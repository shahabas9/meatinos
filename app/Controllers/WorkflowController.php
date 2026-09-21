<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\WorkflowService;
use PDOException;
use Throwable;

final class WorkflowController
{
    private const ENTITIES = [
        'purchase_order' => ['table' => 'purchase_orders', 'module' => 'purchase_orders', 'permission' => 'purchase', 'title' => 'Purchase Order Workflow', 'number' => 'po_number'],
        'goods_receipt' => ['table' => 'goods_receipts', 'module' => 'goods_receipts', 'permission' => 'purchase', 'title' => 'Goods Receipt Workflow', 'number' => 'grn_number'],
        'bird_receipt' => ['table' => 'bird_receipts', 'module' => 'bird_receipts', 'permission' => 'production', 'title' => 'Live Bird Release Workflow', 'number' => 'batch_number'],
        'production_batch' => ['table' => 'production_batches', 'module' => 'production_batches', 'permission' => 'production', 'title' => 'Production Stage Workflow', 'number' => 'batch_number'],
        'production_requirement' => ['table' => 'production_requirements', 'module' => 'production_requirements', 'permission' => 'production', 'title' => 'Production Requirement Workflow', 'number' => 'requirement_number'],
        'sales_order' => ['table' => 'sales_orders', 'module' => 'sales_orders', 'permission' => 'sales', 'title' => 'Sales Fulfilment Workflow', 'number' => 'order_number'],
        'invoice' => ['table' => 'invoices', 'module' => 'invoices', 'permission' => 'finance', 'title' => 'Invoice Posting Workflow', 'number' => 'invoice_number'],
        'payment' => ['table' => 'payments', 'module' => 'payments', 'permission' => 'finance', 'title' => 'Payment Clearing Workflow', 'number' => 'payment_number'],
        'supplier_invoice' => ['table' => 'supplier_invoices', 'module' => 'supplier_invoices', 'permission' => 'finance', 'title' => 'Supplier Invoice Approval Workflow', 'number' => 'invoice_number'],
        'supplier_payment' => ['table' => 'supplier_payments', 'module' => 'supplier_payments', 'permission' => 'finance', 'title' => 'Supplier Payment Workflow', 'number' => 'payment_number'],
        'operating_expense' => ['table' => 'operating_expenses', 'module' => 'operating_expenses', 'permission' => 'finance', 'title' => 'Operating Expense Workflow', 'number' => 'expense_number'],
        'partner_transaction' => ['table' => 'partner_transactions', 'module' => 'partner_transactions', 'permission' => 'finance', 'title' => 'Partner Capital Workflow', 'number' => 'transaction_number'],
        'partner_dividend' => ['table' => 'partner_dividends', 'module' => 'partner_dividends', 'permission' => 'finance', 'title' => 'Partner Dividend Workflow', 'number' => 'dividend_number'],
        'salary_advance' => ['table' => 'salary_advances', 'module' => 'salary_advances', 'permission' => 'hr', 'title' => 'Salary Advance Workflow', 'number' => 'advance_number'],
        'asset_depreciation' => ['table' => 'asset_depreciation_entries', 'module' => 'asset_depreciation_entries', 'permission' => 'finance', 'title' => 'Asset Depreciation Workflow', 'number' => 'id'],
        'dispatch' => ['table' => 'dispatches', 'module' => 'dispatches', 'permission' => 'logistics', 'title' => 'Dispatch & Delivery Workflow', 'number' => 'dispatch_number'],
    ];

    public function show(): void
    {
        [$entity, $definition, $id] = $this->resolve();
        Auth::requirePermission($definition['permission'] . '.view');
        $record = $this->record($definition['table'], $id);
        $this->authorizeScope($entity, $record);
        $pdo = Database::connection();
        $lines = [];
        $related = [];
        $outputs = [];
        $checks = [];
        $measurements = [];
        $lookups = ['products' => [], 'zones' => [], 'lots' => [], 'purchase_items' => []];

        if ($entity === 'purchase_order') {
            $lines = $this->all('SELECT poi.*,p.sku,p.name product_name FROM purchase_order_items poi LEFT JOIN products p ON p.id=poi.product_id WHERE poi.purchase_order_id=? ORDER BY poi.id', [$id]);
            $lookups['products'] = $pdo->query("SELECT id,sku,name,unit,standard_cost FROM products WHERE status='active' ORDER BY name")->fetchAll();
            $related = $this->all('SELECT id,grn_number,received_date,total_amount,quality_status,status FROM goods_receipts WHERE purchase_order_id=? ORDER BY id DESC', [$id]);
        } elseif ($entity === 'goods_receipt') {
            $lines = $this->all('SELECT gri.*,p.sku,p.name product_name,sz.name zone_name FROM goods_receipt_items gri JOIN products p ON p.id=gri.product_id JOIN storage_zones sz ON sz.id=gri.storage_zone_id WHERE gri.goods_receipt_id=? ORDER BY gri.id', [$id]);
            $lookups['products'] = $pdo->query("SELECT id,sku,name,unit,standard_cost FROM products WHERE status='active' ORDER BY name")->fetchAll();
            $lookups['zones'] = $pdo->query("SELECT id,code,name FROM storage_zones WHERE status='active' ORDER BY name")->fetchAll();
            if ($record['purchase_order_id']) {
                $lookups['purchase_items'] = $this->all('SELECT poi.id,poi.product_id,poi.description,poi.quantity,poi.unit,poi.unit_price,p.sku FROM purchase_order_items poi LEFT JOIN products p ON p.id=poi.product_id WHERE poi.purchase_order_id=? ORDER BY poi.id', [$record['purchase_order_id']]);
            }
        } elseif ($entity === 'sales_order') {
            $lines = $this->all('SELECT soi.*,p.sku,p.name product_name,il.lot_number FROM sales_order_items soi JOIN products p ON p.id=soi.product_id LEFT JOIN inventory_lots il ON il.id=soi.inventory_lot_id WHERE soi.sales_order_id=? ORDER BY soi.id', [$id]);
            $lookups['products'] = $pdo->query("SELECT id,sku,name,unit,selling_price FROM products WHERE status='active' ORDER BY name")->fetchAll();
            $lookups['lots'] = $pdo->query("SELECT il.id,il.product_id,il.lot_number,il.available_quantity,il.expiry_date,p.unit FROM inventory_lots il JOIN products p ON p.id=il.product_id WHERE il.status IN ('available','allocated') AND il.available_quantity>0 AND (il.expiry_date IS NULL OR il.expiry_date>=CURDATE()) ORDER BY COALESCE(il.expiry_date,'9999-12-31'),il.received_date")->fetchAll();
            $related = $this->all('SELECT id,invoice_number,invoice_date,total_amount,balance_amount,status FROM invoices WHERE sales_order_id=? ORDER BY id DESC', [$id]);
        } elseif ($entity === 'production_batch') {
            $lines = $this->all('SELECT * FROM production_stage_logs WHERE production_batch_id=? ORDER BY id', [$id]);
            $related = $this->all('SELECT check_number,checkpoint,checked_at,grade,status FROM quality_checks WHERE production_batch_id=? ORDER BY checked_at DESC', [$id]);
            $outputs = $this->all('SELECT po.*,p.sku,p.name product_name,sz.name zone_name,il.status lot_status FROM production_outputs po JOIN products p ON p.id=po.product_id JOIN storage_zones sz ON sz.id=po.storage_zone_id LEFT JOIN inventory_lots il ON il.id=po.inventory_lot_id WHERE po.production_batch_id=? ORDER BY po.id', [$id]);
            $lookups['products'] = $pdo->query("SELECT id,sku,name,unit,standard_cost FROM products WHERE category='finished_good' AND status='active' ORDER BY name")->fetchAll();
            $lookups['zones'] = $pdo->query("SELECT id,code,name FROM storage_zones WHERE status='active' ORDER BY name")->fetchAll();
            $measurements = $this->all('SELECT measurement_number,stage,measured_at,gross_weight_kg,net_weight_kg,defeathered_bird_count,liver_weight_kg,heart_weight_kg,gizzard_weight_kg,screw_chiller_temperature_c,graded_weight_kg,condemned_bird_count,packed_bird_count,packed_weight_kg,storage_temperature_c,water_level_percent FROM production_stage_measurements WHERE production_batch_id=? ORDER BY measured_at,id', [$id]);
        } elseif ($entity === 'invoice') {
            $lines = $this->all('SELECT je.entry_number,je.entry_date,je.status,coa.account_code,coa.account_name,jl.debit,jl.credit FROM journal_entries je JOIN journal_lines jl ON jl.journal_entry_id=je.id JOIN chart_of_accounts coa ON coa.id=jl.account_id WHERE je.reference_type=\'invoice\' AND je.reference_id=? ORDER BY jl.id', [$id]);
            $related = $this->all('SELECT payment_number,payment_date,amount,payment_method,status FROM payments WHERE invoice_id=? ORDER BY payment_date DESC,id DESC', [$id]);
        } elseif ($entity === 'payment') {
            if ($record['invoice_id']) {
                $related = $this->all('SELECT id,invoice_number,total_amount,paid_amount,balance_amount,status FROM invoices WHERE id=?', [$record['invoice_id']]);
            }
            $lines = $this->all("SELECT je.entry_number,je.entry_date,je.reference_type,coa.account_code,coa.account_name,jl.debit,jl.credit FROM journal_entries je JOIN journal_lines jl ON jl.journal_entry_id=je.id JOIN chart_of_accounts coa ON coa.id=jl.account_id WHERE je.reference_id=? AND je.reference_type IN ('payment','payment_reversal') ORDER BY je.id,jl.id", [$id]);
        } elseif ($entity === 'supplier_invoice') {
            $lines = $this->all("SELECT je.entry_number,je.entry_date,je.status,coa.account_code,coa.account_name,jl.debit,jl.credit FROM journal_entries je JOIN journal_lines jl ON jl.journal_entry_id=je.id JOIN chart_of_accounts coa ON coa.id=jl.account_id WHERE je.reference_type='supplier_invoice' AND je.reference_id=? ORDER BY jl.id", [$id]);
            $related = $this->all('SELECT payment_number,payment_date,amount,payment_method,status FROM supplier_payments WHERE supplier_invoice_id=? ORDER BY payment_date DESC,id DESC', [$id]);
        } elseif ($entity === 'supplier_payment') {
            $related = $this->all('SELECT id,invoice_number,total_amount,paid_amount,balance_amount,status FROM supplier_invoices WHERE id=?', [$record['supplier_invoice_id']]);
            $lines = $this->all("SELECT je.entry_number,je.entry_date,je.reference_type,coa.account_code,coa.account_name,jl.debit,jl.credit FROM journal_entries je JOIN journal_lines jl ON jl.journal_entry_id=je.id JOIN chart_of_accounts coa ON coa.id=jl.account_id WHERE je.reference_id=? AND je.reference_type IN ('supplier_payment','supplier_payment_reversal') ORDER BY je.id,jl.id", [$id]);
        } elseif (in_array($entity, ['operating_expense','partner_transaction','partner_dividend','salary_advance','asset_depreciation'], true)) {
            $referenceTypes = [
                'operating_expense' => ['operating_expense','operating_expense_reversal'],
                'partner_transaction' => ['partner_transaction','partner_transaction_reversal'],
                'partner_dividend' => ['partner_dividend','partner_dividend_reversal'],
                'salary_advance' => ['salary_advance','salary_advance_reversal'],
                'asset_depreciation' => ['asset_depreciation','asset_depreciation_reversal'],
            ][$entity];
            $placeholders = implode(',', array_fill(0, count($referenceTypes), '?'));
            $lines = $this->all("SELECT je.entry_number,je.entry_date,je.reference_type,coa.account_code,coa.account_name,jl.debit,jl.credit FROM journal_entries je JOIN journal_lines jl ON jl.journal_entry_id=je.id JOIN chart_of_accounts coa ON coa.id=jl.account_id WHERE je.reference_id=? AND je.reference_type IN ({$placeholders}) ORDER BY je.id,jl.id", array_merge([$id], $referenceTypes));
        } elseif ($entity === 'dispatch') {
            $lines = $this->all('SELECT soi.quantity,p.sku,p.name product_name,il.lot_number FROM sales_order_items soi JOIN products p ON p.id=soi.product_id LEFT JOIN inventory_lots il ON il.id=soi.inventory_lot_id WHERE soi.sales_order_id=? ORDER BY soi.id', [$record['sales_order_id']]);
            $related = $this->all('SELECT order_number,delivery_date,delivery_address,status,payment_status FROM sales_orders WHERE id=?', [$record['sales_order_id']]);
            $checks = $this->all('SELECT dc.*,u.name checked_by_name FROM dispatch_checks dc LEFT JOIN users u ON u.id=dc.checked_by WHERE dc.dispatch_id=? ORDER BY dc.id', [$id]);
        }

        View::render('workflow/show', [
            'title' => $definition['title'], 'entity' => $entity, 'definition' => $definition,
            'record' => $record, 'lines' => $lines, 'related' => $related, 'outputs' => $outputs, 'checks' => $checks, 'measurements' => $measurements, 'lookups' => $lookups,
            'actions' => $this->actions($entity, $record), 'canManage' => Auth::can($definition['permission'] . '.manage'),
        ]);
    }

    public function action(): void
    {
        [$entity, $definition, $id] = $this->resolve(true);
        Auth::requirePermission($definition['permission'] . '.manage');
        Csrf::verify($_POST['_token'] ?? null);
        $action = preg_replace('/[^a-z_]/', '', (string) ($_POST['action'] ?? ''));
        $this->execute(fn (): string => (new WorkflowService())->transition($entity, $id, $action, $_POST), $entity, $id);
    }

    public function saveLine(): void
    {
        [$entity, $definition, $id] = $this->resolve(true);
        Auth::requirePermission($definition['permission'] . '.manage');
        Csrf::verify($_POST['_token'] ?? null);
        $this->execute(fn (): string => (new WorkflowService())->saveLine($entity, $id, $_POST), $entity, $id);
    }

    public function deleteLine(): void
    {
        [$entity, $definition, $id] = $this->resolve(true);
        Auth::requirePermission($definition['permission'] . '.manage');
        Csrf::verify($_POST['_token'] ?? null);
        $lineId = (int) ($_POST['line_id'] ?? 0);
        $this->execute(fn (): string => (new WorkflowService())->deleteLine($entity, $id, $lineId), $entity, $id);
    }

    public function saveCheck(): void
    {
        [$entity, $definition, $id] = $this->resolve(true);
        if ($entity !== 'dispatch') throw new \RuntimeException('Checklist is available only for dispatch.', 422);
        Auth::requirePermission('logistics.manage');
        Csrf::verify($_POST['_token'] ?? null);
        $this->execute(fn (): string => (new WorkflowService())->saveDispatchCheck($id, $_POST), $entity, $id);
    }

    private function execute(callable $callback, string $entity, int $id): never
    {
        try {
            flash('success', $callback());
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000' || !empty($exception->errorInfo[1])) {
                $detail = (string) ($exception->errorInfo[2] ?? $exception->getMessage());
                $driverCode = (int) ($exception->errorInfo[1] ?? 0);

                if ($driverCode === 1062 || str_contains(strtolower($detail), 'duplicate entry')) {
                    if (preg_match("/Duplicate entry '(?<val>[^']*)' for key '(?<key>[^']+)'/i", $detail, $m)) {
                        $keyParts = explode('.', $m['key']);
                        $keyClean = ucwords(str_replace(['_', 'idx', 'unique', 'fk'], [' ', '', '', ''], end($keyParts)));
                        flash('danger', "Duplicate record: '{$m['val']}' is already in use for {$keyClean}.");
                    } else {
                        flash('danger', 'A duplicate reference number or unique value prevented this workflow step.');
                    }
                } elseif ($driverCode === 1452 || str_contains(strtolower($detail), 'foreign key constraint fails')) {
                    if (preg_match("/FOREIGN KEY\s*\([`\"]?(?<col>[^`\" ]+)[`\"]?\)/i", $detail, $m)) {
                        $colClean = ucwords(str_replace(['_id', '_'], ['', ' '], $m['col']));
                        flash('danger', "Invalid linked record: The referenced {$colClean} does not exist or has been removed.");
                    } else {
                        flash('danger', 'A linked record referenced in this workflow step does not exist or is no longer available.');
                    }
                } elseif ($driverCode === 1048 || str_contains(strtolower($detail), 'cannot be null')) {
                    if (preg_match("/Column '([^']+)' cannot be null/i", $detail, $m)) {
                        $colClean = ucwords(str_replace('_', ' ', $m[1]));
                        flash('danger', "Missing required value: '{$colClean}' cannot be empty.");
                    } else {
                        flash('danger', 'A required workflow value was left empty.');
                    }
                } else {
                    $clean = preg_replace('/SQLSTATE\[\w+\]:\s*/', '', $detail);
                    $clean = preg_replace('/Integrity constraint violation:\s*/', '', $clean);
                    flash('danger', "Workflow constraint violation: " . trim($clean));
                }
            } else {
                throw $exception;
            }
        } catch (Throwable $exception) {
            if ((int) $exception->getCode() >= 400 && (int) $exception->getCode() < 500) {
                flash('danger', $exception->getMessage());
            } else {
                throw $exception;
            }
        }
        redirect('workflow', ['entity' => $entity, 'id' => $id]);
    }

    private function resolve(bool $post = false): array
    {
        $source = $post ? $_POST : $_GET;
        $entity = preg_replace('/[^a-z_]/', '', (string) ($source['entity'] ?? ''));
        $id = (int) ($source['id'] ?? 0);
        if (!isset(self::ENTITIES[$entity]) || $id < 1) {
            throw new \RuntimeException('Workflow record not found.', 404);
        }
        return [$entity, self::ENTITIES[$entity], $id];
    }

    private function record(string $table, int $id): array
    {
        $statement = Database::connection()->prepare("SELECT * FROM `{$table}` WHERE id=?");
        $statement->execute([$id]);
        $record = $statement->fetch();
        if (!$record) {
            throw new \RuntimeException('Workflow record not found.', 404);
        }
        return $record;
    }

    private function authorizeScope(string $entity, array $record): void
    {
        $user = Auth::user();
        if (($user['role_slug'] ?? '') === 'customer') {
            $customerId = (int) ($user['customer_id'] ?? 0);
            $allowed = match ($entity) {
                'sales_order', 'invoice', 'payment' => (int) ($record['customer_id'] ?? 0) === $customerId,
                'dispatch' => (int) $this->value('SELECT customer_id FROM sales_orders WHERE id=?', [$record['sales_order_id']]) === $customerId,
                default => false,
            };
            if (!$allowed) {
                throw new \RuntimeException('Access denied.', 403);
            }
        }
        if (($user['role_slug'] ?? '') === 'driver') {
            $allowed = $entity === 'dispatch' && (int) $record['driver_id'] === (int) ($user['employee_id'] ?? 0);
            if (!$allowed) {
                throw new \RuntimeException('Access denied.', 403);
            }
        }
    }

    private function actions(string $entity, array $record): array
    {
        $status = (string) ($record['status'] ?? '');
        return match ($entity) {
            'purchase_order' => match ($status) {
                'draft' => [['submit', 'Submit for approval', 'primary'], ['cancel', 'Cancel order', 'outline-danger']],
                'pending' => [['approve', 'Approve purchase order', 'success'], ['cancel', 'Reject / cancel', 'outline-danger']],
                'approved' => [['cancel', 'Cancel order', 'outline-danger']],
                default => [],
            },
            'goods_receipt' => match ($status) {
                'draft' => [['submit', 'Submit receipt', 'primary']],
                'received', 'quarantine' => [['accept', 'Accept into inventory', 'success'], ['reject', 'Reject receipt', 'danger']],
                default => [],
            },
            'bird_receipt' => in_array($status, ['quarantine', 'accepted'], true) ? [['release', 'Veterinary release', 'success'], ['reject', 'Reject batch', 'danger']] : [],
            'production_batch' => $status === 'scheduled' ? [['start', 'Start production', 'success']] : (in_array($status, ['in_progress', 'hold'], true) ? [['advance', 'Record stage & continue', 'primary']] : []),
            'production_requirement' => match ($status) {
                'open' => [['plan', 'Plan fulfillment', 'primary'], ['cancel', 'Cancel requirement', 'outline-danger']],
                'planned' => [['start', 'Start fulfillment', 'success'], ['cancel', 'Cancel requirement', 'outline-danger']],
                'in_progress' => [['fulfill', 'Mark as fulfilled', 'success'], ['cancel', 'Cancel requirement', 'outline-danger']],
                default => [],
            },
            'sales_order' => match ($status) {
                'draft' => [['submit', 'Submit for approval', 'primary'], ['cancel', 'Cancel order', 'outline-danger']],
                'pending' => [['approve', 'Approve & allocate FEFO', 'success'], ['cancel', 'Reject / cancel', 'outline-danger']],
                'approved', 'partial' => [['invoice', 'Generate invoice', 'primary'], ['cancel', 'Cancel & release stock', 'outline-danger']],
                'completed' => [['invoice', 'Generate invoice', 'primary']],
                default => [],
            },
            'invoice' => $status === 'draft' ? [['issue', 'Issue & post invoice', 'success'], ['cancel', 'Cancel draft', 'outline-danger']] : [],
            'payment' => $status === 'pending' ? [['clear', 'Clear & post payment', 'success']] : ($status === 'cleared' ? [['reverse', 'Reverse payment', 'danger']] : []),
            'supplier_invoice' => $status === 'draft' ? [['approve', 'Approve & post payable', 'success'], ['cancel', 'Cancel draft', 'outline-danger']] : [],
            'supplier_payment' => $status === 'pending' ? [['clear', 'Clear supplier payment', 'success']] : ($status === 'cleared' ? [['reverse', 'Reverse supplier payment', 'danger']] : []),
            'operating_expense' => match ($status) {
                'draft' => [['approve','Approve expense','success'],['cancel','Cancel draft','outline-danger']],
                'approved' => [['pay','Pay & post expense','primary'],['cancel','Cancel approval','outline-danger']],
                'paid' => [['reverse','Reverse posted expense','danger']], default => [],
            },
            'partner_transaction' => match ($status) {
                'draft' => [['post','Post partner transaction','success'],['cancel','Cancel draft','outline-danger']],
                'posted' => [['reverse','Reverse transaction','danger']], default => [],
            },
            'partner_dividend' => match ($status) {
                'declared' => [['approve','Approve dividend','success'],['cancel','Cancel declaration','outline-danger']],
                'approved' => [['pay','Pay dividend','primary'],['cancel','Cancel approval','outline-danger']],
                'paid' => [['reverse','Reverse dividend','danger']], default => [],
            },
            'salary_advance' => match ($status) {
                'requested' => [['approve','Approve salary advance','success'],['cancel','Reject / cancel','outline-danger']],
                'approved' => [['pay','Pay salary advance','primary'],['cancel','Cancel approval','outline-danger']],
                'paid','recovering' => [['reverse','Reverse payment','danger']], default => [],
            },
            'asset_depreciation' => $status==='draft' ? [['post','Post depreciation','success']] : ($status==='posted' ? [['reverse','Reverse depreciation','danger']] : []),
            'dispatch' => match ($status) {
                'scheduled' => [['load', 'Begin loading', 'primary'], ['cancel', 'Cancel dispatch', 'outline-danger']],
                'loading' => [['depart', 'Release vehicle', 'success'], ['cancel', 'Cancel dispatch', 'outline-danger']],
                'in_transit', 'delayed' => [['deliver', 'Confirm proof of delivery', 'success']],
                default => [],
            },
            default => [],
        };
    }

    private function all(string $sql, array $params = []): array
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    private function value(string $sql, array $params = []): mixed
    {
        $statement = Database::connection()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }
}
