<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\Validator;
use MeatinOS\Core\View;
use MeatinOS\Repositories\ErpRepository;
use MeatinOS\Services\AuditService;
use MeatinOS\Services\NumberingService;
use MeatinOS\Services\PricingService;
use PDO;
use PDOException;

final class ModuleController
{
    private ErpRepository $repository;

    public function __construct()
    {
        $this->repository = new ErpRepository();
    }

    public function index(): void
    {
        [$name, $module] = $this->resolve();
        if ($name === 'shareholders') {
            $params = [];
            if (!empty($_GET['id'])) $params['id'] = (int) $_GET['id'];
            if (!empty($_GET['partner_id'])) $params['id'] = (int) $_GET['partner_id'];
            if (!empty($_GET['edit'])) $params['edit'] = (int) $_GET['edit'];
            if (!empty($_GET['q'])) $params['q'] = trim((string) $_GET['q']);
            redirect('partners', $params);
        }
        if ($name === 'employee_benefits') {
            $params = ['name' => 'employees', 'tab' => 'benefits'];
            if (!empty($_GET['employee_id'])) $params['employee_id'] = (int) $_GET['employee_id'];
            if (!empty($_GET['edit'])) $params['edit_benefit'] = (int) $_GET['edit'];
            if (!empty($_GET['q'])) $params['q'] = trim((string) $_GET['q']);
            redirect('module', $params);
        }
        Auth::requirePermission($module['permission'] . '.view');
        $query = trim((string) ($_GET['q'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $scope = $this->scope($name);
        $result = $this->repository->paginate($module, $query, $page, $name === 'shareholders' ? 100 : 15, $scope);
        $lookups = $this->repository->lookupOptions($module);
        $lookupMaps = [];
        foreach ($lookups as $field => $options) {
            $lookupMaps[$field] = [];
            foreach ($options as $option) {
                $lookupMaps[$field][(string) $option['option_value']] = $option['option_label'];
            }
        }
        $rowGroups = [];
        if ($name === 'shareholders') {
            $directors = [];
            foreach (Database::connection()->query("SELECT id,name,sort_order FROM shareholders WHERE shareholder_type='director' ORDER BY sort_order,name")->fetchAll() as $director) {
                $directors[(int) $director['id']] = ['name'=>(string) $director['name'],'sort'=>(int) $director['sort_order']];
            }
            usort($result['rows'], static function (array $left, array $right) use ($directors): int {
                $leftGroup = ($left['shareholder_type'] ?? '') === 'director' ? (int) $left['id'] : (int) ($left['director_id'] ?? 0);
                $rightGroup = ($right['shareholder_type'] ?? '') === 'director' ? (int) $right['id'] : (int) ($right['director_id'] ?? 0);
                $leftKey = sprintf('%010d-%s-%d-%010d-%s', $directors[$leftGroup]['sort'] ?? PHP_INT_MAX, $directors[$leftGroup]['name'] ?? 'Unassigned', ($left['shareholder_type'] ?? '') === 'director' ? 0 : 1, (int) ($left['sort_order'] ?? 0), (string) ($left['name'] ?? ''));
                $rightKey = sprintf('%010d-%s-%d-%010d-%s', $directors[$rightGroup]['sort'] ?? PHP_INT_MAX, $directors[$rightGroup]['name'] ?? 'Unassigned', ($right['shareholder_type'] ?? '') === 'director' ? 0 : 1, (int) ($right['sort_order'] ?? 0), (string) ($right['name'] ?? ''));
                return strcasecmp($leftKey, $rightKey);
            });
            foreach ($result['rows'] as $row) {
                $groupId = ($row['shareholder_type'] ?? '') === 'director' ? (int) $row['id'] : (int) ($row['director_id'] ?? 0);
                $rowGroups[(int) $row['id']] = $groupId && isset($directors[$groupId]) ? 'Director: ' . $directors[$groupId]['name'] : 'Partners without an assigned director';
            }
        }
        $edit = null;
        if (!empty($_GET['edit']) && Auth::can($module['permission'] . '.manage')) {
            $edit = $this->repository->find($module, (int) $_GET['edit'], $scope);
        }
        if ($name === 'employees' && $edit && isset($lookups['manager_id'])) {
            $lookups['manager_id'] = array_values(array_filter($lookups['manager_id'], static fn (array $option): bool => (int) $option['option_value'] !== (int) $edit['id']));
        }
        if ($name === 'employees') {
            $pdo = Database::connection();
            $tab = ($_GET['tab'] ?? 'directory') === 'benefits' ? 'benefits' : 'directory';
            $selectedEmployeeId = !empty($_GET['employee_id']) ? (int) $_GET['employee_id'] : 0;

            $allEmployees = $pdo->query("SELECT id, employee_number, full_name, department, job_title, status, basic_salary, uan_number, esi_number, insurance_number, pan_number, aadhaar_number, phone, email, join_date FROM employees ORDER BY full_name ASC")->fetchAll(PDO::FETCH_ASSOC);

            $selectedEmployee = null;
            if ($selectedEmployeeId > 0) {
                foreach ($allEmployees as $emp) {
                    if ((int) $emp['id'] === $selectedEmployeeId) {
                        $selectedEmployee = $emp;
                        break;
                    }
                }
            }

            $summary = [
                'total_employees' => count($allEmployees),
                'active_employees' => 0,
                'esi_enrolled' => 0,
                'pf_enrolled' => 0,
                'active_benefits' => 0,
                'monthly_employer_amount' => 0.0,
                'monthly_employee_amount' => 0.0,
                'monthly_total_statutory' => 0.0,
            ];

            foreach ($allEmployees as $emp) {
                if (($emp['status'] ?? '') === 'active') {
                    $summary['active_employees']++;
                }
                if (!empty($emp['esi_number'])) {
                    $summary['esi_enrolled']++;
                }
                if (!empty($emp['uan_number'])) {
                    $summary['pf_enrolled']++;
                }
            }

            $benefitStats = $pdo->query("SELECT 
                COUNT(*) as total_active,
                COALESCE(SUM(employer_amount), 0) as total_employer,
                COALESCE(SUM(employee_amount), 0) as total_employee,
                COUNT(DISTINCT CASE WHEN benefit_type='esi' THEN employee_id END) as esi_benefit_count,
                COUNT(DISTINCT CASE WHEN benefit_type='pf' THEN employee_id END) as pf_benefit_count
                FROM employee_benefits WHERE status = 'active'")->fetch(PDO::FETCH_ASSOC);

            if ($benefitStats) {
                $summary['active_benefits'] = (int) $benefitStats['total_active'];
                $summary['monthly_employer_amount'] = (float) $benefitStats['total_employer'];
                $summary['monthly_employee_amount'] = (float) $benefitStats['total_employee'];
                $summary['monthly_total_statutory'] = $summary['monthly_employer_amount'] + $summary['monthly_employee_amount'];
                $summary['esi_enrolled'] = max($summary['esi_enrolled'], (int) $benefitStats['esi_benefit_count']);
                $summary['pf_enrolled'] = max($summary['pf_enrolled'], (int) $benefitStats['pf_benefit_count']);
            }

            $benefitSql = "SELECT b.*, e.employee_number, e.full_name as employee_name, e.department, e.job_title, e.uan_number, e.esi_number
                           FROM employee_benefits b
                           JOIN employees e ON e.id = b.employee_id
                           WHERE 1=1";
            $benefitParams = [];
            if ($selectedEmployeeId > 0) {
                $benefitSql .= " AND b.employee_id = ?";
                $benefitParams[] = $selectedEmployeeId;
            }
            if ($query !== '' && $tab === 'benefits') {
                $benefitSql .= " AND (e.full_name LIKE ? OR e.employee_number LIKE ? OR b.benefit_type LIKE ? OR b.reference_number LIKE ? OR b.notes LIKE ?)";
                $qWild = '%' . $query . '%';
                $benefitParams = array_merge($benefitParams, [$qWild, $qWild, $qWild, $qWild, $qWild]);
            }
            $benefitSql .= " ORDER BY b.effective_from DESC, b.id DESC";

            $benefitStmt = $pdo->prepare($benefitSql);
            $benefitStmt->execute($benefitParams);
            $benefits = $benefitStmt->fetchAll(PDO::FETCH_ASSOC);

            $editBenefit = null;
            if (!empty($_GET['edit_benefit']) && Auth::can($module['permission'] . '.manage')) {
                $ebStmt = $pdo->prepare("SELECT * FROM employee_benefits WHERE id = ?");
                $ebStmt->execute([(int) $_GET['edit_benefit']]);
                $editBenefit = $ebStmt->fetch(PDO::FETCH_ASSOC) ?: null;
            }

            $allModules = config('modules', []);
            $benefitsModule = $allModules['employee_benefits'] ?? [];

            View::render('module/employees', [
                'title' => 'Employees, ESI, PF & Benefits',
                'moduleName' => 'employees',
                'module' => $module,
                'benefitsModule' => $benefitsModule,
                'result' => $result,
                'allEmployees' => $allEmployees,
                'selectedEmployee' => $selectedEmployee,
                'selectedEmployeeId' => $selectedEmployeeId,
                'benefits' => $benefits,
                'summary' => $summary,
                'currentTab' => $tab,
                'lookups' => $lookups,
                'lookupMaps' => $lookupMaps,
                'edit' => $edit,
                'editBenefit' => $editBenefit,
                'query' => $query,
                'canManage' => Auth::can($module['permission'] . '.manage'),
                'canDelete' => Auth::can($module['permission'] . '.manage') && $this->deletionStatuses('employees') !== false,
                'deleteStatuses' => $this->deletionStatuses('employees'),
            ]);
            return;
        }
        View::render('module/index', [
            'title' => $module['title'], 'moduleName' => $name, 'module' => $module,
            'result' => $result, 'lookups' => $lookups, 'lookupMaps' => $lookupMaps,
            'edit' => $edit, 'query' => $query, 'canManage' => Auth::can($module['permission'] . '.manage'),
            'canDelete' => Auth::can($module['permission'] . '.manage') && $this->deletionStatuses($name) !== false,
            'deleteStatuses' => $this->deletionStatuses($name),
            'rowGroups' => $rowGroups,
        ]);
    }

    public function save(): void
    {
        [$name, $module] = $this->resolve(true);
        Auth::requirePermission($module['permission'] . '.manage');
        Csrf::verify($_POST['_token'] ?? null);
        if (!empty($module['workflow_only'])) {
            throw new \RuntimeException('This record is created and updated only through its controlled workflow.', 422);
        }
        [$data, $errors] = Validator::validate($module['fields'], $_POST);
        $this->applyDerivedValues($name, $data);
        $errors += $this->validateLookups($module, $data);
        $errors += $this->domainErrors($name, $data);
        if ($errors) {
            $_SESSION['_errors'] = $errors;
            $_SESSION['_old'] = $_POST;
            $errorDetails = [];
            foreach ($errors as $fieldKey => $errMsg) {
                $fieldLabel = $module['fields'][$fieldKey]['label'] ?? ucwords(str_replace('_', ' ', (string) $fieldKey));
                if (stripos($errMsg, $fieldLabel) !== false || stripos($errMsg, str_replace(['_', 'id'], [' ', ''], (string) $fieldKey)) !== false) {
                    $errorDetails[] = $errMsg;
                } else {
                    $errorDetails[] = "{$fieldLabel}: {$errMsg}";
                }
            }
            $errorSummary = implode(' ', $errorDetails);
            $singular = $module['singular'] ?? 'record';
            $flashMsg = count($errors) === 1
                ? "Unable to save {$singular}: " . reset($errorDetails)
                : "Unable to save {$singular}. Please check: " . $errorSummary;
            flash('danger', $flashMsg);
            if (!empty($_POST['_return_to'])) {
                header('Location: ' . $_POST['_return_to']);
                exit;
            }
            if ($name === 'employee_benefits') {
                redirect('module', ['name' => 'employees', 'tab' => 'benefits', 'edit_benefit' => (int) ($_POST['id'] ?? 0)]);
            }
            redirect('module', ['name' => $name, 'edit' => (int) ($_POST['id'] ?? 0)]);
        }
        $id = (int) ($_POST['id'] ?? 0);
        $scope = $this->scope($name);
        $old = $id ? $this->repository->find($module, $id, $scope) : null;
        if ($id && !$old) {
            http_response_code(404);
            throw new \RuntimeException('Record not found.', 404);
        }
        $this->assertDraftMutation($name, $old);
        $pdo = Database::connection();
        $saved = false;
        try {
            $pdo->beginTransaction();
            if ($id === 0 && !empty($module['auto_number'])) {
                $numbering = $module['auto_number'];
                $field = (string) $numbering['field'];
                $data[$field] = NumberingService::next((string) $numbering['type'], !empty($data['plant_id']) ? (int) $data['plant_id'] : null, (string) ($numbering['prefix'] ?? 'DOC'));
            }
            $this->addOwnershipFields($module['table'], $data, $id === 0);
            if ($id) {
                $this->repository->update($module, $id, $data, $scope);
                $event = 'updated';
            } else {
                $id = $this->repository->insert($module, $data);
                $event = 'created';
            }
            $this->applySideEffects($name, $id, $old, $data);
            AuditService::log($event, $name, $id, $module['singular'] . ' ' . $event . '.', $old, $data);
            $pdo->commit();
            unset($_SESSION['_errors'], $_SESSION['_old']);
            flash('success', $module['singular'] . ' saved successfully.');
            $saved = true;
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ((string) $exception->getCode() === '23000' || !empty($exception->errorInfo[1])) {
                $parsed = $this->parseDatabaseException($exception, $module, $_POST);
                $_SESSION['_errors'] = $parsed['errors'];
                $_SESSION['_old'] = $_POST;
                flash('danger', $parsed['message']);
                if (!empty($_POST['_return_to'])) {
                    header('Location: ' . $_POST['_return_to']);
                    exit;
                }
                if ($name === 'employee_benefits') {
                    redirect('module', ['name' => 'employees', 'tab' => 'benefits', 'edit_benefit' => $id]);
                }
                redirect('module', ['name'=>$name,'edit'=>$id]);
            } else {
                throw $exception;
            }
        }
        if ($saved) {
            if ($name === 'shareholders' && empty($_POST['id']) && $id > 0) {
                redirect('partners', ['id' => $id]);
            }
            if (!empty($_POST['_return_to'])) {
                header('Location: ' . $_POST['_return_to']);
                exit;
            }
            if ($name === 'employee_benefits') {
                redirect('module', ['name' => 'employees', 'tab' => 'benefits']);
            }
            $workflowEntity = ['purchase_orders'=>'purchase_order','bird_receipts'=>'bird_receipt','production_batches'=>'production_batch','sales_orders'=>'sales_order','goods_receipts'=>'goods_receipt'][$name] ?? null;
            if ($workflowEntity) redirect('workflow', ['entity'=>$workflowEntity,'id'=>$id]);
        }
        if ($name === 'employee_benefits') {
            redirect('module', ['name' => 'employees', 'tab' => 'benefits']);
        }
        redirect('module', ['name' => $name]);
    }

    public function delete(): void
    {
        [$name, $module] = $this->resolve(true);
        Auth::requirePermission($module['permission'] . '.manage');
        Csrf::verify($_POST['_token'] ?? null);
        $allowedStatuses = $this->deletionStatuses($name);
        if ($allowedStatuses === false || !empty($module['workflow_only'])) throw new \RuntimeException('This module does not permit permanent deletion.', 422);
        $id = (int) ($_POST['id'] ?? 0);
        $scope = $this->scope($name);
        $old = $this->repository->find($module,$id,$scope);
        if (!$old) throw new \RuntimeException('Record not found.',404);
        if (is_array($allowedStatuses) && !in_array((string)($old['status'] ?? ''),$allowedStatuses,true)) throw new \RuntimeException('Only a new draft, inactive, rejected, or cancelled record can be deleted.',422);
        $entryTimestamp = $old['created_at'] ?? $old['updated_at'] ?? null;
        if (!$entryTimestamp || strtotime((string)$entryTimestamp) < time()-86400) throw new \RuntimeException('Only entries added within the last 24 hours can be deleted. Archive older records instead.',422);
        $pdo=Database::connection();
        try {
            $pdo->beginTransaction();
            AuditService::log('deleted',$name,$id,$module['singular'].' deleted by an authorized manager.',$old,null);
            $this->repository->delete($module,$id,$scope);
            if ($name==='sales_quotation_items') $this->recalculateQuotation((int)$old['sales_quotation_id']);
            $pdo->commit();
            flash('success',$module['singular'].' deleted. The action remains in the audit trail.');
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((string)$exception->getCode()==='23000') {
                $detail = (string) ($exception->errorInfo[2] ?? $exception->getMessage());
                if (preg_match("/(?:REFERENCES|TABLE)\s+[`\"]?(?<child>[^`\" ]+)[`\"]?/i", $detail, $m)) {
                    $childClean = ucwords(str_replace('_', ' ', $m['child']));
                    flash('danger', "Cannot delete this {$module['singular']} because active records in '{$childClean}' depend on it. Set it inactive or cancel it instead.");
                } else {
                    flash('danger', "This {$module['singular']} is referenced by other active records and cannot be deleted. Set it inactive or cancel it instead.");
                }
            } else {
                throw $exception;
            }
        }
        if (!empty($_POST['_return_to'])) {
            header('Location: ' . $_POST['_return_to']);
            exit;
        }
        if ($name === 'employee_benefits') {
            redirect('module', ['name' => 'employees', 'tab' => 'benefits']);
        }
        redirect('module',['name'=>$name]);
    }

    private function resolve(bool $fromPost = false): array
    {
        $source = $fromPost ? $_POST : $_GET;
        $key = $fromPost ? '_module' : 'name';
        $name = preg_replace('/[^a-z_]/', '', (string) ($source[$key] ?? ''));
        $modules = config('modules', []);
        if (!$name || !isset($modules[$name])) {
            http_response_code(404);
            throw new \RuntimeException('ERP module not found.', 404);
        }
        return [$name, $modules[$name]];
    }

    private function scope(string $moduleName): ?array
    {
        $user = Auth::user();
        if (!$user || $user['role_slug'] !== 'customer') {
            if ($user && $user['role_slug'] === 'driver') {
                return in_array($moduleName, ['dispatches', 'fuel_logs'], true)
                    ? ['column' => 'driver_id', 'value' => $user['employee_id'] ?? 0]
                    : ['column' => 'id', 'value' => 0];
            }
            return null;
        }
        if (in_array($moduleName, ['sales_orders', 'sales_quotations', 'invoices', 'payments'], true)) {
            return ['column' => 'customer_id', 'value' => $user['customer_id'] ?? 0];
        }
        if ($moduleName === 'dispatches') {
            return ['sql' => 'sales_order_id IN (SELECT id FROM sales_orders WHERE customer_id = :scope)', 'value' => $user['customer_id'] ?? 0];
        }
        return ['column' => 'id', 'value' => 0];
    }

    private function applyDerivedValues(string $name, array &$data): void
    {
        if ($name === 'bird_receipts') {
            if ((float)($data['received_quantity'] ?? 0) <= 0) $data['received_quantity'] = (float)($data['net_weight_kg'] ?? 0);
            $data['total_value'] = round((float)$data['received_quantity'] * (float)($data['unit_purchase_cost'] ?? 0),2);
            $currentStatus = '';
            if (!empty($_POST['id'])) {
                $statement = Database::connection()->prepare('SELECT status FROM bird_receipts WHERE id=?');
                $statement->execute([(int) $_POST['id']]);
                $currentStatus = (string) $statement->fetchColumn();
            }
            if (in_array($currentStatus, ['released','rejected'], true)) $data['status'] = $currentStatus;
            elseif (($data['vet_status'] ?? '') === 'rejected') $data['status'] = 'rejected';
            elseif (($data['vet_status'] ?? '') === 'approved' && trim((string) ($data['vet_certificate'] ?? '')) !== '') $data['status'] = 'accepted';
            else $data['status'] = 'quarantine';
        }
        if ($name === 'sales_orders' && !empty($data['customer_id'])) {
            $statement=Database::connection()->prepare('SELECT assigned_salesman_id,default_rate_card_id,address FROM customers WHERE id=?');
            $statement->execute([(int)$data['customer_id']]); $customer=$statement->fetch();
            if ($customer) {
                if (empty($data['sales_person_id'])) $data['sales_person_id']=$customer['assigned_salesman_id'] ?: null;
                if (empty($data['rate_card_id'])) $data['rate_card_id']=$customer['default_rate_card_id'] ?: null;
                if (empty($data['delivery_address'])) $data['delivery_address']=$data['delivery_location'] ?: ($customer['address'] ?: 'Customer delivery location');
            }
            if (empty($data['received_by_employee_id']) && !empty(Auth::user()['employee_id'])) $data['received_by_employee_id'] = (int) Auth::user()['employee_id'];
        }
        if ($name === 'sales_quotation_items') {
            $productId=(int)($data['product_id'] ?? 0);
            if ($productId>0 && (empty($data['hsn_code']) || empty($data['material_description']))) {
                $statement=Database::connection()->prepare('SELECT name,hsn_code FROM products WHERE id=?');$statement->execute([$productId]);$product=$statement->fetch();
                if ($product) { if (empty($data['hsn_code'])) $data['hsn_code']=$product['hsn_code']; if (empty($data['material_description'])) $data['material_description']=$product['name']; }
            }
            $gross=round((float)($data['quantity'] ?? 0)*(float)($data['unit_price'] ?? 0),2);
            $taxable=max(0,$gross-(float)($data['discount_amount'] ?? 0));
            $data['tax_amount']=round($taxable*gst_rate(),2); $data['line_total']=round($taxable+$data['tax_amount'],2);
        }
        if ($name === 'rate_card_items') {
            $pricing=(new PricingService())->calculate((int)($data['product_id'] ?? 0),(int)($data['rate_card_id'] ?? 0) ?: null,(float)($data['live_bird_cost_factor'] ?? 1),(float)($data['margin_percent'] ?? 0),(float)($data['fixed_margin'] ?? 0));
            $data['calculated_price']=$pricing['calculated_price']; if (empty($data['unit_price'])) $data['unit_price']=$pricing['effective_price']; if (empty($data['hsn_code'])) $data['hsn_code']=$pricing['hsn_code'];
        }
        if ($name === 'shareholders') $data['pending_share_amount']=round(max(0,(float)($data['share_amount'] ?? 0)-(float)($data['received_share'] ?? 0)),2);
        if ($name === 'production_batches') {
            $input = (float) ($data['input_weight_kg'] ?? 0);
            $data['yield_percent'] = $input > 0 ? round(((float) ($data['output_weight_kg'] ?? 0) / $input) * 100, 2) : 0;
        }
        if (in_array($name, ['invoices', 'supplier_invoices'], true)) {
            $data['balance_amount'] = max(0, (float) ($data['total_amount'] ?? 0) - (float) ($data['paid_amount'] ?? 0));
        }
        if ($name === 'deboning_records') {
            $input = (float) ($data['input_weight_kg'] ?? 0);
            $data['yield_percent'] = $input > 0 ? round(((float) ($data['net_meat_weight_kg'] ?? 0) / $input) * 100, 3) : 0;
        }
        if ($name === 'yield_records') {
            $batchInput = 0.0;
            if (!empty($data['production_batch_id'])) {
                $statement = Database::connection()->prepare('SELECT input_weight_kg FROM production_batches WHERE id=?');
                $statement->execute([(int) $data['production_batch_id']]);
                $batchInput = (float) $statement->fetchColumn();
            }
            $data['carcass_yield_percent'] = $batchInput > 0 ? round(((float) ($data['carcass_weight_kg'] ?? 0) / $batchInput) * 100, 3) : 0;
            $saleable = (float) ($data['finished_weight_kg'] ?? 0) + (float) ($data['deboned_weight_kg'] ?? 0);
            $data['saleable_yield_percent'] = $batchInput > 0 ? round(($saleable / $batchInput) * 100, 3) : 0;
            $data['waste_percent'] = $batchInput > 0 ? round(((float) ($data['waste_weight_kg'] ?? 0) / $batchInput) * 100, 3) : 0;
            $data['variance_percent'] = round($data['saleable_yield_percent'] - (float) ($data['expected_yield_percent'] ?? 0), 3);
        }
        if ($name === 'operating_expenses') {
            $data['total_amount'] = round((float) ($data['taxable_amount'] ?? 0) + (float) ($data['gst_amount'] ?? 0), 2);
        }
        if ($name === 'production_batch_costs') {
            $data['amount'] = round((float) ($data['quantity'] ?? 0) * (float) ($data['unit_rate'] ?? 0), 2);
        }
        if ($name === 'employee_performance_reviews') {
            $scores = array_map(static fn (string $field): float => (float) ($data[$field] ?? 0), ['productivity_score','quality_score','attendance_score','behaviour_score']);
            $data['overall_score'] = round(array_sum($scores) / count($scores), 2);
        }
        if ($name === 'salary_advances') {
            $approved = min((float) ($data['amount'] ?? 0), (float) ($data['approved_amount'] ?? 0));
            $data['approved_amount'] = round(max(0, $approved), 2);
            $currentRecovered = 0.0;
            if (!empty($_POST['id'])) {
                $statement = Database::connection()->prepare('SELECT recovered_amount FROM salary_advances WHERE id=?');
                $statement->execute([(int) $_POST['id']]);
                $currentRecovered = (float) $statement->fetchColumn();
            }
            $data['balance_amount'] = round(max(0, $data['approved_amount'] - $currentRecovered), 2);
        }
        if ($name === 'partner_dividends') {
            $data['gross_amount'] = round((float) ($data['share_value_basis'] ?? 0) * (float) ($data['dividend_rate'] ?? 0) / 100, 2);
            $data['net_amount'] = round(max(0, $data['gross_amount'] - (float) ($data['tds_amount'] ?? 0)), 2);
        }
        if ($name === 'sales_targets' && !empty($data['employee_id']) && !empty($data['period_start']) && !empty($data['period_end'])) {
            $statement = Database::connection()->prepare("SELECT COALESCE(SUM(i.subtotal),0) FROM invoices i JOIN sales_orders so ON so.id=i.sales_order_id JOIN customers c ON c.id=so.customer_id WHERE COALESCE(so.sales_person_id,c.assigned_salesman_id)=? AND i.invoice_date BETWEEN ? AND ? AND i.status IN ('issued','partial','paid','overdue')");
            $statement->execute([(int) $data['employee_id'], $data['period_start'], $data['period_end']]);
            $data['achieved_amount'] = round((float) $statement->fetchColumn(), 2);
            $data['commission_amount'] = $data['achieved_amount'] >= (float) ($data['target_amount'] ?? 0)
                ? round($data['achieved_amount'] * (float) ($data['commission_rate'] ?? 0) / 100, 2) : 0.0;
            if (($data['status'] ?? '') === 'active' && date('Y-m-d') > $data['period_end']) {
                $data['status'] = $data['achieved_amount'] >= (float) ($data['target_amount'] ?? 0) ? 'achieved' : 'missed';
            } elseif (($data['status'] ?? '') === 'active' && $data['achieved_amount'] >= (float) ($data['target_amount'] ?? 0)) {
                $data['status'] = 'achieved';
            }
        }
        if ($name === 'employees') {
            if (!isset($data['overtime_rate']) || $data['overtime_rate'] === null || $data['overtime_rate'] === '') {
                $data['overtime_rate'] = 0.00;
            } else {
                $data['overtime_rate'] = round((float) $data['overtime_rate'], 2);
            }
            if (empty($data['plant_id'])) {
                $data['plant_id'] = (int) Database::connection()->query("SELECT id FROM plants WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn() ?: null;
            }
            if (!empty($data['department']) && empty($data['department_id'])) {
                $statement = Database::connection()->prepare("SELECT id FROM departments WHERE LOWER(TRIM(name)) = LOWER(TRIM(?)) LIMIT 1");
                $statement->execute([$data['department']]);
                $deptId = $statement->fetchColumn();
                if ($deptId) {
                    $data['department_id'] = (int) $deptId;
                }
            }
        }
        if ($name === 'products') {
            if (!isset($data['reorder_level']) || $data['reorder_level'] === null || $data['reorder_level'] === '') {
                $data['reorder_level'] = 0.000;
            } else {
                $data['reorder_level'] = round((float) $data['reorder_level'], 3);
            }
            if (!isset($data['standard_cost']) || $data['standard_cost'] === null || $data['standard_cost'] === '') {
                $data['standard_cost'] = 0.000;
            } else {
                $data['standard_cost'] = round((float) $data['standard_cost'], 3);
            }
            if (!isset($data['selling_price']) || $data['selling_price'] === null || $data['selling_price'] === '') {
                $data['selling_price'] = 0.00;
            } else {
                $data['selling_price'] = round((float) $data['selling_price'], 2);
            }
            if (!isset($data['live_bird_cost_factor']) || $data['live_bird_cost_factor'] === null || $data['live_bird_cost_factor'] === '') {
                $data['live_bird_cost_factor'] = 1.0000;
            }
            if (!isset($data['default_margin_percent']) || $data['default_margin_percent'] === null || $data['default_margin_percent'] === '') {
                $data['default_margin_percent'] = 0.000;
            }
            if (!empty($data['category']) && empty($data['category_id'])) {
                $statement = Database::connection()->prepare("SELECT id FROM item_categories WHERE category_type = ? LIMIT 1");
                $statement->execute([$data['category']]);
                $catId = $statement->fetchColumn();
                if ($catId) {
                    $data['category_id'] = (int) $catId;
                }
            }
        }
        if ($name === 'production_stage_measurements') {
            $decimalZeroFields = [
                'gross_weight_kg', 'net_weight_kg', 'blood_loss_kg', 'head_waste_kg',
                'liver_weight_kg', 'heart_weight_kg', 'gizzard_weight_kg',
                'defeathering_waste_kg', 'evisceration_waste_kg', 'graded_weight_kg',
                'peeled_skin_waste_kg', 'condemned_weight_kg', 'packed_weight_kg',
                'packaging_waste_kg'
            ];
            foreach ($decimalZeroFields as $field) {
                if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                    $data[$field] = 0.000;
                } else {
                    $data[$field] = round((float) $data[$field], 3);
                }
            }

            $intZeroFields = [
                'hanging_bird_count', 'stunned_bird_count', 'defeathered_bird_count',
                'condemned_bird_count', 'packed_bird_count', 'rejected_pack_count'
            ];
            foreach ($intZeroFields as $field) {
                if (!isset($data[$field]) || $data[$field] === null || $data[$field] === '') {
                    $data[$field] = 0;
                } else {
                    $data[$field] = (int) $data[$field];
                }
            }

            if (($data['stage'] ?? '') !== 'storage') {
                if (!isset($data['storage_door_open']) || $data['storage_door_open'] === null || $data['storage_door_open'] === '') {
                    $data['storage_door_open'] = 0;
                } else {
                    $data['storage_door_open'] = (int) $data['storage_door_open'];
                }
                if (!isset($data['door_open_seconds']) || $data['door_open_seconds'] === null || $data['door_open_seconds'] === '') {
                    $data['door_open_seconds'] = 0;
                } else {
                    $data['door_open_seconds'] = (int) $data['door_open_seconds'];
                }
            } else {
                if (isset($data['storage_door_open']) && $data['storage_door_open'] !== null && $data['storage_door_open'] !== '') {
                    $data['storage_door_open'] = (int) $data['storage_door_open'];
                }
                if (isset($data['door_open_seconds']) && $data['door_open_seconds'] !== null && $data['door_open_seconds'] !== '') {
                    $data['door_open_seconds'] = (int) $data['door_open_seconds'];
                }
            }

            $userId = (int) (Auth::user()['id'] ?? 0);
            if (empty($data['recorded_by']) && $userId > 0) {
                $data['recorded_by'] = $userId;
            }
        }
    }

    private function validateLookups(array $module, array $data): array
    {
        $errors = [];
        $pdo = Database::connection();
        $fields = $module['fields'] ?? [];

        foreach ($fields as $fieldName => $fieldConfig) {
            if (($fieldConfig['type'] ?? '') !== 'lookup') {
                continue;
            }

            $val = $data[$fieldName] ?? null;
            if ($val === null || $val === '' || (is_numeric($val) && (int) $val === 0)) {
                continue;
            }

            $lookup = $fieldConfig['lookup'] ?? [];
            $table = $lookup['table'] ?? null;
            $valCol = $lookup['value'] ?? 'id';
            $labelCol = $lookup['label'] ?? 'name';
            $fieldLabel = $fieldConfig['label'] ?? ucwords(str_replace(['_id', '_'], ['', ' '], (string) $fieldName));
            $where = $lookup['where'] ?? null;

            if (!$table || !preg_match('/^[a-z0-9_]+$/i', (string) $table)) {
                continue;
            }

            try {
                $stmt = $pdo->prepare("SELECT * FROM `{$table}` WHERE `{$valCol}` = ? LIMIT 1");
                $stmt->execute([$val]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$record) {
                    $errors[$fieldName] = "The selected {$fieldLabel} (ID: {$val}) does not exist or has been removed.";
                    continue;
                }

                if (!empty($where)) {
                    $stmtWhere = $pdo->prepare("SELECT 1 FROM `{$table}` WHERE `{$valCol}` = ? AND ({$where}) LIMIT 1");
                    $stmtWhere->execute([$val]);
                    if (!$stmtWhere->fetchColumn()) {
                        $displayVal = $record[$labelCol] ?? ("ID " . $val);
                        $statusStr = !empty($record['status']) ? " (status: '" . (string) $record['status'] . "')" : "";
                        $errors[$fieldName] = "The selected {$fieldLabel} '{$displayVal}'{$statusStr} is inactive, cancelled, or cannot be used.";
                    }
                }
            } catch (\Throwable $e) {
                // Allow DB engine constraint checks if query fails
            }
        }

        return $errors;
    }

    private function domainErrors(string $name, array $data): array
    {
        $errors = [];
        $nonNegative = '/(amount|weight|quantity|qty|count|percent|capacity|hours|minutes|cost|price|level|days|salary|rate|mortality|litres|odometer)/';
        foreach ($data as $field => $value) {
            if ($value !== null && is_numeric($value) && preg_match($nonNegative, $field) && (float) $value < 0) {
                $errors[$field] = 'This value cannot be negative.';
            }
        }
        if ($name === 'bird_receipts' && (float) ($data['mortality_count'] ?? 0) > (float) ($data['bird_count'] ?? 0)) {
            $errors['mortality_count'] = 'Mortality cannot exceed the received bird count.';
        }
        if ($name === 'bird_receipts' && (float) ($data['bird_count'] ?? 0) <= 0) {
            $errors['bird_count'] = 'Bird count must be greater than zero.';
        }
        if ($name === 'bird_receipts' && ((float)($data['received_quantity'] ?? 0) <= 0 || (float)($data['unit_purchase_cost'] ?? 0) < 0)) $errors['received_quantity']='Received quantity must be greater than zero and purchase cost cannot be negative.';
        if ($name === 'employees' && !empty($data['manager_id'])) {
            $employeeId = (int) ($_POST['id'] ?? 0);
            $managerId = (int) $data['manager_id'];
            if ($employeeId > 0 && $managerId === $employeeId) $errors['manager_id'] = 'An employee cannot report to themselves.';
            elseif ($employeeId > 0 && $this->managerCreatesCycle($employeeId, $managerId)) $errors['manager_id'] = 'This reporting line would create a management cycle.';
        }
        if ($name === 'production_damaged_birds' && (int) ($data['damaged_bird_count'] ?? 0) <= 0 && (float) ($data['damaged_weight_kg'] ?? 0) <= 0) {
            $errors['damaged_bird_count'] = 'Enter a damaged bird quantity or damaged weight greater than zero.';
        }
        if ($name === 'production_batches') {
            $input = (float) ($data['input_weight_kg'] ?? 0);
            if ($input <= 0) {
                $errors['input_weight_kg'] = 'Input weight must be greater than zero.';
            }
            if ((float) ($data['output_weight_kg'] ?? 0) + (float) ($data['rejected_weight_kg'] ?? 0) > $input) {
                $errors['output_weight_kg'] = 'Output and rejected weight cannot exceed input weight.';
            }
        }
        if ($name === 'quality_checks' && (float) ($data['accepted_qty'] ?? 0) + (float) ($data['rejected_qty'] ?? 0) > (float) ($data['sample_size'] ?? 0)) {
            $errors['accepted_qty'] = 'Accepted and rejected quantities cannot exceed the sample size.';
        }
        if ($name === 'quality_checks' && (float) ($data['sample_size'] ?? 0) <= 0) {
            $errors['sample_size'] = 'Sample size must be greater than zero.';
        }
        if ($name === 'inventory_lots') {
            if ((float) ($data['quantity'] ?? 0) <= 0) {
                $errors['quantity'] = 'Lot quantity must be greater than zero.';
            }
            if ((float) ($data['available_quantity'] ?? 0) > (float) ($data['quantity'] ?? 0)) {
                $errors['available_quantity'] = 'Available quantity cannot exceed the lot quantity.';
            }
            if (!empty($data['expiry_date']) && !empty($data['received_date']) && $data['expiry_date'] < $data['received_date']) {
                $errors['expiry_date'] = 'Expiry date cannot be before the received date.';
            }
        }
        if ($name === 'invoices') {
            if ((float) ($data['paid_amount'] ?? 0) > (float) ($data['total_amount'] ?? 0)) {
                $errors['paid_amount'] = 'Paid amount cannot exceed the invoice total.';
            }
            if (!empty($data['due_date']) && !empty($data['invoice_date']) && $data['due_date'] < $data['invoice_date']) {
                $errors['due_date'] = 'Due date cannot be before the invoice date.';
            }
            if (abs(((float) ($data['subtotal'] ?? 0) + (float) ($data['tax_amount'] ?? 0)) - (float) ($data['total_amount'] ?? 0)) > 0.01) {
                $errors['total_amount'] = 'Invoice total must equal subtotal plus GST.';
            }
        }
        if ($name === 'payments' && (float) ($data['amount'] ?? 0) <= 0) {
            $errors['amount'] = 'Payment amount must be greater than zero.';
        }
        if ($name === 'supplier_invoices') {
            if (!empty($data['due_date']) && !empty($data['invoice_date']) && $data['due_date'] < $data['invoice_date']) {
                $errors['due_date'] = 'Due date cannot be before the invoice date.';
            }
            if (abs(((float) ($data['subtotal'] ?? 0) + (float) ($data['tax_amount'] ?? 0)) - (float) ($data['total_amount'] ?? 0)) > 0.01) {
                $errors['total_amount'] = 'Supplier invoice total must equal subtotal plus input GST.';
            }
            if (!empty($data['goods_receipt_id'])) {
                $statement = Database::connection()->prepare("SELECT supplier_id,purchase_order_id,total_amount,status FROM goods_receipts WHERE id=?");
                $statement->execute([(int) $data['goods_receipt_id']]);
                $receipt = $statement->fetch();
                if (!$receipt || $receipt['status'] !== 'accepted') {
                    $errors['goods_receipt_id'] = 'Select an accepted goods receipt.';
                } elseif ((int) $receipt['supplier_id'] !== (int) ($data['supplier_id'] ?? 0)) {
                    $errors['supplier_id'] = 'Supplier must match the goods receipt.';
                } elseif (!empty($data['purchase_order_id']) && (int) $receipt['purchase_order_id'] !== (int) $data['purchase_order_id']) {
                    $errors['purchase_order_id'] = 'Purchase order must match the goods receipt.';
                } elseif (abs((float) $receipt['total_amount'] - (float) ($data['subtotal'] ?? 0)) > 0.01) {
                    $errors['subtotal'] = 'Subtotal must match the accepted receipt value.';
                }
            }
        }
        if ($name === 'supplier_payments') {
            if ((float) ($data['amount'] ?? 0) <= 0) {
                $errors['amount'] = 'Payment amount must be greater than zero.';
            }
            if (!empty($data['supplier_invoice_id'])) {
                $statement = Database::connection()->prepare('SELECT supplier_id,balance_amount,status FROM supplier_invoices WHERE id=?');
                $statement->execute([(int) $data['supplier_invoice_id']]);
                $invoice = $statement->fetch();
                if (!$invoice || !in_array($invoice['status'], ['approved', 'partial', 'overdue'], true)) {
                    $errors['supplier_invoice_id'] = 'Select an open approved supplier invoice.';
                } elseif ((int) $invoice['supplier_id'] !== (int) ($data['supplier_id'] ?? 0)) {
                    $errors['supplier_id'] = 'Supplier must match the invoice.';
                } elseif ((float) ($data['amount'] ?? 0) > (float) $invoice['balance_amount'] + 0.001) {
                    $errors['amount'] = 'Payment cannot exceed the supplier invoice balance.';
                }
            }
        }
        if ($name === 'sales_orders' && !empty($data['delivery_date']) && !empty($data['order_date']) && $data['delivery_date'] < $data['order_date']) {
            $errors['delivery_date'] = 'Delivery date cannot be before the order date.';
        }
        if ($name === 'sales_orders' && abs(((float) ($data['subtotal'] ?? 0) - (float) ($data['discount_amount'] ?? 0) + (float) ($data['tax_amount'] ?? 0)) - (float) ($data['total_amount'] ?? 0)) > 0.01) {
            $errors['total_amount'] = 'Order total must equal subtotal minus discount plus GST.';
        }
        if ($name === 'sales_quotations') {
            if (!empty($data['valid_until']) && !empty($data['quote_date']) && $data['valid_until'] < $data['quote_date']) {
                $errors['valid_until'] = 'Validity date cannot be before the quote date.';
            }
            if (abs(((float) ($data['subtotal'] ?? 0) - (float) ($data['discount_amount'] ?? 0) + (float) ($data['tax_amount'] ?? 0)) - (float) ($data['total_amount'] ?? 0)) > 0.01) {
                $errors['total_amount'] = 'Quotation total must equal subtotal minus discount plus GST.';
            }
        }
        if ($name === 'sales_quotation_items') {
            $gross=(float)($data['quantity'] ?? 0)*(float)($data['unit_price'] ?? 0);
            if ((float)($data['quantity'] ?? 0)<=0) $errors['quantity']='Quotation quantity must be greater than zero.';
            if ((float)($data['discount_amount'] ?? 0)>$gross) $errors['discount_amount']='Discount cannot exceed the gross line value.';
        }
        if ($name === 'rate_cards' && !empty($data['valid_to']) && !empty($data['valid_from']) && $data['valid_to']<$data['valid_from']) $errors['valid_to']='Valid-to date cannot be before valid-from date.';
        if ($name === 'rate_card_items' && ((float)($data['live_bird_cost_factor'] ?? 0)<=0 || (float)($data['margin_percent'] ?? 0)<0)) $errors['live_bird_cost_factor']='Cost factor must be positive and margin cannot be negative.';
        if ($name === 'sales_targets' && !empty($data['period_end']) && !empty($data['period_start']) && $data['period_end'] < $data['period_start']) {
            $errors['period_end'] = 'Target end date cannot be before the start date.';
        }
        if ($name === 'leave_requests') {
            if (!empty($data['end_date']) && !empty($data['start_date']) && $data['end_date'] < $data['start_date']) {
                $errors['end_date'] = 'Leave end date cannot be before the start date.';
            }
            if ((float) ($data['days'] ?? 0) <= 0) $errors['days'] = 'Leave days must be greater than zero.';
        }
        if ($name === 'fuel_logs' && (float) ($data['litres'] ?? 0) <= 0) {
            $errors['litres'] = 'Fuel litres must be greater than zero.';
        }
        if ($name === 'compliance_records' && !empty($data['expiry_date']) && !empty($data['issue_date']) && $data['expiry_date'] < $data['issue_date']) {
            $errors['expiry_date'] = 'Expiry date cannot be before the issue date.';
        }
        if ($name === 'purchase_orders' && !empty($data['expected_date']) && !empty($data['order_date']) && $data['expected_date'] < $data['order_date']) {
            $errors['expected_date'] = 'Expected date cannot be before the order date.';
        }
        if ($name === 'purchase_requisitions' && !empty($data['required_date']) && !empty($data['request_date']) && $data['required_date'] < $data['request_date']) {
            $errors['required_date'] = 'Required date cannot be before the request date.';
        }
        if ($name === 'deboning_records') {
            $input = (float) ($data['input_weight_kg'] ?? 0);
            $disposed = (float) ($data['bone_weight_kg'] ?? 0) + (float) ($data['net_meat_weight_kg'] ?? 0) + (float) ($data['waste_weight_kg'] ?? 0);
            if ($input <= 0) $errors['input_weight_kg'] = 'Input weight must be greater than zero.';
            if ($disposed > $input + 0.001) $errors['net_meat_weight_kg'] = 'Bone, meat, and waste weights cannot exceed input weight.';
        }
        if ($name === 'stock_transfers') {
            if ((int) ($data['from_zone_id'] ?? 0) === (int) ($data['to_zone_id'] ?? 0)) $errors['to_zone_id'] = 'Destination zone must be different from the source zone.';
            if ((float) ($data['quantity'] ?? 0) <= 0) $errors['quantity'] = 'Transfer quantity must be greater than zero.';
            if (!empty($data['inventory_lot_id'])) {
                $statement = Database::connection()->prepare('SELECT storage_zone_id,available_quantity FROM inventory_lots WHERE id=?');
                $statement->execute([(int) $data['inventory_lot_id']]);
                $lot = $statement->fetch();
                if (!$lot || (int) $lot['storage_zone_id'] !== (int) ($data['from_zone_id'] ?? 0)) $errors['from_zone_id'] = 'Source zone must match the selected lot location.';
                elseif ((float) $data['quantity'] > (float) $lot['available_quantity'] + 0.001) $errors['quantity'] = 'Transfer quantity exceeds available lot stock.';
            }
        }
        if ($name === 'quality_holds' && empty($data['production_batch_id']) && empty($data['inventory_lot_id'])) $errors['production_batch_id'] = 'Link the hold to a production batch or inventory lot.';
        if ($name === 'customer_prices' && !empty($data['valid_to']) && !empty($data['valid_from']) && $data['valid_to'] < $data['valid_from']) $errors['valid_to'] = 'Valid-to date cannot be before valid-from date.';
        if ($name === 'shareholders' && ((float) ($data['ownership_percent'] ?? 0) < 0 || (float) ($data['ownership_percent'] ?? 0) > 100)) $errors['ownership_percent'] = 'Ownership must be between 0 and 100 percent.';
        if ($name === 'shareholders' && !isset($errors['ownership_percent']) && ($data['status'] ?? 'active') === 'active') {
            $statement = Database::connection()->prepare("SELECT COALESCE(SUM(ownership_percent),0) FROM shareholders WHERE company_id=? AND status='active' AND id<>?");
            $statement->execute([(int) ($data['company_id'] ?? 0), (int) ($_POST['id'] ?? 0)]);
            if ((float) $statement->fetchColumn() + (float) ($data['ownership_percent'] ?? 0) > 100.0001) $errors['ownership_percent'] = 'Active partner ownership cannot exceed 100 percent in total.';
        }
        if ($name === 'shareholders' && ($data['shareholder_type'] ?? '')==='partner' && empty($data['director_id'])) $errors['director_id']='A partner must be grouped under a director.';
        if ($name === 'shareholders' && (float)($data['received_share'] ?? 0)>(float)($data['share_amount'] ?? 0)) $errors['received_share']='Received share amount cannot exceed the total share amount.';
        if ($name === 'shareholders' && !empty($_POST['nominee_name'])) {
            if (empty($_POST['nominee_relationship'])) {
                $errors['nominee_relationship'] = 'Please specify the nominee relationship.';
            }
            if (!empty($_POST['nominee_minor']) && empty($_POST['nominee_guardian'])) {
                $errors['nominee_guardian'] = 'Guardian name is required when the nominee is a minor.';
            }
        }
        if ($name === 'sales_returns' && (float)($data['quantity_received'] ?? 0)<=0) $errors['quantity_received']='Quantity received must be greater than zero.';
        if ($name === 'carcass_allotments' && ((float) ($data['quantity'] ?? 0) <= 0 || (float) ($data['weight_kg'] ?? 0) <= 0)) $errors['quantity'] = 'Allocation quantity and weight must be greater than zero.';
        if ($name === 'packaging_records' && ((int) ($data['package_count'] ?? 0) <= 0 || (float) ($data['total_weight_kg'] ?? 0) <= 0)) $errors['package_count'] = 'Package count and total weight must be greater than zero.';
        if ($name === 'operating_expenses' && (float) ($data['total_amount'] ?? 0) <= 0) $errors['taxable_amount'] = 'Expense total must be greater than zero.';
        if ($name === 'production_batch_costs' && ((float) ($data['quantity'] ?? 0) <= 0 || (float) ($data['amount'] ?? 0) <= 0)) $errors['quantity'] = 'Cost quantity and amount must be greater than zero.';
        if ($name === 'employee_performance_reviews') {
            foreach (['productivity_score','quality_score','attendance_score','behaviour_score'] as $score) {
                if ((float) ($data[$score] ?? 0) < 0 || (float) ($data[$score] ?? 0) > 100) $errors[$score] = 'Performance scores must be between 0 and 100.';
            }
            if (!empty($data['review_period_start']) && !empty($data['review_period_end']) && $data['review_period_end'] < $data['review_period_start']) $errors['review_period_end'] = 'Review end date cannot be before the start date.';
        }
        if ($name === 'salary_advances') {
            if ((float) ($data['amount'] ?? 0) <= 0) $errors['amount'] = 'Requested advance must be greater than zero.';
            if ((float) ($data['approved_amount'] ?? 0) > (float) ($data['amount'] ?? 0)) $errors['approved_amount'] = 'Approved amount cannot exceed the request.';
            if ((float) ($data['approved_amount'] ?? 0) > 0 && (float) ($data['monthly_recovery'] ?? 0) <= 0) $errors['monthly_recovery'] = 'Enter a monthly recovery amount for an approved advance.';
        }
        if ($name === 'partner_dividends') {
            if ((float) ($data['share_value_basis'] ?? 0) <= 0 || (float) ($data['dividend_rate'] ?? 0) <= 0) $errors['share_value_basis'] = 'Share basis and dividend rate must be greater than zero.';
            if ((float) ($data['tds_amount'] ?? 0) > (float) ($data['gross_amount'] ?? 0)) $errors['tds_amount'] = 'TDS cannot exceed the gross dividend.';
        }
        if ($name === 'partner_transactions' && (float) ($data['amount'] ?? 0) <= 0) $errors['amount'] = 'Transaction amount must be greater than zero.';
        if ($name === 'shareholder_nominees') {
            $allocation = (float) ($data['allocation_percent'] ?? 0);
            if ($allocation <= 0 || $allocation > 100) $errors['allocation_percent'] = 'Nominee allocation must be greater than 0 and at most 100 percent.';
            if (!isset($errors['allocation_percent']) && ($data['status'] ?? 'active') === 'active' && !empty($data['shareholder_id'])) {
                $statement = Database::connection()->prepare("SELECT COALESCE(SUM(allocation_percent),0) FROM shareholder_nominees WHERE shareholder_id=? AND status='active' AND id<>?");
                $statement->execute([(int) $data['shareholder_id'], (int) ($_POST['id'] ?? 0)]);
                if ((float) $statement->fetchColumn() + $allocation > 100.0001) $errors['allocation_percent'] = 'Active nominee allocations cannot exceed 100 percent for a partner.';
            }
            if (!empty($data['is_minor']) && empty($data['guardian_name'])) $errors['guardian_name'] = 'A guardian is required for a minor nominee.';
        }
        if ($name === 'production_stage_measurements') {
            if (!empty($data['production_batch_id'])) {
                $statement = Database::connection()->prepare("SELECT id, status, batch_number FROM production_batches WHERE id=?");
                $statement->execute([(int) $data['production_batch_id']]);
                $batch = $statement->fetch();
                if (!$batch) {
                    $errors['production_batch_id'] = 'The selected production batch does not exist or has been removed.';
                } elseif ($batch['status'] === 'cancelled') {
                    $errors['production_batch_id'] = 'Stage measurements cannot be recorded for a cancelled batch (' . $batch['batch_number'] . ').';
                }
            }
            if ((float) ($data['net_weight_kg'] ?? 0) > (float) ($data['gross_weight_kg'] ?? 0) && (float) ($data['gross_weight_kg'] ?? 0) > 0) $errors['net_weight_kg'] = 'Net weight cannot exceed gross weight.';
            if ((int) ($data['stunned_bird_count'] ?? 0) > (int) ($data['hanging_bird_count'] ?? 0) && (int) ($data['hanging_bird_count'] ?? 0) > 0) $errors['stunned_bird_count'] = 'Stunned bird count cannot exceed hanging count.';
            if ((float) ($data['water_level_percent'] ?? 0) > 100) $errors['water_level_percent'] = 'Water level cannot exceed 100 percent.';
            if (($data['stage'] ?? '') === 'scalding' && (($data['scalding_temperature_c'] ?? null) === null || (float) $data['scalding_temperature_c'] < 40 || (float) $data['scalding_temperature_c'] > 80)) $errors['scalding_temperature_c'] = 'Record a scalding temperature between 40C and 80C.';
            if (($data['stage'] ?? '') === 'storage' && ($data['storage_temperature_c'] ?? null) === null) $errors['storage_temperature_c'] = 'Storage temperature is required at the storage stage.';
            if (($data['stage'] ?? '') === 'storage' && ($data['storage_door_open'] ?? null) === null) $errors['storage_door_open'] = 'Record whether the storage door was opened.';
            if (($data['stage'] ?? '') === 'storage' && !empty($data['storage_door_open']) && (int) ($data['door_open_seconds'] ?? 0) <= 0) $errors['door_open_seconds'] = 'Record the door-open duration in seconds.';
            if (($data['stage'] ?? '') === 'defeathering' && !empty($data['production_batch_id']) && (int) ($data['defeathered_bird_count'] ?? 0) > 0) {
                $statement = Database::connection()->prepare('SELECT birds_input FROM production_batches WHERE id=?');
                $statement->execute([(int) $data['production_batch_id']]);
                if ((int) $data['defeathered_bird_count'] > (int) $statement->fetchColumn()) $errors['defeathered_bird_count'] = 'Defeathered bird count cannot exceed the batch bird input.';
            }
            if (($data['stage'] ?? '') === 'packing') {
                if ((int) ($data['packed_bird_count'] ?? 0) <= 0 && (float) ($data['packed_weight_kg'] ?? 0) <= 0) $errors['packed_bird_count'] = 'Record a packed bird count or packed weight.';
                if ((int) ($data['rejected_pack_count'] ?? 0) > (int) ($data['packed_bird_count'] ?? 0) && (int) ($data['packed_bird_count'] ?? 0) > 0) $errors['rejected_pack_count'] = 'Rejected packs cannot exceed the packed bird count.';
                if ((float) ($data['packed_weight_kg'] ?? 0) > (float) ($data['gross_weight_kg'] ?? 0) && (float) ($data['gross_weight_kg'] ?? 0) > 0) $errors['packed_weight_kg'] = 'Packed weight cannot exceed gross weight.';
            }
        }
        if ($name === 'vehicle_gate_logs') {
            if (!empty($data['exit_at']) && !empty($data['entry_at']) && $data['exit_at'] < $data['entry_at']) $errors['exit_at'] = 'Exit time cannot be before entry time.';
            if ((float) ($data['odometer_out'] ?? 0) > 0 && (float) ($data['odometer_out'] ?? 0) < (float) ($data['odometer_in'] ?? 0)) $errors['odometer_out'] = 'Odometer out cannot be lower than odometer in.';
        }
        if ($name === 'eway_bills' && !empty($data['valid_until']) && !empty($data['document_date']) && substr((string) $data['valid_until'], 0, 10) < $data['document_date']) $errors['valid_until'] = 'E-Way bill validity cannot end before the document date.';
        if ($name === 'company_certificates' && !empty($data['expiry_date']) && !empty($data['issue_date']) && $data['expiry_date'] < $data['issue_date']) $errors['expiry_date'] = 'Certificate expiry cannot be before issue date.';
        if ($name === 'roc_filings' && !empty($data['filed_date']) && $data['filed_date'] > date('Y-m-d')) $errors['filed_date'] = 'Filed date cannot be in the future.';
        if ($name === 'government_loans') {
            if ((float) ($data['disbursed_amount'] ?? 0) > (float) ($data['sanctioned_amount'] ?? 0)) $errors['disbursed_amount'] = 'Disbursed amount cannot exceed the sanctioned amount.';
            if ((float) ($data['outstanding_amount'] ?? 0) > (float) ($data['disbursed_amount'] ?? 0)) $errors['outstanding_amount'] = 'Outstanding amount cannot exceed the disbursed amount.';
        }
        if ($name === 'assets' && (float) ($data['residual_value'] ?? 0) > (float) ($data['acquisition_cost'] ?? 0)) $errors['residual_value'] = 'Residual value cannot exceed acquisition cost.';
        if ($name === 'calendar_events' && !empty($data['end_at']) && !empty($data['start_at']) && $data['end_at'] < $data['start_at']) $errors['end_at'] = 'Event end cannot be before its start.';
        if ($name === 'employee_resignations' && !empty($data['requested_last_date']) && !empty($data['submitted_date']) && $data['requested_last_date'] < $data['submitted_date']) $errors['requested_last_date'] = 'Last working date cannot be before the submission date.';
        return $errors;
    }

    private function addOwnershipFields(string $table, array &$data, bool $creating): void
    {
        if (!$creating) {
            return;
        }
        $columns = Database::connection()->query('SHOW COLUMNS FROM `' . $table . '`')->fetchAll(PDO::FETCH_COLUMN);
        $userId = (int) (Auth::user()['id'] ?? 0);
        foreach (['created_by', 'requested_by', 'received_by', 'checked_by', 'paid_by', 'held_by', 'allotted_by', 'operator_id', 'uploaded_by', 'recorded_by', 'issued_by', 'reviewed_by', 'owner_id', 'submitted_by_user_id'] as $column) {
            if (in_array($column, $columns, true) && !array_key_exists($column, $data)) {
                $data[$column] = $userId;
            }
        }
    }

    private function applySideEffects(string $name, int $id, ?array $old, array $data): void
    {
        $pdo = Database::connection();
        if ($name === 'payments') {
            $invoiceIds = array_unique(array_filter([(int) ($data['invoice_id'] ?? 0), (int) ($old['invoice_id'] ?? 0)]));
            foreach ($invoiceIds as $invoiceId) {
                $statement = $pdo->prepare("UPDATE invoices i SET paid_amount=(SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.invoice_id=i.id AND p.status='cleared'), balance_amount=GREATEST(0,total_amount-(SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.invoice_id=i.id AND p.status='cleared')), status=CASE WHEN total_amount <= (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.invoice_id=i.id AND p.status='cleared') THEN 'paid' WHEN (SELECT COALESCE(SUM(p.amount),0) FROM payments p WHERE p.invoice_id=i.id AND p.status='cleared') > 0 THEN 'partial' ELSE 'issued' END WHERE i.id=?");
                $statement->execute([$invoiceId]);
            }
            $customerIds = array_unique(array_filter([(int) ($data['customer_id'] ?? 0), (int) ($old['customer_id'] ?? 0)]));
            foreach ($customerIds as $customerId) {
                $pdo->prepare("UPDATE customers c SET outstanding_balance=(SELECT COALESCE(SUM(balance_amount),0) FROM invoices WHERE customer_id=c.id AND status IN ('issued','partial','overdue')) WHERE c.id=?")->execute([$customerId]);
            }
        }
        if ($name === 'invoices') {
            $customerIds = array_unique(array_filter([(int) ($data['customer_id'] ?? 0), (int) ($old['customer_id'] ?? 0)]));
            foreach ($customerIds as $customerId) {
                $pdo->prepare("UPDATE customers c SET outstanding_balance=(SELECT COALESCE(SUM(balance_amount),0) FROM invoices WHERE customer_id=c.id AND status IN ('issued','partial','overdue')) WHERE c.id=?")->execute([$customerId]);
            }
        }
        if ($name === 'inventory_lots' && !$old) {
            $pdo->prepare("INSERT INTO stock_movements (inventory_lot_id,movement_type,quantity,to_zone_id,reference_type,reference_id,reason,moved_by,moved_at) VALUES (?,'receipt',?,?,'inventory_lot',?,'Initial lot receipt',?,NOW())")
                ->execute([$id, $data['quantity'], $data['storage_zone_id'], $id, Auth::user()['id']]);
        }
        if ($name === 'sales_quotation_items') {
            foreach (array_unique(array_filter([(int)($data['sales_quotation_id'] ?? 0),(int)($old['sales_quotation_id'] ?? 0)])) as $quotationId) {
                $this->recalculateQuotation($quotationId);
            }
        }
        if ($name === 'customer_bank_accounts' && !empty($data['is_primary'])) $pdo->prepare('UPDATE customer_bank_accounts SET is_primary=0 WHERE customer_id=? AND id<>?')->execute([$data['customer_id'],$id]);
        if ($name === 'company_bank_accounts') {
            if (!$old) $pdo->prepare('UPDATE company_bank_accounts SET current_balance=opening_balance WHERE id=?')->execute([$id]);
            if (!empty($data['is_primary'])) $pdo->prepare('UPDATE company_bank_accounts SET is_primary=0 WHERE company_id=? AND id<>?')->execute([$data['company_id'],$id]);
        }
        if ($name === 'employee_bank_accounts' && !empty($data['is_salary_account'])) $pdo->prepare('UPDATE employee_bank_accounts SET is_salary_account=0 WHERE employee_id=? AND id<>?')->execute([$data['employee_id'],$id]);
        if ($name === 'employee_performance_reviews') {
            $periodEnd = (string) ($data['review_period_end'] ?? $old['review_period_end'] ?? '');
            if ($periodEnd !== '') {
                $pdo->prepare('SET @performance_rank := 0')->execute();
                $statement = $pdo->prepare('UPDATE employee_performance_reviews r JOIN (SELECT id,(@performance_rank := @performance_rank + 1) rank_value FROM employee_performance_reviews WHERE review_period_end=? AND status<>\'draft\' ORDER BY overall_score DESC,id) ranked ON ranked.id=r.id SET r.rank_number=ranked.rank_value');
                $statement->execute([$periodEnd]);
            }
        }
        if ($name === 'sales_targets') {
            $status = ($data['status'] ?? '') === 'cancelled' ? 'reversed' : 'calculated';
            $statement = $pdo->prepare("INSERT INTO sales_commission_accruals (target_id,employee_id,period_start,period_end,eligible_sales,commission_rate,commission_amount,status,calculated_at) VALUES (?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE employee_id=VALUES(employee_id),period_start=VALUES(period_start),period_end=VALUES(period_end),eligible_sales=VALUES(eligible_sales),commission_rate=VALUES(commission_rate),commission_amount=VALUES(commission_amount),status=IF(status='included',status,VALUES(status)),calculated_at=NOW()");
            $statement->execute([$id,$data['employee_id'],$data['period_start'],$data['period_end'],$data['achieved_amount'],$data['commission_rate'],$data['commission_amount'],$status]);
        }
        if ($name === 'production_stage_measurements') {
            $reference = (string) ($data['measurement_number'] ?? ('measurement-' . $id));
            $alerts = [];
            $doorThreshold = (int) round((float) $pdo->query("SELECT COALESCE((SELECT setting_value FROM settings WHERE setting_key='storage_door_alert_minutes'),2)")->fetchColumn() * 60);
            if (!empty($data['storage_door_open']) && (int) ($data['door_open_seconds'] ?? 0) >= $doorThreshold) $alerts[] = ['warning','Storage door open','Production storage door remained open beyond the controlled duration of ' . number_format($doorThreshold / 60, 1) . ' minute(s).'];
            if (($data['storage_temperature_c'] ?? null) !== null && ((float) $data['storage_temperature_c'] < -2 || (float) $data['storage_temperature_c'] > 5)) $alerts[] = ['critical','Storage temperature deviation','Storage temperature is outside the -2C to 5C operational range.'];
            if (($data['screw_chiller_temperature_c'] ?? null) !== null && ((float) $data['screw_chiller_temperature_c'] < -1 || (float) $data['screw_chiller_temperature_c'] > 5)) $alerts[] = ['critical','Chiller temperature deviation','Screw chiller temperature is outside the -1C to 5C operational range.'];
            if (($data['scalding_temperature_c'] ?? null) !== null && ((float) $data['scalding_temperature_c'] < 50 || (float) $data['scalding_temperature_c'] > 65)) $alerts[] = ['warning','Scalding temperature deviation','Scalding temperature is outside the configured operational range.'];
            if (($data['water_level_percent'] ?? null) !== null && (float) $data['water_level_percent'] < 25) $alerts[] = ['warning','Low process-water level','Process water level is below 25 percent.'];
            if ((int) ($data['condemned_bird_count'] ?? 0) > 0) $alerts[] = ['critical','Condemned birds recorded',(int) $data['condemned_bird_count'] . ' condemned birds require Quality review.'];
            foreach ($alerts as [$severity,$title,$message]) {
                $exists = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE source_type='production_measurement' AND source_reference=? AND title=? AND status='open'");
                $exists->execute([$reference,$title]);
                if (!(int) $exists->fetchColumn()) $pdo->prepare("INSERT INTO alerts (severity,title,message,source_type,source_reference,triggered_at,status) VALUES (?,?,?,'production_measurement',?,NOW(),'open')")->execute([$severity,$title,$message,$reference]);
            }
        }
        if ($name === 'shareholders') {
            $nomineeName = trim((string) ($_POST['nominee_name'] ?? ''));
            if ($nomineeName !== '') {
                $nomineeId = (int) ($_POST['nominee_id'] ?? 0);
                $rel = trim((string) ($_POST['nominee_relationship'] ?? '')) ?: 'Nominee';
                $phone = trim((string) ($_POST['nominee_phone'] ?? '')) ?: null;
                $aadhaar = trim((string) ($_POST['nominee_aadhaar'] ?? '')) ?: null;
                $dob = !empty($_POST['nominee_dob']) ? (string) $_POST['nominee_dob'] : null;
                $alloc = (float) ($_POST['nominee_allocation'] ?? 100);
                $isMinor = !empty($_POST['nominee_minor']) ? 1 : 0;
                $guardian = $isMinor ? (trim((string) ($_POST['nominee_guardian'] ?? '')) ?: null) : null;
                $status = in_array($_POST['nominee_status'] ?? 'active', ['active', 'inactive'], true) ? (string) $_POST['nominee_status'] : 'active';

                if ($nomineeId > 0) {
                    $check = $pdo->prepare("SELECT id FROM shareholder_nominees WHERE id=? AND shareholder_id=?");
                    $check->execute([$nomineeId, $id]);
                    if ($check->fetchColumn()) {
                        $upd = $pdo->prepare("UPDATE shareholder_nominees SET nominee_name=?, relationship=?, date_of_birth=?, phone=?, aadhaar_number=?, allocation_percent=?, is_minor=?, guardian_name=?, status=? WHERE id=? AND shareholder_id=?");
                        $upd->execute([$nomineeName, $rel, $dob, $phone, $aadhaar, $alloc, $isMinor, $guardian, $status, $nomineeId, $id]);
                    }
                } else {
                    $existing = $pdo->prepare("SELECT id FROM shareholder_nominees WHERE shareholder_id=? ORDER BY id ASC LIMIT 1");
                    $existing->execute([$id]);
                    $exId = $existing->fetchColumn();
                    if ($exId) {
                        $upd = $pdo->prepare("UPDATE shareholder_nominees SET nominee_name=?, relationship=?, date_of_birth=?, phone=?, aadhaar_number=?, allocation_percent=?, is_minor=?, guardian_name=?, status=? WHERE id=?");
                        $upd->execute([$nomineeName, $rel, $dob, $phone, $aadhaar, $alloc, $isMinor, $guardian, $status, $exId]);
                    } else {
                        $ins = $pdo->prepare("INSERT INTO shareholder_nominees (shareholder_id, nominee_name, relationship, date_of_birth, phone, aadhaar_number, allocation_percent, is_minor, guardian_name, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $ins->execute([$id, $nomineeName, $rel, $dob, $phone, $aadhaar, $alloc, $isMinor, $guardian, $status]);
                    }
                }
            }
        }
    }

    private function assertDraftMutation(string $name, ?array $old): void
    {
        if (!$old) {
            return;
        }
        $editable = [
            'purchase_orders' => ['draft'],
            'goods_receipts' => ['draft'],
            'bird_receipts' => ['quarantine', 'accepted'],
            'production_batches' => ['scheduled'],
            'sales_orders' => ['draft'],
            'payments' => ['pending'],
            'supplier_invoices' => ['draft'],
            'supplier_payments' => ['pending'],
            'dispatches' => ['scheduled'],
            'operating_expenses' => ['draft'],
            'partner_transactions' => ['draft'],
            'partner_dividends' => ['declared'],
            'salary_advances' => ['requested'],
        ];
        if (isset($editable[$name]) && !in_array((string) ($old['status'] ?? ''), $editable[$name], true)) {
            throw new \RuntimeException('Posted or active workflow records are immutable. Use the available controlled transition.', 422);
        }
    }

    private function recalculateQuotation(int $quotationId): void
    {
        Database::connection()->prepare("UPDATE sales_quotations q SET subtotal=(SELECT COALESCE(SUM(quantity*unit_price),0) FROM sales_quotation_items WHERE sales_quotation_id=q.id),discount_amount=(SELECT COALESCE(SUM(discount_amount),0) FROM sales_quotation_items WHERE sales_quotation_id=q.id),tax_amount=(SELECT COALESCE(SUM(tax_amount),0) FROM sales_quotation_items WHERE sales_quotation_id=q.id),total_amount=(SELECT COALESCE(SUM(line_total),0) FROM sales_quotation_items WHERE sales_quotation_id=q.id) WHERE q.id=?")->execute([$quotationId]);
    }

    /** @return false|list<string>|null */
    private function deletionStatuses(string $name): false|array|null
    {
        $policy=[
            'suppliers'=>['active','inactive'],'customers'=>['active','inactive'],'products'=>['active','inactive'],'sales_quotations'=>['draft','rejected','expired'],
            'sales_quotation_items'=>null,'sales_targets'=>['active','cancelled'],'farms'=>['active','inactive'],'item_categories'=>['active','inactive'],'warehouses'=>['active','inactive'],
            'purchase_requisitions'=>['draft','rejected','cancelled'],'packaging_specs'=>['active','inactive'],'customer_prices'=>['active','inactive'],
            'rate_cards'=>['draft','active','inactive','expired'],'rate_card_items'=>null,'customer_bank_accounts'=>['pending','active','blocked','inactive'],
            'market_prices'=>null,'shareholders'=>['active','inactive'],'report_schedules'=>['active','paused','inactive'],'route_masters'=>['active','inactive'],
            'cost_centers'=>['active','inactive'],'company_bank_accounts'=>['active','inactive','blocked'],'operating_expenses'=>['draft','cancelled'],
            'employee_bank_accounts'=>['active','inactive','blocked'],'employee_benefits'=>['active','inactive','closed'],'employee_uniform_allocations'=>null,
            'employee_memos'=>['draft','cancelled'],'employee_appointments'=>['draft','cancelled'],'employee_performance_reviews'=>['draft'],'salary_advances'=>['requested','rejected','cancelled'],
            'employee_resignations'=>['submitted','rejected','withdrawn'],'production_stage_measurements'=>null,'production_damaged_birds'=>['reported'],'production_batch_costs'=>['estimated','reversed'],
            'shareholder_nominees'=>['active','inactive'],'partner_transactions'=>['draft','cancelled'],'partner_dividends'=>['declared','cancelled'],
            'legal_cases'=>['open','closed'],'roc_filings'=>['planned','rejected'],'company_certificates'=>['active','expired','suspended'],'government_loans'=>['applied','rejected','closed'],
            'office_file_register'=>['open','closed','archived'],'calendar_events'=>['planned','cancelled'],'eway_bills'=>['draft','cancelled'],'vehicle_gate_logs'=>['inside','exited','denied'],
        ];
        return array_key_exists($name,$policy) ? $policy[$name] : false;
    }

    private function managerCreatesCycle(int $employeeId, int $managerId): bool
    {
        $visited = [];
        $pdo = Database::connection();
        while ($managerId > 0 && !isset($visited[$managerId])) {
            if ($managerId === $employeeId) return true;
            $visited[$managerId] = true;
            $statement = $pdo->prepare('SELECT manager_id FROM employees WHERE id=?');
            $statement->execute([$managerId]);
            $managerId = (int) $statement->fetchColumn();
        }
        return false;
    }

    private function parseDatabaseException(PDOException $exception, array $module, array $postData): array
    {
        $code = (string) $exception->getCode();
        $errorInfo = $exception->errorInfo ?? [];
        $driverCode = (int) ($errorInfo[1] ?? 0);
        $detail = (string) ($errorInfo[2] ?? $exception->getMessage());
        $fields = $module['fields'] ?? [];
        $singular = $module['singular'] ?? 'Record';

        $fieldErrors = [];
        $flashMessage = '';

        // 1. MySQL Error 1062: Duplicate entry
        if ($driverCode === 1062 || str_contains(strtolower($detail), 'duplicate entry')) {
            if (preg_match("/Duplicate entry '(?<value>[^']*)' for key '(?<key>[^']+)'/i", $detail, $matches)) {
                $duplicateValue = $matches['value'];
                $rawKey = $matches['key'];
                $keyParts = explode('.', $rawKey);
                $keyName = end($keyParts);

                $matchedField = null;
                // Direct match
                if (isset($fields[$keyName])) {
                    $matchedField = $keyName;
                }
                // Check if any field in postData directly contains the duplicate value
                if (!$matchedField) {
                    foreach (array_keys($fields) as $fieldName) {
                        if (isset($postData[$fieldName]) && (string) $postData[$fieldName] === (string) $duplicateValue) {
                            $matchedField = $fieldName;
                            break;
                        }
                    }
                }
                // Clean key match (strip idx_, uq_, table prefixes)
                if (!$matchedField) {
                    $cleanKey = preg_replace('/^(idx_|uq_|fk_|uniq_|unique_)/i', '', $keyName);
                    $tableName = (string) ($module['table'] ?? '');
                    if ($tableName) {
                        $cleanKey = preg_replace('/^' . preg_quote($tableName, '/') . '_?/i', '', $cleanKey);
                        $cleanKey = preg_replace('/^' . preg_quote(rtrim($tableName, 's'), '/') . '_?/i', '', $cleanKey);
                    }
                    if (isset($fields[$cleanKey])) {
                        $matchedField = $cleanKey;
                    } else {
                        foreach (array_keys($fields) as $fieldName) {
                            $normField = str_replace('_', '', (string) $fieldName);
                            $normKey = str_replace(['_', 'idx', 'uq', 'uniq', 'unique', 'fk'], '', (string) $keyName);
                            if (str_contains($keyName, $fieldName) || str_contains($normKey, $normField) || (strlen($normKey) >= 4 && str_contains($normField, $normKey))) {
                                $matchedField = $fieldName;
                                break;
                            }
                        }
                    }
                }

                if ($matchedField) {
                    $label = $fields[$matchedField]['label'] ?? ucwords(str_replace('_', ' ', $matchedField));
                    $fieldErrors[$matchedField] = "The {$label} '{$duplicateValue}' is already registered to another record.";
                    $flashMessage = "Duplicate {$label}: '{$duplicateValue}' is already in use. Please enter a unique value.";
                } else {
                    $fieldErrors['database'] = "A record with value '{$duplicateValue}' already exists for key '{$keyName}'.";
                    $flashMessage = "Duplicate value '{$duplicateValue}' conflicts with an existing {$singular}.";
                }
            } else {
                $fieldErrors['database'] = 'A record already uses one of these unique values. Please check for duplicates.';
                $flashMessage = 'A duplicate entry exists. Please verify unique fields like numbers, email, or code.';
            }
        }
        // 2. MySQL Error 1452: Foreign key constraint fails on insert/update
        elseif ($driverCode === 1452 || str_contains(strtolower($detail), 'foreign key constraint fails')) {
            $col = null;
            $tbl = null;

            if (preg_match("/(?:CONSTRAINT\s+[`\"]?(?<fk>[^`\" ]+)[`\"]?\s+)?FOREIGN KEY\s*\([`\"]?(?<col>[^`\" ]+)[`\"]?\)\s*REFERENCES\s*[`\"]?(?<tbl>[^`\" ]+)[`\"]?/i", $detail, $matches)) {
                $col = $matches['col'];
                $tbl = $matches['tbl'] ?? null;
            } elseif (preg_match("/REFERENCES\s+[`\"]?(?<tbl>[^`\" ]+)[`\"]?\s*\([`\"]?(?<col>[^`\" ]+)[`\"]?\)/i", $detail, $matches)) {
                $tbl = $matches['tbl'];
                $col = $matches['col'] ?? null;
            }

            // If column was not found via regex, check each lookup field in postData to locate the missing record
            if (!$col) {
                foreach ($fields as $fieldName => $fieldConfig) {
                    if (($fieldConfig['type'] ?? '') === 'lookup' && !empty($postData[$fieldName])) {
                        $targetTable = $fieldConfig['lookup']['table'] ?? '';
                        $targetCol = $fieldConfig['lookup']['value'] ?? 'id';
                        if ($targetTable) {
                            try {
                                $stmt = Database::connection()->prepare("SELECT 1 FROM `{$targetTable}` WHERE `{$targetCol}` = ? LIMIT 1");
                                $stmt->execute([$postData[$fieldName]]);
                                if (!$stmt->fetchColumn()) {
                                    $col = $fieldName;
                                    $tbl = $targetTable;
                                    break;
                                }
                            } catch (\Throwable $e) {}
                        }
                    }
                }
            }

            if ($col) {
                $label = $fields[$col]['label'] ?? ucwords(str_replace(['_id', '_'], ['', ' '], $col));
                $submittedValue = $postData[$col] ?? '';
                $valDetail = $submittedValue !== '' ? " (Value: {$submittedValue})" : '';
                $tblDetail = $tbl ? " in table '{$tbl}'" : '';

                $fieldErrors[$col] = "The selected {$label}{$valDetail} does not exist{$tblDetail} or has been removed.";
                $flashMessage = "Invalid {$label}: The selected record does not exist{$tblDetail} or is no longer available.";
            } else {
                $cleanDetail = preg_replace('/SQLSTATE\[\w+\]:\s*/', '', $detail);
                $cleanDetail = preg_replace('/Integrity constraint violation:\s*/', '', $cleanDetail);
                $fieldErrors['database'] = "A linked record referenced in this form is invalid or does not exist: {$cleanDetail}";
                $flashMessage = "Linked record error: A referenced record does not exist or has been removed in the database.";
            }
        }
        // 3. MySQL Error 1048: Column cannot be null
        elseif ($driverCode === 1048 || str_contains(strtolower($detail), 'cannot be null')) {
            if (preg_match("/Column '([^']+)' cannot be null/i", $detail, $matches)) {
                $col = $matches[1];
                $label = $fields[$col]['label'] ?? ucwords(str_replace('_', ' ', $col));
                $fieldErrors[$col] = "The {$label} field is required and cannot be empty.";
                $flashMessage = "Field '{$label}' cannot be empty. Please provide a value.";
            } else {
                $fieldErrors['database'] = 'A required field cannot be empty.';
                $flashMessage = 'A required field was left empty.';
            }
        }
        // 4. MySQL Error 1451: Foreign key constraint fails on delete/update parent
        elseif ($driverCode === 1451 || str_contains(strtolower($detail), 'cannot delete or update a parent row')) {
            $childTable = null;
            if (preg_match("/(?:REFERENCES|TABLE)\s+[`\"]?(?<child>[^`\" ]+)[`\"]?/i", $detail, $matches)) {
                $childTable = ucwords(str_replace('_', ' ', $matches['child']));
            }
            $targetDesc = $childTable ? "active records in '{$childTable}'" : "other active records";
            $fieldErrors['database'] = "This {$singular} is referenced by {$targetDesc} and cannot be altered or removed.";
            $flashMessage = "Cannot delete or alter this {$singular} because {$targetDesc} depend on it.";
        }
        // 5. MySQL Error 1364: Field doesn't have a default value
        elseif ($driverCode === 1364 || str_contains(strtolower($detail), "doesn't have a default value")) {
            if (preg_match("/Field '([^']+)' doesn't have a default value/i", $detail, $matches)) {
                $col = $matches[1];
                $label = $fields[$col]['label'] ?? ucwords(str_replace('_', ' ', $col));
                $fieldErrors[$col] = "The {$label} field is required.";
                $flashMessage = "Field '{$label}' is required. Please provide a value.";
            } else {
                $fieldErrors['database'] = 'A required field is missing a value.';
                $flashMessage = 'A required field is missing.';
            }
        }
        // 6. MySQL Error 1406: Data too long for column
        elseif ($driverCode === 1406 || str_contains(strtolower($detail), 'data too long')) {
            if (preg_match("/Data too long for column '([^']+)'/i", $detail, $matches)) {
                $col = $matches[1];
                $label = $fields[$col]['label'] ?? ucwords(str_replace('_', ' ', $col));
                $fieldErrors[$col] = "The value entered for {$label} is too long.";
                $flashMessage = "Value entered for '{$label}' exceeds the maximum allowed character length.";
            } else {
                $fieldErrors['database'] = 'Data entered exceeds the column size limit.';
                $flashMessage = 'One or more fields exceed the maximum length.';
            }
        }
        // 7. General Fallback
        else {
            $cleanDetail = preg_replace('/SQLSTATE\[\w+\]:\s*/', '', $detail);
            $cleanDetail = preg_replace('/Integrity constraint violation:\s*/', '', $cleanDetail);
            $cleanDetail = preg_replace('/\s+/', ' ', trim($cleanDetail));
            $fieldErrors['database'] = "Database constraint violation: {$cleanDetail}";
            $flashMessage = "Unable to save {$singular}: {$cleanDetail}";
        }

        return ['errors' => $fieldErrors, 'message' => $flashMessage];
    }
}
