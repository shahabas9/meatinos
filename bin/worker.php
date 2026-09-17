<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use MeatinOS\Core\Database;
use MeatinOS\Services\ScheduledReportService;
use MeatinOS\Services\EmployeeActivityService;

$pdo = Database::connection();
$created = 0;

$raise = static function (string $severity, string $title, string $message, string $type, string $reference) use ($pdo, &$created): void {
    $check = $pdo->prepare("SELECT id FROM alerts WHERE source_type=? AND source_reference=? AND title=? AND status IN ('open','acknowledged') LIMIT 1");
    $check->execute([$type, $reference, $title]);
    if ($check->fetchColumn()) return;
    $pdo->prepare("INSERT INTO alerts (severity,title,message,source_type,source_reference,triggered_at,status) VALUES (?,?,?,?,?,NOW(),'open')")->execute([$severity, $title, $message, $type, $reference]);
    $created++;
};

$pdo->beginTransaction();
try {
    $pdo->exec("UPDATE invoices SET status='overdue' WHERE due_date<CURDATE() AND balance_amount>0 AND status IN ('issued','partial')");
    $pdo->exec("UPDATE sales_orders so JOIN invoices i ON i.sales_order_id=so.id SET so.payment_status='overdue' WHERE i.status='overdue' AND so.payment_status<>'paid'");
    $pdo->exec("UPDATE customers c SET outstanding_balance=(SELECT COALESCE(SUM(i.balance_amount),0) FROM invoices i WHERE i.customer_id=c.id AND i.status IN ('issued','partial','overdue'))");
    $pdo->exec("UPDATE supplier_invoices SET status='overdue' WHERE due_date<CURDATE() AND balance_amount>0 AND status IN ('approved','partial')");
    $pdo->exec("UPDATE suppliers s SET outstanding_balance=(SELECT COALESCE(SUM(si.balance_amount),0) FROM supplier_invoices si WHERE si.supplier_id=s.id AND si.status IN ('approved','partial','overdue'))");
    $pdo->exec("UPDATE inventory_lots SET status='expired' WHERE expiry_date<CURDATE() AND available_quantity>0 AND status IN ('available','allocated')");

    foreach ($pdo->query("SELECT * FROM sensors WHERE current_value IS NOT NULL")->fetchAll() as $sensor) {
        $out = ($sensor['min_threshold'] !== null && (float) $sensor['current_value'] < (float) $sensor['min_threshold']) || ($sensor['max_threshold'] !== null && (float) $sensor['current_value'] > (float) $sensor['max_threshold']);
        $newStatus = $out ? 'critical' : (strtotime((string) $sensor['last_seen_at']) < time() - 900 ? 'offline' : 'online');
        $pdo->prepare('UPDATE sensors SET status=? WHERE id=?')->execute([$newStatus, $sensor['id']]);
        if ($out) $raise('critical', 'Sensor threshold exceeded', $sensor['name'] . ' is outside its configured operating range.', 'sensor', $sensor['sensor_code']);
        if ($newStatus === 'offline') $raise('warning', 'Sensor offline', $sensor['name'] . ' has not reported for 15 minutes.', 'sensor', $sensor['sensor_code']);
    }

    foreach ($pdo->query("SELECT p.sku,p.name,p.reorder_level,COALESCE(SUM(il.available_quantity),0) stock FROM products p LEFT JOIN inventory_lots il ON il.product_id=p.id AND il.status IN ('available','allocated') GROUP BY p.id HAVING stock < p.reorder_level")->fetchAll() as $product) {
        $raise('warning', 'Low stock alert', $product['name'] . ' is below reorder level. Available: ' . $product['stock'] . '.', 'inventory', $product['sku']);
    }
    foreach ($pdo->query("SELECT asset_code,name,next_maintenance_date FROM assets WHERE status='active' AND next_maintenance_date<=CURDATE()+INTERVAL 7 DAY")->fetchAll() as $asset) {
        $raise('warning', 'Maintenance due', $asset['name'] . ' is due for preventive maintenance by ' . $asset['next_maintenance_date'] . '.', 'maintenance', $asset['asset_code']);
    }
    foreach ($pdo->query("SELECT il.lot_number,p.name,il.expiry_date FROM inventory_lots il JOIN products p ON p.id=il.product_id WHERE il.status IN ('available','allocated') AND il.expiry_date<=CURDATE()+INTERVAL 7 DAY")->fetchAll() as $lot) {
        $raise('warning', 'Lot nearing expiry', $lot['name'] . ' lot ' . $lot['lot_number'] . ' expires on ' . $lot['expiry_date'] . '. Prioritize by FEFO.', 'inventory_lot', $lot['lot_number']);
    }
    if ((int) date('G') >= 10 && (int) $pdo->query("SELECT COUNT(*) FROM employees WHERE status='active'")->fetchColumn() > 0 && (int) $pdo->query("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE()")->fetchColumn() === 0) {
        $raise('warning', 'Attendance feed missing', 'No attendance records have been received today. Check the biometric integration.', 'attendance', date('Y-m-d'));
    }
    foreach ($pdo->query("SELECT id,po_number FROM purchase_orders WHERE status='pending' AND updated_at<NOW()-INTERVAL 24 HOUR")->fetchAll() as $order) {
        $raise('warning', 'Purchase approval overdue', $order['po_number'] . ' has awaited approval for more than 24 hours.', 'purchase_order', $order['po_number']);
    }
    foreach ($pdo->query("SELECT id,order_number FROM sales_orders WHERE status='pending' AND updated_at<NOW()-INTERVAL 8 HOUR")->fetchAll() as $order) {
        $raise('warning', 'Sales approval overdue', $order['order_number'] . ' has awaited approval for more than 8 hours.', 'sales_order', $order['order_number']);
    }
    foreach ($pdo->query("SELECT batch_number FROM production_batches WHERE status='hold'")->fetchAll() as $batch) {
        $raise('critical', 'Production batch on hold', $batch['batch_number'] . ' requires quality disposition before processing can continue.', 'production_batch', $batch['batch_number']);
    }
    foreach ($pdo->query("SELECT dispatch_number FROM dispatches WHERE status IN ('scheduled','loading','in_transit','delayed') AND planned_departure<NOW()-INTERVAL 2 HOUR")->fetchAll() as $dispatch) {
        $raise('warning', 'Dispatch milestone overdue', $dispatch['dispatch_number'] . ' is more than two hours beyond planned departure.', 'dispatch', $dispatch['dispatch_number']);
    }
    foreach ($pdo->query("SELECT requirement_number,shortage_quantity,p.name product FROM production_requirements pr JOIN products p ON p.id=pr.product_id WHERE pr.status IN ('open','planned','in_progress') AND pr.required_date<=CURDATE()+INTERVAL 2 DAY")->fetchAll() as $requirement) {
        $raise('warning', 'Production shortage due', $requirement['requirement_number'] . ' requires ' . $requirement['shortage_quantity'] . ' of ' . $requirement['product'] . '.', 'production_requirement', $requirement['requirement_number']);
    }
    foreach ($pdo->query("SELECT qh.hold_number,COALESCE(pb.batch_number,il.lot_number) reference,qh.reason FROM quality_holds qh LEFT JOIN production_batches pb ON pb.id=qh.production_batch_id LEFT JOIN inventory_lots il ON il.id=qh.inventory_lot_id WHERE qh.status='open'")->fetchAll() as $hold) {
        $raise('critical', 'Quality hold blocks dispatch', $hold['hold_number'] . ' for ' . $hold['reference'] . ': ' . $hold['reason'], 'quality_hold', $hold['hold_number']);
    }
    foreach ($pdo->query("SELECT tray_code,current_location,last_seen_at FROM tray_assets WHERE status='missing' OR (status IN ('with_customer','in_vehicle') AND last_seen_at<NOW()-INTERVAL 7 DAY)")->fetchAll() as $tray) {
        $raise('warning', 'Tray tracking exception', $tray['tray_code'] . ' is missing or overdue at ' . ($tray['current_location'] ?: 'an unknown location') . '.', 'tray', $tray['tray_code']);
    }
    foreach ($pdo->query("SELECT document_number,title,expiry_date FROM documents WHERE expiry_date BETWEEN CURDATE() AND CURDATE()+INTERVAL 30 DAY")->fetchAll() as $document) {
        $raise('warning', 'Document expiry approaching', $document['title'] . ' expires on ' . $document['expiry_date'] . '.', 'document', $document['document_number']);
    }
    foreach ($pdo->query("SELECT capa_number,target_date FROM capa_actions WHERE status NOT IN ('closed','cancelled') AND target_date<CURDATE()")->fetchAll() as $capa) {
        $raise('warning', 'CAPA overdue', $capa['capa_number'] . ' passed its target date ' . $capa['target_date'] . '.', 'capa', $capa['capa_number']);
    }
    foreach ($pdo->query("SELECT ar.id,ar.module,ar.record_id,ar.requested_at FROM approval_requests ar WHERE ar.status='pending' AND ar.requested_at<NOW()-INTERVAL 24 HOUR")->fetchAll() as $approval) {
        $raise('warning', 'Approval workflow overdue', human_status($approval['module']) . ' #' . $approval['record_id'] . ' has awaited approval since ' . $approval['requested_at'] . '.', 'approval_request', (string) $approval['id']);
    }
    $pdo->exec("UPDATE sales_targets st SET achieved_amount=(SELECT COALESCE(SUM(i.subtotal),0) FROM invoices i JOIN sales_orders so ON so.id=i.sales_order_id JOIN customers c ON c.id=so.customer_id WHERE COALESCE(so.sales_person_id,c.assigned_salesman_id)=st.employee_id AND i.invoice_date BETWEEN st.period_start AND st.period_end AND i.status IN ('issued','partial','paid','overdue')),commission_amount=CASE WHEN achieved_amount>=target_amount THEN ROUND(achieved_amount*commission_rate/100,2) ELSE 0 END,status=CASE WHEN status='cancelled' THEN status WHEN achieved_amount>=target_amount THEN 'achieved' WHEN period_end<CURDATE() THEN 'missed' ELSE 'active' END");
    $pdo->exec("INSERT INTO sales_commission_accruals (target_id,employee_id,period_start,period_end,eligible_sales,commission_rate,commission_amount,status,calculated_at) SELECT id,employee_id,period_start,period_end,achieved_amount,commission_rate,commission_amount,IF(status='cancelled','reversed','calculated'),NOW() FROM sales_targets ON DUPLICATE KEY UPDATE employee_id=VALUES(employee_id),period_start=VALUES(period_start),period_end=VALUES(period_end),eligible_sales=VALUES(eligible_sales),commission_rate=VALUES(commission_rate),commission_amount=VALUES(commission_amount),status=IF(sales_commission_accruals.status='included',sales_commission_accruals.status,VALUES(status)),calculated_at=NOW()");
    $monthStart=date('Y-m-01'); $monthEnd=date('Y-m-t');
    $depreciation=$pdo->prepare("INSERT IGNORE INTO asset_depreciation_entries (asset_id,period_start,period_end,depreciation_amount,accumulated_amount,book_value,status) SELECT id,?,?,ROUND((acquisition_cost-residual_value)/useful_life_months,2),LEAST(acquisition_cost-residual_value,accumulated_depreciation+ROUND((acquisition_cost-residual_value)/useful_life_months,2)),GREATEST(residual_value,acquisition_cost-(accumulated_depreciation+ROUND((acquisition_cost-residual_value)/useful_life_months,2))),'draft' FROM assets WHERE status='active' AND acquisition_cost>residual_value AND useful_life_months>0 AND accumulated_depreciation<acquisition_cost-residual_value AND acquisition_date<=?");
    $depreciation->execute([$monthStart,$monthEnd,$monthEnd]);
    $pdo->exec("UPDATE company_certificates SET status=CASE WHEN expiry_date<CURDATE() THEN 'expired' WHEN expiry_date<=CURDATE()+INTERVAL 30 DAY AND status='active' THEN 'renewal_due' ELSE status END WHERE expiry_date IS NOT NULL");
    foreach ($pdo->query("SELECT certificate_number,certificate_type,expiry_date FROM company_certificates WHERE status IN ('renewal_due','expired')")->fetchAll() as $certificate) $raise($certificate['expiry_date']<date('Y-m-d')?'critical':'warning','Company certification renewal',human_status($certificate['certificate_type']).' certificate '.$certificate['certificate_number'].' expires / expired on '.$certificate['expiry_date'].'.','company_certificate',$certificate['certificate_number']);
    $pdo->exec("UPDATE roc_filings SET status=CASE WHEN due_date<CURDATE() AND status IN ('planned','due') THEN 'overdue' WHEN due_date<=CURDATE()+INTERVAL 30 DAY AND status='planned' THEN 'due' ELSE status END");
    foreach ($pdo->query("SELECT filing_number,form_name,due_date FROM roc_filings WHERE status IN ('due','overdue')")->fetchAll() as $filing) $raise($filing['due_date']<date('Y-m-d')?'critical':'warning','ROC filing due',$filing['form_name'].' is due on '.$filing['due_date'].'.','roc_filing',$filing['filing_number']);
    foreach ($pdo->query("SELECT registration_number,insurance_expiry,fitness_expiry,permit_expiry FROM vehicles WHERE status='active' AND (insurance_expiry<=CURDATE()+INTERVAL 30 DAY OR fitness_expiry<=CURDATE()+INTERVAL 30 DAY OR permit_expiry<=CURDATE()+INTERVAL 30 DAY)")->fetchAll() as $vehicle) $raise('warning','Vehicle legal renewal due',$vehicle['registration_number'].' has insurance, fitness, or permit renewal due within 30 days.','vehicle',$vehicle['registration_number']);
    foreach ($pdo->query("SELECT event_number,title,start_at FROM calendar_events WHERE status IN ('planned','confirmed') AND start_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL reminder_minutes MINUTE)")->fetchAll() as $event) $raise('info','Calendar reminder',$event['title'].' starts at '.$event['start_at'].'.','calendar_event',$event['event_number']);
    foreach ($pdo->query("SELECT loan_number,scheme_name,next_due_date,outstanding_amount FROM government_loans WHERE status='active' AND next_due_date<=CURDATE()+INTERVAL 15 DAY")->fetchAll() as $loan) $raise('warning','Loan installment due',$loan['scheme_name'].' has a due date of '.$loan['next_due_date'].' and outstanding '.money($loan['outstanding_amount']).'.','government_loan',$loan['loan_number']);
    $created += EmployeeActivityService::generateInactivityReminders($pdo);
    $reportService = new ScheduledReportService($pdo);
    $reportService->refreshMonthlyBalances();
    $reportsGenerated = $reportService->generateDue();
    $retentionDays = max(7,min(365,(int) ($pdo->query('SELECT retention_days FROM ai_settings WHERE id=1')->fetchColumn() ?: 90)));
    $pdo->exec('DELETE FROM ai_messages WHERE created_at < NOW()-INTERVAL ' . $retentionDays . ' DAY');
    $pdo->exec("DELETE c FROM ai_conversations c LEFT JOIN ai_messages m ON m.conversation_id=c.id WHERE m.id IS NULL AND c.updated_at<NOW()-INTERVAL {$retentionDays} DAY");
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    throw $exception;
}

echo "MeatinOS worker complete. {$created} new alerts created; {$reportsGenerated} scheduled reports generated.\n";
