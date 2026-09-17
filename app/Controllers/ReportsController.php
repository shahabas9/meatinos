<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;
use MeatinOS\Services\ScheduledReportService;
use PDO;

final class ReportsController
{
    public function index(): void
    {
        Auth::requirePermission('reports.view');
        $pdo = Database::connection();
        $summary = [
            'yield' => (float) $pdo->query("SELECT COALESCE(AVG(yield_percent),0) FROM production_batches WHERE production_date >= CURDATE()-INTERVAL 30 DAY")->fetchColumn(),
            'mortality' => (float) $pdo->query("SELECT COALESCE(SUM(mortality_count)/NULLIF(SUM(bird_count),0)*100,0) FROM bird_receipts WHERE received_at >= CURDATE()-INTERVAL 30 DAY")->fetchColumn(),
            'sales' => (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE order_date >= CURDATE()-INTERVAL 30 DAY AND status <> 'cancelled'")->fetchColumn(),
            'receivables' => (float) $pdo->query("SELECT COALESCE(SUM(balance_amount),0) FROM invoices WHERE status IN ('issued','partial','overdue')")->fetchColumn(),
            'inventory' => (float) $pdo->query("SELECT COALESCE(SUM(available_quantity*unit_cost),0) FROM inventory_lots WHERE status IN ('available','allocated')")->fetchColumn(),
            'downtime' => (float) $pdo->query("SELECT COALESCE(SUM(downtime_minutes),0) FROM maintenance_tickets WHERE reported_at >= CURDATE()-INTERVAL 30 DAY")->fetchColumn(),
        ];
        $quality = $pdo->query("SELECT grade, COUNT(*) checks, SUM(accepted_qty) accepted, SUM(rejected_qty) rejected FROM quality_checks GROUP BY grade ORDER BY grade")->fetchAll();
        $stock = $pdo->query("SELECT p.sku,p.name,p.unit,SUM(l.available_quantity) available,MIN(l.expiry_date) nearest_expiry FROM products p LEFT JOIN inventory_lots l ON l.product_id=p.id AND l.status IN ('available','allocated') GROUP BY p.id ORDER BY p.name")->fetchAll();
        $finance = $pdo->query("SELECT a.account_type,SUM(jl.debit) debit,SUM(jl.credit) credit FROM chart_of_accounts a JOIN journal_lines jl ON jl.account_id=a.id JOIN journal_entries je ON je.id=jl.journal_entry_id WHERE je.status='posted' GROUP BY a.account_type ORDER BY FIELD(a.account_type,'asset','liability','equity','revenue','expense')")->fetchAll();
        View::render('reports/index', compact('summary', 'quality', 'stock', 'finance') + ['title' => 'Reports & Analytics', 'catalog' => ScheduledReportService::labels()]);
    }

    public function traceability(): void
    {
        Auth::requirePermission('reports.view');
        $query = trim((string) ($_GET['q'] ?? ''));
        $chain = null;
        if ($query !== '') {
            $pdo = Database::connection();
            $statement = $pdo->prepare("SELECT pb.id AS production_batch_id,pb.batch_number AS production_batch,pb.production_date,pb.stage,pb.status AS production_status,pb.input_weight_kg,pb.output_weight_kg,pb.yield_percent,
                br.batch_number AS receiving_batch,br.received_at,br.bird_count,br.mortality_count,br.vet_status,br.farm_name,s.name AS supplier_name,
                qc.check_number,qc.grade,qc.status AS quality_status,qc.checked_at,
                il.id AS lot_id,il.lot_number,il.expiry_date,il.available_quantity,p.name AS product_name,z.name AS storage_zone,
                so.order_number,c.name AS customer_name,i.invoice_number,d.dispatch_number,d.status AS dispatch_status,d.pod_reference,v.registration_number
                FROM production_batches pb
                JOIN bird_receipts br ON br.id=pb.bird_receipt_id JOIN suppliers s ON s.id=br.supplier_id
                LEFT JOIN quality_checks qc ON qc.production_batch_id=pb.id
                LEFT JOIN inventory_lots il ON il.production_batch_id=pb.id LEFT JOIN products p ON p.id=il.product_id LEFT JOIN storage_zones z ON z.id=il.storage_zone_id
                LEFT JOIN sales_order_items soi ON soi.inventory_lot_id=il.id LEFT JOIN sales_orders so ON so.id=soi.sales_order_id LEFT JOIN customers c ON c.id=so.customer_id
                LEFT JOIN invoices i ON i.sales_order_id=so.id LEFT JOIN dispatches d ON d.sales_order_id=so.id LEFT JOIN vehicles v ON v.id=d.vehicle_id
                WHERE pb.batch_number=? OR br.batch_number=? OR il.lot_number=? OR so.order_number=? OR i.invoice_number=? OR d.dispatch_number=?
                   OR br.purchase_order_id=(SELECT id FROM purchase_orders WHERE po_number=? LIMIT 1)
                   OR so.id=(SELECT sales_order_id FROM customer_complaints WHERE complaint_number=? LIMIT 1)
                   OR il.barcode=? OR il.rfid_tag=?
                ORDER BY il.id,so.id LIMIT 100");
            $statement->execute(array_fill(0, 10, $query));
            $chain = $statement->fetchAll();
        }
        View::render('reports/traceability', ['title' => 'End-to-End Traceability', 'query' => $query, 'chain' => $chain]);
    }

    public function globalSearch(): void
    {
        Auth::requireLogin();
        $query = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
        $results = [];
        if (mb_strlen($query) >= 2) {
            $pdo = Database::connection();
            $like = '%' . $query . '%';
            $collect = static function (PDO $pdo, string $sql, array $params, callable $map) use (&$results): void {
                $statement = $pdo->prepare($sql);
                $statement->execute($params);
                foreach ($statement->fetchAll() as $row) $results[] = $map($row);
            };
            if (Auth::can('reports.view')) {
                $collect($pdo, "SELECT batch_number number,'Receiving batch' kind,CONCAT(farm_name,' · ',bird_count,' birds') meta FROM bird_receipts WHERE batch_number LIKE ? OR farm_name LIKE ? LIMIT 8", [$like,$like], static fn(array $r): array => $r + ['icon'=>'bi-clipboard2-pulse','url'=>url('traceability',['q'=>$r['number']])]);
                $collect($pdo, "SELECT batch_number number,'Production batch' kind,CONCAT(stage,' · ',status) meta FROM production_batches WHERE batch_number LIKE ? LIMIT 8", [$like], static fn(array $r): array => $r + ['icon'=>'bi-diagram-3','url'=>url('traceability',['q'=>$r['number']])]);
                $collect($pdo, "SELECT lot_number number,'Inventory lot' kind,CONCAT(p.name,' · ',il.available_quantity,' ',p.unit) meta FROM inventory_lots il JOIN products p ON p.id=il.product_id WHERE lot_number LIKE ? OR il.barcode LIKE ? OR il.rfid_tag LIKE ? LIMIT 8", [$like,$like,$like], static fn(array $r): array => $r + ['icon'=>'bi-upc-scan','url'=>url('traceability',['q'=>$r['number']])]);
            }
            if (Auth::can('sales.view')) {
                $user = Auth::user();
                $scope = ($user['role_slug'] ?? '') === 'customer' ? ' AND so.customer_id=' . (int) ($user['customer_id'] ?? 0) : '';
                $collect($pdo, "SELECT so.order_number number,'Sales order' kind,CONCAT(c.name,' · ',so.status) meta FROM sales_orders so JOIN customers c ON c.id=so.customer_id WHERE (so.order_number LIKE ? OR c.name LIKE ?) {$scope} LIMIT 10", [$like,$like], static fn(array $r): array => $r + ['icon'=>'bi-cart3','url'=>url('traceability',['q'=>$r['number']])]);
            }
            if (Auth::can('crm.view') && !Auth::hasRole('customer')) {
                $collect($pdo, "SELECT id,code number,'Customer' kind,name meta FROM customers WHERE code LIKE ? OR name LIKE ? OR phone LIKE ? LIMIT 10", [$like,$like,$like], static fn(array $r): array => $r + ['icon'=>'bi-people','url'=>url('entity360',['type'=>'customer','id'=>$r['id']])]);
            }
            if (Auth::can('purchase.view')) {
                $collect($pdo, "SELECT id,code number,'Supplier' kind,name meta FROM suppliers WHERE code LIKE ? OR name LIKE ? OR contact_person LIKE ? LIMIT 10", [$like,$like,$like], static fn(array $r): array => $r + ['icon'=>'bi-buildings','url'=>url('entity360',['type'=>'supplier','id'=>$r['id']])]);
            }
            if (Auth::can('inventory.view')) {
                $collect($pdo, "SELECT id,sku number,'Product' kind,name meta FROM products WHERE sku LIKE ? OR name LIKE ? OR barcode LIKE ? OR rfid_tag LIKE ? LIMIT 10", [$like,$like,$like,$like], static fn(array $r): array => $r + ['icon'=>'bi-box-seam','url'=>url('entity360',['type'=>'product','id'=>$r['id']])]);
            }
            if (Auth::can('hr.view')) {
                $collect($pdo, "SELECT employee_number number,'Employee' kind,CONCAT(full_name,' · ',department) meta FROM employees WHERE employee_number LIKE ? OR full_name LIKE ? OR department LIKE ? LIMIT 10", [$like,$like,$like], static fn(array $r): array => $r + ['icon'=>'bi-person-badge','url'=>url('module',['name'=>'employees','q'=>$r['number']])]);
            }
            if (Auth::can('logistics.view')) {
                $collect($pdo, "SELECT registration_number number,'Vehicle' kind,CONCAT(make_model,' · ',status) meta FROM vehicles WHERE registration_number LIKE ? OR make_model LIKE ? OR gps_device_id LIKE ? LIMIT 10", [$like,$like,$like], static fn(array $r): array => $r + ['icon'=>'bi-truck-front','url'=>url('module',['name'=>'vehicles','q'=>$r['number']])]);
            }
        }
        View::render('reports/search', ['title'=>'Global Search','query'=>$query,'results'=>array_slice($results,0,60)]);
    }

    public function entity360(): void
    {
        Auth::requireLogin();
        $type = preg_replace('/[^a-z]/', '', (string) ($_GET['type'] ?? ''));
        $id = (int) ($_GET['id'] ?? 0);
        $pdo = Database::connection();
        $profile = null;
        $sections = [];
        $title = '360° Profile';
        if ($type === 'customer') {
            Auth::requirePermission('crm.view');
            $statement = $pdo->prepare('SELECT * FROM customers WHERE id=?'); $statement->execute([$id]); $profile=$statement->fetch();
            if ($profile) {
                $title = $profile['name'] . ' · Customer 360°';
                $sections['Orders'] = $this->prepared($pdo,'SELECT order_number,order_date,delivery_date,total_amount,payment_status,status FROM sales_orders WHERE customer_id=? ORDER BY id DESC LIMIT 50',[$id]);
                $sections['Invoices & Outstanding'] = $this->prepared($pdo,'SELECT invoice_number,invoice_date,due_date,total_amount,paid_amount,balance_amount,status FROM invoices WHERE customer_id=? ORDER BY id DESC LIMIT 50',[$id]);
                $sections['Payments'] = $this->prepared($pdo,'SELECT payment_number,payment_date,amount,payment_method,reference_number,status FROM payments WHERE customer_id=? ORDER BY id DESC LIMIT 50',[$id]);
                $sections['Monthly Opening & Closing Balances'] = $this->prepared($pdo,'SELECT month_start,opening_balance,invoice_amount,payment_amount,credit_amount,closing_balance,generated_at FROM customer_monthly_balances WHERE customer_id=? ORDER BY month_start DESC LIMIT 36',[$id]);
                $sections['Authorized Bank Links'] = $this->prepared($pdo,'SELECT bank_name,account_holder,masked_account,currency,is_primary,is_verified,status FROM customer_bank_accounts WHERE customer_id=? ORDER BY is_primary DESC,id DESC',[$id]);
                $sections['Deliveries'] = $this->prepared($pdo,'SELECT d.dispatch_number,so.order_number,d.route_name,d.planned_departure,d.temperature_c load_temperature_c,d.delivery_temperature_c,d.delivery_temperature_recorded_at,d.status,d.pod_reference FROM dispatches d JOIN sales_orders so ON so.id=d.sales_order_id WHERE so.customer_id=? ORDER BY d.id DESC LIMIT 50',[$id]);
                $sections['Returns & Complaints'] = array_merge($this->prepared($pdo,"SELECT return_number reference,return_date date,reason detail,status FROM sales_returns WHERE customer_id=? ORDER BY id DESC",[$id]),$this->prepared($pdo,"SELECT complaint_number reference,complaint_date date,details detail,status FROM customer_complaints WHERE customer_id=? ORDER BY id DESC",[$id]));
            }
        } elseif ($type === 'supplier') {
            Auth::requirePermission('purchase.view');
            $statement=$pdo->prepare('SELECT * FROM suppliers WHERE id=?'); $statement->execute([$id]); $profile=$statement->fetch();
            if ($profile) {
                $title=$profile['name'].' · Supplier 360°';
                $sections['Purchase Orders']=$this->prepared($pdo,'SELECT po_number,order_date,expected_date,total_amount,status FROM purchase_orders WHERE supplier_id=? ORDER BY id DESC LIMIT 50',[$id]);
                $sections['Bird Batches']=$this->prepared($pdo,'SELECT batch_number,farm_name,received_at,bird_count,mortality_count,net_weight_kg,vet_status,status FROM bird_receipts WHERE supplier_id=? ORDER BY id DESC LIMIT 50',[$id]);
                $sections['Goods Receipts']=$this->prepared($pdo,'SELECT grn_number,received_date,total_amount,quality_status,status FROM goods_receipts WHERE supplier_id=? ORDER BY id DESC LIMIT 50',[$id]);
                $sections['Payables']=$this->prepared($pdo,'SELECT invoice_number,invoice_date,due_date,total_amount,paid_amount,balance_amount,status FROM supplier_invoices WHERE supplier_id=? ORDER BY id DESC LIMIT 50',[$id]);
                $performance=$this->prepared($pdo,'SELECT COUNT(*) batches,COALESCE(SUM(bird_count),0) birds,COALESCE(SUM(mortality_count)/NULLIF(SUM(bird_count),0)*100,0) mortality_percent,COALESCE(AVG(sample_avg_weight_kg),0) average_bird_weight FROM bird_receipts WHERE supplier_id=?',[$id]);
                $sections['Performance']=$performance;
            }
        } elseif ($type === 'product') {
            Auth::requirePermission('inventory.view');
            $statement=$pdo->prepare('SELECT * FROM products WHERE id=?'); $statement->execute([$id]); $profile=$statement->fetch();
            if ($profile) {
                $title=$profile['name'].' · Product 360°';
                $sections['Stock & Lots']=$this->prepared($pdo,'SELECT il.lot_number,sz.name storage,il.received_date,il.expiry_date,il.quantity,il.available_quantity,il.unit_cost,il.status FROM inventory_lots il JOIN storage_zones sz ON sz.id=il.storage_zone_id WHERE il.product_id=? ORDER BY il.id DESC LIMIT 100',[$id]);
                $sections['Production']=$this->prepared($pdo,'SELECT pb.batch_number,po.lot_number,po.grade,po.quantity,po.qc_status,pb.production_date,pb.status FROM production_outputs po JOIN production_batches pb ON pb.id=po.production_batch_id WHERE po.product_id=? ORDER BY po.id DESC LIMIT 50',[$id]);
                $sections['Sales']=$this->prepared($pdo,'SELECT so.order_number,c.name customer,so.order_date,soi.quantity,soi.unit_price,soi.line_total,so.status FROM sales_order_items soi JOIN sales_orders so ON so.id=soi.sales_order_id JOIN customers c ON c.id=so.customer_id WHERE soi.product_id=? ORDER BY soi.id DESC LIMIT 100',[$id]);
                $sections['Pricing']=$this->prepared($pdo,'SELECT cp.price_type,c.name customer,cp.customer_type,cp.unit_price,cp.valid_from,cp.valid_to,cp.max_discount_percent,cp.status FROM customer_prices cp LEFT JOIN customers c ON c.id=cp.customer_id WHERE cp.product_id=? ORDER BY cp.valid_from DESC',[$id]);
            }
        }
        if (!$profile) throw new \RuntimeException('360° profile not found.',404);
        View::render('reports/entity360',['title'=>$title,'type'=>$type,'profile'=>$profile,'sections'=>$sections]);
    }

    private function prepared(PDO $pdo, string $sql, array $params): array
    {
        $statement=$pdo->prepare($sql); $statement->execute($params); return $statement->fetchAll();
    }

    public function intelligence(): void
    {
        Auth::requirePermission('reports.view');
        $pdo = Database::connection();
        $metrics = [
            'yield' => (float) $pdo->query("SELECT COALESCE(AVG(yield_percent),0) FROM production_batches WHERE production_date>=CURDATE()-INTERVAL 30 DAY")->fetchColumn(),
            'mortality' => (float) $pdo->query("SELECT COALESCE(SUM(mortality_count)/NULLIF(SUM(bird_count),0)*100,0) FROM bird_receipts WHERE received_at>=CURDATE()-INTERVAL 30 DAY")->fetchColumn(),
            'forecast_production' => (float) $pdo->query("SELECT COALESCE(SUM(output_weight_kg)/30*7,0) FROM production_batches WHERE production_date>=CURDATE()-INTERVAL 30 DAY")->fetchColumn(),
            'forecast_sales' => (float) $pdo->query("SELECT COALESCE(SUM(total_amount)/30*7,0) FROM sales_orders WHERE order_date>=CURDATE()-INTERVAL 30 DAY AND status<>'cancelled'")->fetchColumn(),
            'expiry_risk' => (int) $pdo->query("SELECT COUNT(*) FROM inventory_lots WHERE status IN ('available','allocated') AND expiry_date BETWEEN CURDATE() AND CURDATE()+INTERVAL 7 DAY")->fetchColumn(),
            'critical_alerts' => (int) $pdo->query("SELECT COUNT(*) FROM alerts WHERE status='open' AND severity='critical'")->fetchColumn(),
        ];
        $demand = $pdo->query("SELECT p.sku,p.name,p.unit,COALESCE(SUM(DISTINCT il.available_quantity),0) available,COALESCE((SELECT SUM(soi.quantity) FROM sales_order_items soi JOIN sales_orders so ON so.id=soi.sales_order_id WHERE soi.product_id=p.id AND so.order_date>=CURDATE()-INTERVAL 30 DAY AND so.status<>'cancelled'),0) sold_30 FROM products p LEFT JOIN inventory_lots il ON il.product_id=p.id AND il.status IN ('available','allocated') WHERE p.category='finished_good' GROUP BY p.id ORDER BY sold_30 DESC,p.name")->fetchAll();
        foreach ($demand as &$row) {
            $daily = (float) $row['sold_30'] / 30;
            $row['forecast_7'] = round($daily * 7, 2);
            $row['cover_days'] = $daily > 0 ? round((float) $row['available'] / $daily, 1) : null;
        }
        unset($row);
        $recommendations = [];
        if ($metrics['critical_alerts'] > 0) $recommendations[] = ['critical','Cold-chain exception','Acknowledge open critical alerts, verify product temperature, and isolate affected lots before release.','IoT + quality rules'];
        if ($metrics['yield'] < 70) $recommendations[] = ['warning','Yield below operating target','Review receiving weight basis, evisceration loss, grading rejections, and stage calibration for the latest production batch.','30-day yield trend'];
        if ($metrics['expiry_risk'] > 0) $recommendations[] = ['warning','FEFO action required','Prioritize near-expiry lots in picking and customer allocation; prevent new stock from bypassing older lots.','Expiry and lot status'];
        $lowStock = (int) $pdo->query("SELECT COUNT(*) FROM products p WHERE p.reorder_level>0 AND COALESCE((SELECT SUM(available_quantity) FROM inventory_lots WHERE product_id=p.id AND status IN ('available','allocated')),0)<p.reorder_level")->fetchColumn();
        if ($lowStock > 0) $recommendations[] = ['warning','Replenishment risk',number_format($lowStock) . ' item(s) are below reorder level. Raise or expedite approved procurement.','Stock vs. reorder level'];
        $overdue = (float) $pdo->query("SELECT COALESCE(SUM(balance_amount),0) FROM invoices WHERE status='overdue' OR (status IN ('issued','partial') AND due_date<CURDATE())")->fetchColumn();
        if ($overdue > 0) $recommendations[] = ['warning','Collection priority',money($overdue) . ' is overdue. Assign collection actions and review customer credit exposure.','Invoice due dates'];
        if (!$recommendations) $recommendations[] = ['success','No priority exceptions','Current operating rules found no urgent production, inventory, quality, or finance exception.','Cross-module controls'];
        View::render('reports/intelligence', ['title' => 'Operational Intelligence', 'metrics' => $metrics, 'demand' => $demand, 'recommendations' => $recommendations]);
    }

    public function commercial(): void
    {
        Auth::requirePermission('reports.view');
        $pdo = Database::connection();
        $service = new ScheduledReportService();
        $service->refreshMonthlyBalances();
        $datasets = [];
        foreach (['salesman','sales_received_person','customer_ranking','customer_comparison','vendor_comparison','customer_ledger','price_comparison'] as $type) {
            $datasets[$type] = array_slice($service->rows($type), 0, 250);
        }
        $salespeople = $pdo->query("SELECT id,employee_number,full_name FROM employees WHERE department='Sales' AND status='active' ORDER BY full_name")->fetchAll();
        $salesmanId = (int) ($_GET['salesman_id'] ?? 0);
        $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($_GET['month'] ?? '')) ? (string) $_GET['month'] : date('Y-m');
        $periodStart = $month . '-01';
        $periodEnd = date('Y-m-t', strtotime($periodStart));
        $selectedSalesman = null;
        foreach ($salespeople as $salesperson) if ((int) $salesperson['id'] === $salesmanId) $selectedSalesman = $salesperson;
        $salesmanSummary = null;
        $salesmanDetails = [];
        if ($selectedSalesman) {
            $statement = $pdo->prepare("SELECT COUNT(*) orders,COALESCE(SUM(so.total_amount),0) sales_value FROM sales_orders so JOIN customers c ON c.id=so.customer_id WHERE COALESCE(so.sales_person_id,c.assigned_salesman_id)=? AND so.order_date BETWEEN ? AND ? AND so.status<>'cancelled'");
            $statement->execute([$salesmanId,$periodStart,$periodEnd]);
            $salesmanSummary = $statement->fetch();
            $statement = $pdo->prepare("SELECT so.order_number,so.order_date,c.name customer,p.sku,p.name product,soi.quantity,soi.unit_price,soi.line_total,so.status FROM sales_order_items soi JOIN sales_orders so ON so.id=soi.sales_order_id JOIN customers c ON c.id=so.customer_id JOIN products p ON p.id=soi.product_id WHERE COALESCE(so.sales_person_id,c.assigned_salesman_id)=? AND so.order_date BETWEEN ? AND ? AND so.status<>'cancelled' ORDER BY so.order_date DESC,so.id DESC,p.name");
            $statement->execute([$salesmanId,$periodStart,$periodEnd]);
            $salesmanDetails = $statement->fetchAll();
            $salesmanSummary['quantity'] = array_sum(array_map(static fn(array $row): float => (float) $row['quantity'], $salesmanDetails));
        }
        View::render('reports/commercial', compact('datasets','salespeople','salesmanId','month','selectedSalesman','salesmanSummary','salesmanDetails') + ['title'=>'Commercial Analytics']);
    }

    public function scheduled(): void
    {
        Auth::requirePermission('reports.view');
        $rows = Database::connection()->query("SELECT rr.*,rs.schedule_number,rs.name,rs.report_type,rs.frequency FROM report_runs rr JOIN report_schedules rs ON rs.id=rr.report_schedule_id ORDER BY rr.started_at DESC LIMIT 250")->fetchAll();
        View::render('reports/scheduled', ['title'=>'Generated Reports','rows'=>$rows]);
    }

    public function downloadScheduled(): void
    {
        Auth::requirePermission('reports.view');
        $id = (int)($_GET['id'] ?? 0);
        $statement = Database::connection()->prepare("SELECT rr.*,rs.name FROM report_runs rr JOIN report_schedules rs ON rs.id=rr.report_schedule_id WHERE rr.id=? AND rr.status='completed' LIMIT 1");
        $statement->execute([$id]);
        $run = $statement->fetch();
        if (!$run || empty($run['file_path'])) throw new \RuntimeException('Generated report is not available.',404);
        $base = realpath(BASE_PATH . '/storage/reports');
        $file = realpath(BASE_PATH . '/' . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, (string)$run['file_path']));
        if (!$base || !$file || !str_starts_with($file,$base . DIRECTORY_SEPARATOR) || !is_file($file)) throw new \RuntimeException('Generated report file is unavailable.',404);
        AuditService::log('downloaded','report_runs',$id,'Generated report downloaded.',null,['file'=>basename($file)]);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/','_',basename($file)) . '"');
        header('Content-Length: ' . filesize($file));
        readfile($file);
        exit;
    }

    public function export(): void
    {
        Auth::requirePermission('reports.view');
        $type = preg_replace('/[^a-z_]/', '', (string) ($_GET['type'] ?? ''));
        if ($type === 'salesman_detail') {
            $salesmanId = (int) ($_GET['salesman_id'] ?? 0);
            $month = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($_GET['month'] ?? '')) ? (string) $_GET['month'] : date('Y-m');
            $pdo = Database::connection();
            $employee = $pdo->prepare("SELECT employee_number,full_name FROM employees WHERE id=? AND department='Sales' LIMIT 1");
            $employee->execute([$salesmanId]);
            $salesperson = $employee->fetch();
            if (!$salesperson) throw new \RuntimeException('Salesman report not found.', 404);
            $statement = $pdo->prepare("SELECT so.order_number,so.order_date,c.code customer_code,c.name customer,p.sku,p.name product,soi.quantity,soi.unit_price,soi.line_total,so.payment_status,so.status FROM sales_order_items soi JOIN sales_orders so ON so.id=soi.sales_order_id JOIN customers c ON c.id=so.customer_id JOIN products p ON p.id=soi.product_id WHERE COALESCE(so.sales_person_id,c.assigned_salesman_id)=? AND so.order_date BETWEEN ? AND ? AND so.status<>'cancelled' ORDER BY so.order_date DESC,so.id DESC,p.name");
            $statement->execute([$salesmanId,$month.'-01',date('Y-m-t',strtotime($month.'-01'))]);
            $rows = $statement->fetchAll();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="meatinos-salesman-' . preg_replace('/[^A-Za-z0-9._-]/','_',strtolower((string)$salesperson['employee_number'])) . '-' . $month . '.csv"');
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            if ($rows) {
                fputcsv($out, array_keys($rows[0]));
                foreach ($rows as $row) fputcsv($out, $row);
            }
            fclose($out);
            exit;
        }
        $reports = [
            'production' => "SELECT pb.batch_number,pb.production_date,br.batch_number receiving_batch,pb.birds_input,pb.input_weight_kg,pb.output_weight_kg,pb.rejected_weight_kg,pb.yield_percent,pb.stage,pb.status FROM production_batches pb JOIN bird_receipts br ON br.id=pb.bird_receipt_id ORDER BY pb.production_date DESC",
            'inventory' => "SELECT il.lot_number,p.sku,p.name product,z.name storage_zone,il.quantity,il.available_quantity,p.unit,il.expiry_date,il.temperature_c,il.status FROM inventory_lots il JOIN products p ON p.id=il.product_id JOIN storage_zones z ON z.id=il.storage_zone_id ORDER BY il.received_date DESC",
            'sales' => "SELECT so.order_number,c.name customer,so.order_date,so.delivery_date,so.subtotal,so.discount_amount,so.tax_amount,so.total_amount,so.payment_status,so.status FROM sales_orders so JOIN customers c ON c.id=so.customer_id ORDER BY so.order_date DESC",
            'attendance' => "SELECT e.employee_number,e.full_name,e.department,a.attendance_date,a.check_in,a.check_out,a.regular_hours,a.overtime_hours,a.source,a.status FROM attendance a JOIN employees e ON e.id=a.employee_id ORDER BY a.attendance_date DESC,e.full_name",
            'maintenance' => "SELECT mt.ticket_number,a.asset_code,a.name asset,mt.ticket_type,mt.priority,mt.reported_at,mt.scheduled_date,mt.downtime_minutes,mt.status,mt.resolution FROM maintenance_tickets mt JOIN assets a ON a.id=mt.asset_id ORDER BY mt.reported_at DESC",
            'quality' => "SELECT qc.check_number,pb.batch_number production_batch,qc.checkpoint,qc.checked_at,qc.sample_size,qc.accepted_qty,qc.rejected_qty,qc.grade,qc.weight_kg,qc.temperature_c,qc.appearance,qc.smell,qc.physical_damage,qc.status,qc.corrective_action FROM quality_checks qc JOIN production_batches pb ON pb.id=qc.production_batch_id ORDER BY qc.checked_at DESC",
            'dispatch' => "SELECT d.dispatch_number,so.order_number,c.name customer,v.registration_number,e.full_name driver,d.route_name,d.planned_departure,d.actual_departure,d.delivered_at,d.temperature_c,d.status,d.pod_reference FROM dispatches d JOIN sales_orders so ON so.id=d.sales_order_id JOIN customers c ON c.id=so.customer_id JOIN vehicles v ON v.id=d.vehicle_id JOIN employees e ON e.id=d.driver_id ORDER BY d.planned_departure DESC",
            'payroll' => "SELECT pr.run_number,pr.period_start,pr.period_end,pr.pay_date,e.employee_number,e.full_name,e.department,pi.basic_amount,pi.overtime_hours,pi.overtime_amount,pi.allowances,pi.deductions,pi.net_amount,pr.status FROM payroll_items pi JOIN payroll_runs pr ON pr.id=pi.payroll_run_id JOIN employees e ON e.id=pi.employee_id ORDER BY pr.period_end DESC,e.full_name",
            'cold_storage' => "SELECT z.code zone_code,z.name,z.zone_type,z.capacity_kg,z.min_temperature_c,z.max_temperature_c,z.current_temperature_c,z.status,COALESCE(SUM(l.available_quantity),0) used_kg,COALESCE(z.capacity_kg-SUM(l.available_quantity),z.capacity_kg) available_capacity_kg,MIN(l.expiry_date) nearest_expiry FROM storage_zones z LEFT JOIN inventory_lots l ON l.storage_zone_id=z.id AND l.status IN ('available','allocated') GROUP BY z.id ORDER BY z.code",
            'profitability' => "SELECT DATE_FORMAT(months.month_start,'%Y-%m') month,COALESCE((SELECT SUM(i.total_amount) FROM invoices i WHERE i.invoice_date>=months.month_start AND i.invoice_date<months.month_start+INTERVAL 1 MONTH AND i.status<>'cancelled'),0) revenue,COALESCE((SELECT SUM(po.total_amount) FROM purchase_orders po WHERE po.order_date>=months.month_start AND po.order_date<months.month_start+INTERVAL 1 MONTH AND po.status<>'cancelled'),0) procurement_cost,COALESCE((SELECT SUM(i.total_amount) FROM invoices i WHERE i.invoice_date>=months.month_start AND i.invoice_date<months.month_start+INTERVAL 1 MONTH AND i.status<>'cancelled'),0)-COALESCE((SELECT SUM(po.total_amount) FROM purchase_orders po WHERE po.order_date>=months.month_start AND po.order_date<months.month_start+INTERVAL 1 MONTH AND po.status<>'cancelled'),0) operating_contribution FROM (SELECT DATE_FORMAT(CURDATE(),'%Y-%m-01') month_start UNION ALL SELECT DATE_FORMAT(CURDATE()-INTERVAL 1 MONTH,'%Y-%m-01') UNION ALL SELECT DATE_FORMAT(CURDATE()-INTERVAL 2 MONTH,'%Y-%m-01') UNION ALL SELECT DATE_FORMAT(CURDATE()-INTERVAL 3 MONTH,'%Y-%m-01') UNION ALL SELECT DATE_FORMAT(CURDATE()-INTERVAL 4 MONTH,'%Y-%m-01') UNION ALL SELECT DATE_FORMAT(CURDATE()-INTERVAL 5 MONTH,'%Y-%m-01')) months ORDER BY months.month_start DESC",
        ];
        $scheduledReports = ScheduledReportService::queries();
        $reports += $scheduledReports;
        $reports['sales'] = $scheduledReports['sales'];
        $reports['dispatch'] = $scheduledReports['dispatch'];
        if (!isset($reports[$type])) {
            http_response_code(404);
            throw new \RuntimeException('Report export not found.', 404);
        }
        $rows = Database::connection()->query($reports[$type])->fetchAll();
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="meatinos-' . $type . '-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'wb');
        fwrite($out, "\xEF\xBB\xBF");
        if ($rows) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        }
        fclose($out);
        exit;
    }

    public function audit(): void
    {
        Auth::requirePermission('audit.view');
        $rows = Database::connection()->query("SELECT a.*,u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 250")->fetchAll();
        View::render('audit/index', ['title' => 'Audit Trail', 'rows' => $rows]);
    }
}
