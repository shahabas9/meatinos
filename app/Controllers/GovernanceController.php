<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;

final class GovernanceController
{
    public function partnerReceipt(): void
    {
        Auth::requirePermission('finance.view');
        $id=(int)($_GET['id'] ?? 0);
        $statement=Database::connection()->prepare("SELECT pt.*,sh.shareholder_code,sh.name partner_name,sh.phone,sh.email,c.name company_name,c.legal_name,c.tax_number,cba.bank_name,cba.masked_account,u.name posted_by_name FROM partner_transactions pt JOIN shareholders sh ON sh.id=pt.shareholder_id JOIN companies c ON c.id=sh.company_id LEFT JOIN company_bank_accounts cba ON cba.id=pt.company_bank_account_id LEFT JOIN users u ON u.id=pt.posted_by WHERE pt.id=? AND pt.status IN ('posted','reversed')");
        $statement->execute([$id]); $record=$statement->fetch();
        if (!$record) throw new \RuntimeException('Posted partner receipt not found.',404);
        $message='Meatin partner receipt '.$record['transaction_number'].' for '.money($record['amount']).' dated '.date('d M Y',strtotime($record['transaction_date'])).'. Status: '.human_status($record['status']).'.';
        $whatsapp='https://wa.me/'.preg_replace('/\D/','',(string)$record['phone']).'?text='.rawurlencode($message);
        AuditService::log('viewed','partner_receipts',$id,'Partner receipt viewed for printing or sharing.');
        View::render('governance/partner_receipt',['title'=>'Partner Receipt','record'=>$record,'whatsapp'=>$whatsapp,'printView'=>true]);
    }

    public function partnerWelcome(): void
    {
        Auth::requirePermission('finance.view');
        $id=(int)($_GET['id'] ?? 0); $pdo=Database::connection();
        $statement=$pdo->prepare('SELECT sh.*,c.name company_name,c.legal_name,c.tax_number,d.name director_name FROM shareholders sh JOIN companies c ON c.id=sh.company_id LEFT JOIN shareholders d ON d.id=sh.director_id WHERE sh.id=?');
        $statement->execute([$id]); $record=$statement->fetch();
        if (!$record) throw new \RuntimeException('Partner not found.',404);
        $nominees=$pdo->prepare("SELECT nominee_name,relationship,allocation_percent,is_minor,guardian_name,status FROM shareholder_nominees WHERE shareholder_id=? ORDER BY id"); $nominees->execute([$id]);
        $message='Welcome to '.$record['company_name'].', '.$record['name'].' (Partner ID '.$record['shareholder_code'].'). Your registered share amount is '.money($record['share_amount']).'.';
        $whatsapp='https://wa.me/'.preg_replace('/\D/','',(string)$record['phone']).'?text='.rawurlencode($message);
        AuditService::log('viewed','partner_welcome',$id,'Partner welcome letter viewed for printing or sharing.');
        View::render('governance/partner_welcome',['title'=>'Partner Welcome Letter','record'=>$record,'nominees'=>$nominees->fetchAll(),'whatsapp'=>$whatsapp,'printView'=>true]);
    }

    public function partners(): void
    {
        Auth::requirePermission('finance.view');
        $canManage = Auth::can('finance.manage');
        $pdo = Database::connection();
        $query = trim((string) ($_GET['q'] ?? ''));
        $requestedId = max(0, (int) ($_GET['id'] ?? ($_GET['partner_id'] ?? 0)));

        $searchSql = '';
        $params = [];
        if ($query !== '') {
            $searchSql = 'WHERE (sh.name LIKE ? OR sh.shareholder_code LIKE ? OR sh.phone LIKE ? OR sh.email LIKE ? OR sh.pan_number LIKE ?)';
            $like = '%' . $query . '%';
            $params = [$like, $like, $like, $like, $like];
        }

        $partnersStatement = $pdo->prepare("
            SELECT sh.*, c.name AS company_name, d.name AS director_name, d.shareholder_code AS director_code
            FROM shareholders sh
            LEFT JOIN companies c ON c.id = sh.company_id
            LEFT JOIN shareholders d ON d.id = sh.director_id
            {$searchSql}
            ORDER BY sh.sort_order ASC, sh.name ASC
        ");
        $partnersStatement->execute($params);
        $allPartners = $partnersStatement->fetchAll();

        $selectedId = $requestedId;
        if ($selectedId === 0 && !empty($allPartners)) {
            $selectedId = (int) $allPartners[0]['id'];
        }

        $selectedPartner = null;
        if ($selectedId > 0) {
            foreach ($allPartners as $p) {
                if ((int) $p['id'] === $selectedId) {
                    $selectedPartner = $p;
                    break;
                }
            }
            if (!$selectedPartner) {
                $stmt = $pdo->prepare("
                    SELECT sh.*, c.name AS company_name, c.legal_name AS company_legal_name, d.name AS director_name, d.shareholder_code AS director_code
                    FROM shareholders sh
                    LEFT JOIN companies c ON c.id = sh.company_id
                    LEFT JOIN shareholders d ON d.id = sh.director_id
                    WHERE sh.id = ?
                ");
                $stmt->execute([$selectedId]);
                $selectedPartner = $stmt->fetch() ?: null;
            }
        }

        $dividends = [];
        $dividendMetrics = [
            'total_investment' => 0.0,
            'received_share' => 0.0,
            'dividend_rate' => 0.0,
            'dividend_earned' => 0.0,
            'dividend_paid' => 0.0,
            'pending_dividend' => 0.0,
        ];
        $transactions = [];
        $nominees = [];

        if ($selectedPartner) {
            $dividendMetrics['total_investment'] = (float) ($selectedPartner['share_amount'] ?? 0);
            $dividendMetrics['received_share'] = (float) ($selectedPartner['received_share'] ?? 0);

            // Fetch dividends
            $divStmt = $pdo->prepare("
                SELECT pd.*, cba.bank_name AS paying_bank_name, cba.masked_account AS paying_account
                FROM partner_dividends pd
                LEFT JOIN company_bank_accounts cba ON cba.id = pd.company_bank_account_id
                WHERE pd.shareholder_id = ?
                ORDER BY pd.declaration_date DESC, pd.id DESC
            ");
            $divStmt->execute([$selectedPartner['id']]);
            $dividends = $divStmt->fetchAll();

            $earnedSum = 0.0;
            $paidSum = 0.0;
            $pendingSum = 0.0;
            $latestRate = 0.0;
            if (!empty($dividends)) {
                $latestRate = (float) $dividends[0]['dividend_rate'];
                foreach ($dividends as $div) {
                    if ($div['status'] !== 'cancelled' && $div['status'] !== 'reversed') {
                        $earnedSum += (float) ($div['gross_amount'] ?? 0);
                    }
                    if ($div['status'] === 'paid') {
                        $paidSum += (float) ($div['net_amount'] ?? 0);
                    } elseif (in_array($div['status'], ['declared', 'approved'], true)) {
                        $pendingSum += (float) ($div['net_amount'] ?? 0);
                    }
                }
            }
            $dividendMetrics['dividend_rate'] = $latestRate;
            $dividendMetrics['dividend_earned'] = $earnedSum;
            $dividendMetrics['dividend_paid'] = $paidSum;
            $dividendMetrics['pending_dividend'] = $pendingSum;

            // Fetch transactions & compute running balance and credit/debit
            $txStmt = $pdo->prepare("
                SELECT pt.*, cba.bank_name, cba.masked_account, u.name AS posted_by_name
                FROM partner_transactions pt
                LEFT JOIN company_bank_accounts cba ON cba.id = pt.company_bank_account_id
                LEFT JOIN users u ON u.id = pt.posted_by
                WHERE pt.shareholder_id = ?
                ORDER BY pt.transaction_date ASC, pt.id ASC
            ");
            $txStmt->execute([$selectedPartner['id']]);
            $rawTx = $txStmt->fetchAll();

            $totalTarget = (float) ($selectedPartner['share_amount'] ?? 0);
            $cumulativeReceived = 0.0;
            $processedTx = [];

            foreach ($rawTx as $tx) {
                $amount = (float) ($tx['amount'] ?? 0);
                $isCredit = in_array($tx['transaction_type'], ['installment', 'share_addition'], true);
                $isDebit = in_array($tx['transaction_type'], ['share_deduction', 'refund', 'cancellation_settlement'], true);

                $credit = $isCredit ? $amount : ($isDebit ? 0.0 : $amount);
                $debit = $isDebit ? $amount : 0.0;

                if ($tx['status'] === 'posted') {
                    $cumulativeReceived += ($credit - $debit);
                }
                $runningBalance = max(0.0, $totalTarget - $cumulativeReceived);

                $processedTx[] = array_merge($tx, [
                    'credit' => $credit,
                    'debit' => $debit,
                    'running_balance' => $runningBalance,
                    'cumulative_received' => $cumulativeReceived,
                ]);
            }
            $transactions = array_reverse($processedTx);

            // Fetch nominees
            $nomStmt = $pdo->prepare("
                SELECT sn.*
                FROM shareholder_nominees sn
                WHERE sn.shareholder_id = ?
                ORDER BY sn.allocation_percent DESC, sn.id ASC
            ");
            $nomStmt->execute([$selectedPartner['id']]);
            $nominees = $nomStmt->fetchAll();
        }

        // Summary metrics across all partners
        $summary = [
            'total_partners' => count($allPartners),
            'active_partners' => count(array_filter($allPartners, static fn($p) => ($p['status'] ?? '') === 'active')),
            'directors' => count(array_filter($allPartners, static fn($p) => ($p['shareholder_type'] ?? '') === 'director')),
            'total_share_amount' => array_sum(array_column($allPartners, 'share_amount')),
            'total_received_share' => array_sum(array_column($allPartners, 'received_share')),
            'total_pending_share' => array_sum(array_column($allPartners, 'pending_share_amount')),
        ];

        // Dropdown lookups for inline modals
        $companies = $pdo->query("SELECT id, name FROM companies ORDER BY name")->fetchAll();
        $directors = $pdo->query("SELECT id, name FROM shareholders WHERE shareholder_type='director' AND status='active' ORDER BY sort_order, name")->fetchAll();
        $bankAccounts = $pdo->query("SELECT id, bank_name, masked_account FROM company_bank_accounts WHERE status='active' ORDER BY bank_name")->fetchAll();

        AuditService::log('viewed', 'shareholders', $selectedId ?: null, 'Unified Partner tab viewed.');

        View::render('governance/partners', compact(
            'allPartners',
            'selectedPartner',
            'dividends',
            'dividendMetrics',
            'transactions',
            'nominees',
            'summary',
            'companies',
            'directors',
            'bankAccounts',
            'canManage',
            'query'
        ) + ['title' => 'Partners']);
    }

    public function employmentLetter(): void
    {
        Auth::requirePermission('hr.view');
        $id=(int)($_GET['id'] ?? 0);
        $statement=Database::connection()->prepare('SELECT ea.*,e.employee_number,e.full_name,e.address,e.alternate_address,e.phone,e.email,c.name company_name,c.legal_name,c.tax_number,u.name issued_by_name FROM employee_appointments ea JOIN employees e ON e.id=ea.employee_id CROSS JOIN companies c LEFT JOIN users u ON u.id=ea.issued_by WHERE ea.id=? ORDER BY c.id LIMIT 1');
        $statement->execute([$id]); $record=$statement->fetch();
        if (!$record) throw new \RuntimeException('Employment letter not found.',404);
        AuditService::log('viewed','employment_letters',$id,'Employment letter viewed for printing.');
        View::render('governance/employment_letter',['title'=>human_status($record['letter_type']).' Letter','record'=>$record,'printView'=>true]);
    }
}
