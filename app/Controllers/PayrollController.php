<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use DateTimeImmutable;
use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;

final class PayrollController
{
    public function index(): void
    {
        Auth::requirePermission('hr.view');
        $pdo = Database::connection();
        $runs = $pdo->query('SELECT pr.*,u.name approved_by_name FROM payroll_runs pr LEFT JOIN users u ON u.id=pr.approved_by ORDER BY period_end DESC,id DESC')->fetchAll();
        $selected = !empty($_GET['run']) ? (int) $_GET['run'] : (int) ($runs[0]['id'] ?? 0);
        $selectedRun = null;
        foreach ($runs as $run) {
            if ((int) $run['id'] === $selected) $selectedRun = $run;
        }
        $items = [];
        if ($selected) {
            $statement = $pdo->prepare('SELECT pi.*,e.employee_number,e.full_name,e.department FROM payroll_items pi JOIN employees e ON e.id=pi.employee_id WHERE pi.payroll_run_id=? ORDER BY e.full_name');
            $statement->execute([$selected]);
            $items = $statement->fetchAll();
        }
        View::render('payroll/index', ['title' => 'Payroll', 'runs' => $runs, 'items' => $items, 'selected' => $selected, 'selectedRun' => $selectedRun, 'canManage' => Auth::can('hr.manage')]);
    }

    public function generate(): void
    {
        Auth::requirePermission('hr.manage');
        Csrf::verify($_POST['_token'] ?? null);
        $parseDate = static function (string $value): ?DateTimeImmutable {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            return $date && $date->format('Y-m-d') === $value ? $date : null;
        };
        $start = $parseDate((string) ($_POST['period_start'] ?? ''));
        $end = $parseDate((string) ($_POST['period_end'] ?? ''));
        $payDate = $parseDate((string) ($_POST['pay_date'] ?? ''));
        if (!$start || !$end || !$payDate) {
            flash('danger', 'Enter valid payroll dates.');
            redirect('payroll');
        }
        if ($end < $start || $start->diff($end)->days > 31) {
            flash('danger', 'Payroll periods must be between 1 and 31 days.');
            redirect('payroll');
        }
        if ($payDate < $end) {
            flash('danger', 'Pay date cannot be before the payroll period ends.');
            redirect('payroll');
        }
        $pdo = Database::connection();
        $overlap = $pdo->prepare("SELECT COUNT(*) FROM payroll_runs WHERE status<>'cancelled' AND period_start<=? AND period_end>=?");
        $overlap->execute([$end->format('Y-m-d'), $start->format('Y-m-d')]);
        if ((int) $overlap->fetchColumn() > 0) {
            flash('danger', 'An active payroll run already overlaps this period.');
            redirect('payroll');
        }
        $employees = $pdo->query("SELECT * FROM employees WHERE status='active' ORDER BY id")->fetchAll();
        if (!$employees) {
            flash('danger', 'No active employees are available for payroll.');
            redirect('payroll');
        }
        $days = (int) $start->diff($end)->days + 1;
        $factor = min(1, $days / 30);
        $sequenceStatement = $pdo->prepare('SELECT COUNT(*)+1 FROM payroll_runs WHERE YEAR(period_end)=? AND MONTH(period_end)=?');
        $sequenceStatement->execute([$end->format('Y'), $end->format('m')]);
        $runNumber = 'PAY-' . $end->format('Ym') . '-' . str_pad((string) $sequenceStatement->fetchColumn(), 2, '0', STR_PAD_LEFT);
        $pdo->beginTransaction();
        try {
            $pdo->prepare("INSERT INTO payroll_runs (run_number,period_start,period_end,pay_date,status) VALUES (?,?,?,?,'draft')")->execute([$runNumber, $start->format('Y-m-d'), $end->format('Y-m-d'), $payDate->format('Y-m-d')]);
            $runId = (int) $pdo->lastInsertId();
            $totals = ['gross' => 0.0, 'deductions' => 0.0, 'net' => 0.0];
            $attendance = $pdo->prepare("SELECT COALESCE(SUM(overtime_hours+extra_duty_hours),0) overtime, SUM(status='absent') absences FROM attendance WHERE employee_id=? AND attendance_date BETWEEN ? AND ?");
            $benefits = $pdo->prepare("SELECT COALESCE(SUM(CASE WHEN benefit_type IN ('bonus','allowance') THEN employer_amount ELSE 0 END),0) allowances,COALESCE(SUM(employee_amount),0) deductions FROM employee_benefits WHERE employee_id=? AND status='active' AND effective_from<=? AND (effective_to IS NULL OR effective_to>=?)");
            $commissions = $pdo->prepare("SELECT COALESCE(SUM(commission_amount),0) FROM sales_commission_accruals WHERE employee_id=? AND period_end BETWEEN ? AND ? AND status IN ('calculated','approved')");
            $advances = $pdo->prepare("SELECT COALESCE(SUM(LEAST(monthly_recovery,balance_amount)),0) FROM salary_advances WHERE employee_id=? AND status IN ('paid','recovering') AND balance_amount>0 AND (recovery_start IS NULL OR recovery_start<=?)");
            $item = $pdo->prepare('INSERT INTO payroll_items (payroll_run_id,employee_id,basic_amount,overtime_hours,overtime_amount,allowances,commission_amount,advance_recovery,deductions,remarks,net_amount) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
            foreach ($employees as $employee) {
                $attendance->execute([$employee['id'], $start->format('Y-m-d'), $end->format('Y-m-d')]);
                $record = $attendance->fetch();
                $basic = round((float) $employee['basic_salary'] * $factor, 2);
                $overtimeHours = (float) ($record['overtime'] ?? 0);
                $overtime = round($overtimeHours * (float) $employee['overtime_rate'], 2);
                $benefits->execute([$employee['id'],$end->format('Y-m-d'),$start->format('Y-m-d')]);
                $benefit = $benefits->fetch();
                $allowances = round((float) ($benefit['allowances'] ?? 0) * $factor, 2);
                $commissions->execute([$employee['id'],$start->format('Y-m-d'),$end->format('Y-m-d')]);
                $commission = round((float) $commissions->fetchColumn(), 2);
                $advances->execute([$employee['id'],$end->format('Y-m-d')]);
                $advanceRecovery = round((float) $advances->fetchColumn(), 2);
                $absenceDeduction = round((float) ($record['absences'] ?? 0) * ((float) $employee['basic_salary'] / 30), 2);
                $benefitDeduction = round((float) ($benefit['deductions'] ?? 0) * $factor, 2);
                $gross = round($basic + $overtime + $allowances + $commission, 2);
                $advanceRecovery = round(min($advanceRecovery, max(0, $gross - $absenceDeduction - $benefitDeduction)), 2);
                $deductions = round(min($gross, $absenceDeduction + $benefitDeduction + $advanceRecovery), 2);
                $net = max(0, round($gross - $deductions, 2));
                $remarks = 'Absence: ' . money($absenceDeduction) . '; statutory/benefits: ' . money($benefitDeduction) . '; advance recovery: ' . money($advanceRecovery) . '.';
                $item->execute([$runId, $employee['id'], $basic, $overtimeHours, $overtime, $allowances, $commission, $advanceRecovery, $deductions, $remarks, $net]);
                $totals['gross'] += $gross;
                $totals['deductions'] += $deductions;
                $totals['net'] += $net;
            }
            $pdo->prepare('UPDATE payroll_runs SET gross_amount=?,deductions_amount=?,net_amount=? WHERE id=?')->execute([$totals['gross'], $totals['deductions'], $totals['net'], $runId]);
            AuditService::log('generated', 'payroll', $runId, 'Payroll run generated.', null, ['run_number' => $runNumber, 'net_amount' => $totals['net']]);
            $pdo->commit();
            flash('success', 'Payroll run generated for ' . count($employees) . ' employees.');
            redirect('payroll', ['run' => $runId]);
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }

    public function status(): void
    {
        Auth::requirePermission('hr.manage');
        Csrf::verify($_POST['_token'] ?? null);
        $runId = (int) ($_POST['run_id'] ?? 0);
        $action = (string) ($_POST['action'] ?? '');
        $transitions = ['approve' => ['draft', 'approved'], 'pay' => ['approved', 'paid'], 'cancel' => ['draft', 'cancelled']];
        if ($runId < 1 || !isset($transitions[$action])) {
            throw new \RuntimeException('Invalid payroll action.', 422);
        }
        [$from, $to] = $transitions[$action];
        $pdo = Database::connection();
        $statement = $pdo->prepare('SELECT * FROM payroll_runs WHERE id=?');
        $statement->execute([$runId]);
        $old = $statement->fetch();
        if (!$old) {
            throw new \RuntimeException('Payroll run not found.', 404);
        }
        if ($old['status'] !== $from) {
            flash('danger', 'That payroll status transition is no longer valid.');
            redirect('payroll', ['run' => $runId]);
        }
        $pdo->beginTransaction();
        try {
            if ($action === 'approve') {
                $pdo->prepare("UPDATE payroll_runs SET status='approved',approved_by=? WHERE id=? AND status='draft'")->execute([Auth::user()['id'], $runId]);
                $pdo->prepare("UPDATE sales_commission_accruals sc JOIN payroll_items pi ON pi.employee_id=sc.employee_id SET sc.status='approved',sc.approved_by=? WHERE pi.payroll_run_id=? AND sc.period_end BETWEEN ? AND ? AND sc.status='calculated'")
                    ->execute([Auth::user()['id'],$runId,$old['period_start'],$old['period_end']]);
            } elseif ($action === 'pay') {
                $this->postPaidPayroll($pdo, $old);
                $pdo->prepare("UPDATE payroll_runs SET status='paid' WHERE id=? AND status='approved'")->execute([$runId]);
                $pdo->prepare("UPDATE sales_commission_accruals sc JOIN payroll_items pi ON pi.employee_id=sc.employee_id SET sc.status='included',sc.payroll_run_id=? WHERE pi.payroll_run_id=? AND sc.period_end BETWEEN ? AND ? AND sc.status='approved'")
                    ->execute([$runId,$runId,$old['period_start'],$old['period_end']]);
                $this->recoverSalaryAdvances($pdo, $runId);
            } else {
                $pdo->prepare('UPDATE payroll_runs SET status=? WHERE id=? AND status=?')->execute([$to, $runId, $from]);
            }
            AuditService::log($to, 'payroll', $runId, 'Payroll run marked ' . $to . '.', $old, ['status' => $to]);
            $pdo->commit();
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        flash('success', 'Payroll run marked ' . human_status($to) . '.');
        redirect('payroll', ['run' => $runId]);
    }

    private function postPaidPayroll(\PDO $pdo, array $run): void
    {
        $exists = $pdo->prepare("SELECT COUNT(*) FROM journal_entries WHERE reference_type='payroll' AND reference_id=? AND status='posted'");
        $exists->execute([$run['id']]);
        if ((int) $exists->fetchColumn()) throw new \RuntimeException('This payroll is already posted.', 422);
        $totals = $pdo->prepare('SELECT COALESCE(SUM(basic_amount+overtime_amount+allowances+commission_amount),0) gross,COALESCE(SUM(net_amount),0) net,COALESCE(SUM(advance_recovery),0) advances,COALESCE(SUM(deductions-advance_recovery),0) other_deductions FROM payroll_items WHERE payroll_run_id=?');
        $totals->execute([$run['id']]);
        $amounts = $totals->fetch();
        $gross = round((float) $amounts['gross'],2); $net = round((float) $amounts['net'],2); $advances = round((float) $amounts['advances'],2); $other = round((float) $amounts['other_deductions'],2);
        if (abs($gross-($net+$advances+$other)) > 0.01) throw new \RuntimeException('Payroll journal is not balanced.', 422);
        $bank = $pdo->query("SELECT id FROM company_bank_accounts WHERE status='active' ORDER BY is_primary DESC,id LIMIT 1 FOR UPDATE")->fetchColumn();
        if (!$bank) throw new \RuntimeException('Configure an active company bank or cash account before paying payroll.', 422);
        $entryNumber = 'JE-PAYROLL-' . $run['id'];
        $pdo->prepare("INSERT INTO journal_entries (entry_number,entry_date,reference_type,reference_id,description,status,posted_by,posted_at) VALUES (?,?, 'payroll',?,?,'posted',?,NOW())")
            ->execute([$entryNumber,$run['pay_date'],$run['id'],'Automated payroll posting: ' . $run['run_number'],Auth::user()['id']]);
        $entryId = (int) $pdo->lastInsertId();
        $lines = [['5100',$gross,0.0,'Payroll gross cost'],['1000',0.0,$net,'Net salary paid'],['1600',0.0,$advances,'Salary advances recovered'],['2200',0.0,$other,'Statutory and employee deductions payable']];
        $account = $pdo->prepare("SELECT id FROM chart_of_accounts WHERE account_code=? AND status='active'");
        $insert = $pdo->prepare('INSERT INTO journal_lines (journal_entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)');
        foreach ($lines as [$code,$debit,$credit,$description]) {
            if ((float)$debit === 0.0 && (float)$credit === 0.0) continue;
            $account->execute([$code]); $accountId = $account->fetchColumn();
            if (!$accountId) throw new \RuntimeException("Required payroll account {$code} is missing.",422);
            $insert->execute([$entryId,$accountId,$debit,$credit,$description]);
        }
        $pdo->prepare('UPDATE company_bank_accounts SET current_balance=current_balance-? WHERE id=?')->execute([$net,$bank]);
    }

    private function recoverSalaryAdvances(\PDO $pdo, int $runId): void
    {
        $items = $pdo->prepare('SELECT employee_id,advance_recovery FROM payroll_items WHERE payroll_run_id=? AND advance_recovery>0 ORDER BY employee_id');
        $items->execute([$runId]);
        $select = $pdo->prepare("SELECT * FROM salary_advances WHERE employee_id=? AND status IN ('paid','recovering') AND balance_amount>0 ORDER BY recovery_start,id FOR UPDATE");
        $update = $pdo->prepare("UPDATE salary_advances SET recovered_amount=recovered_amount+?,balance_amount=GREATEST(0,balance_amount-?),status=CASE WHEN balance_amount-?<=0.001 THEN 'recovered' ELSE 'recovering' END WHERE id=?");
        foreach ($items->fetchAll() as $item) {
            $remaining = (float) $item['advance_recovery'];
            $select->execute([$item['employee_id']]);
            foreach ($select->fetchAll() as $advance) {
                if ($remaining <= 0.001) break;
                $recovery = min($remaining,(float)$advance['balance_amount']);
                $update->execute([$recovery,$recovery,$recovery,$advance['id']]);
                $remaining = round($remaining-$recovery,2);
            }
        }
    }
}
