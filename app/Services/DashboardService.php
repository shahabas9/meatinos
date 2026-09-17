<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use MeatinOS\Core\Database;
use PDO;

final class DashboardService
{
    public function driverData(int $employeeId): array
    {
        $pdo = Database::connection();
        $employeeStatement = $pdo->prepare("SELECT id,employee_number,full_name,phone FROM employees WHERE id=? AND department='Logistics' AND status='active'");
        $employeeStatement->execute([$employeeId]);
        $driver = $employeeStatement->fetch();
        if (!$driver) {
            throw new \RuntimeException('This driver user is not linked to an active logistics employee.', 422);
        }
        $dispatchStatement = $pdo->prepare("SELECT d.*,so.order_number,so.delivery_address,c.name customer_name,v.registration_number,v.make_model FROM dispatches d JOIN sales_orders so ON so.id=d.sales_order_id JOIN customers c ON c.id=so.customer_id JOIN vehicles v ON v.id=d.vehicle_id WHERE d.driver_id=? ORDER BY d.planned_departure DESC LIMIT 30");
        $dispatchStatement->execute([$employeeId]);
        $dispatches = $dispatchStatement->fetchAll();
        $cards = ['assigned' => count($dispatches), 'active' => 0, 'delivered' => 0, 'delayed' => 0];
        foreach ($dispatches as $dispatch) {
            if (in_array($dispatch['status'], ['loading', 'in_transit', 'scheduled'], true)) $cards['active']++;
            if ($dispatch['status'] === 'delivered') $cards['delivered']++;
            if ($dispatch['status'] === 'delayed') $cards['delayed']++;
        }
        return compact('driver', 'cards', 'dispatches');
    }

    public function customerData(int $customerId): array
    {
        $pdo = Database::connection();
        $customerStatement = $pdo->prepare('SELECT id,code,name,contact_person,credit_limit,outstanding_balance,payment_terms_days FROM customers WHERE id=? AND status=\'active\'');
        $customerStatement->execute([$customerId]);
        $customer = $customerStatement->fetch();
        if (!$customer) {
            throw new \RuntimeException('This portal user is not linked to an active customer account.', 422);
        }
        $cardStatement = $pdo->prepare("SELECT COUNT(*) total_orders,SUM(status IN ('pending','approved','partial')) open_orders,COALESCE(SUM(total_amount),0) order_value FROM sales_orders WHERE customer_id=?");
        $cardStatement->execute([$customerId]);
        $cards = $cardStatement->fetch();
        $receivableStatement = $pdo->prepare("SELECT COALESCE(SUM(balance_amount),0) FROM invoices WHERE customer_id=? AND status IN ('issued','partial','overdue')");
        $receivableStatement->execute([$customerId]);
        $cards['receivables'] = (float) $receivableStatement->fetchColumn();
        $dispatchStatement = $pdo->prepare("SELECT d.dispatch_number,d.status,d.planned_departure,d.delivered_at,d.temperature_c,d.pod_reference,d.route_name,so.order_number,v.registration_number FROM dispatches d JOIN sales_orders so ON so.id=d.sales_order_id JOIN vehicles v ON v.id=d.vehicle_id WHERE so.customer_id=? ORDER BY d.planned_departure DESC LIMIT 20");
        $dispatchStatement->execute([$customerId]);
        $dispatches = $dispatchStatement->fetchAll();
        $orderStatement = $pdo->prepare("SELECT so.id,so.order_number,so.order_date,so.delivery_date,so.total_amount,so.payment_status,so.status,i.invoice_number,i.balance_amount FROM sales_orders so LEFT JOIN invoices i ON i.sales_order_id=so.id WHERE so.customer_id=? ORDER BY so.order_date DESC,so.id DESC LIMIT 20");
        $orderStatement->execute([$customerId]);
        $orders = $orderStatement->fetchAll();
        $complaintStatement = $pdo->prepare('SELECT complaint_number,sales_order_id,complaint_date,category,details,status FROM customer_complaints WHERE customer_id=? ORDER BY complaint_date DESC,id DESC LIMIT 20');
        $complaintStatement->execute([$customerId]);
        $complaints = $complaintStatement->fetchAll();
        return compact('customer', 'cards', 'orders', 'dispatches', 'complaints');
    }

    public function data(): array
    {
        $pdo = Database::connection();
        $scalar = static function (string $sql) use ($pdo): float {
            return (float) $pdo->query($sql)->fetchColumn();
        };
        $cards = [
            'birds' => $scalar("SELECT COALESCE(SUM(bird_count),0) FROM bird_receipts WHERE DATE(received_at)=CURDATE()"),
            'processed' => $scalar("SELECT COALESCE(SUM(output_weight_kg),0) FROM production_batches WHERE production_date=CURDATE()"),
            'sales' => $scalar("SELECT COALESCE(SUM(total_amount),0) FROM sales_orders WHERE order_date=CURDATE() AND status <> 'cancelled'"),
            'orders' => $scalar("SELECT COUNT(*) FROM sales_orders WHERE status IN ('pending','approved','partial')"),
            'storage_used' => $scalar("SELECT COALESCE(SUM(available_quantity),0) FROM inventory_lots WHERE status IN ('available','allocated')"),
            'storage_capacity' => $scalar("SELECT COALESCE(SUM(capacity_kg),0) FROM storage_zones WHERE status='active'"),
        ];
        $cards['storage_percent'] = $cards['storage_capacity'] > 0 ? round(($cards['storage_used'] / $cards['storage_capacity']) * 100, 1) : 0;
        $cards['revenue'] = $scalar("SELECT COALESCE(SUM(total_amount),0) FROM invoices WHERE invoice_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND status <> 'cancelled'");
        $cards['cost'] = $scalar("SELECT COALESCE(SUM(total_amount),0) FROM purchase_orders WHERE order_date >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND status <> 'cancelled'");
        $cards['profit'] = $cards['revenue'] - $cards['cost'];
        $cards['attendance_present'] = $scalar("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status IN ('present','late')");
        $cards['attendance_absent'] = $scalar("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status='absent'");
        $cards['attendance_leave'] = $scalar("SELECT COUNT(*) FROM attendance WHERE attendance_date=CURDATE() AND status='leave'");
        $cards['critical_alerts'] = $scalar("SELECT COUNT(*) FROM alerts WHERE status='open' AND severity='critical'");

        $alerts = $pdo->query("SELECT * FROM alerts WHERE status <> 'resolved' ORDER BY FIELD(severity,'critical','warning','info'), triggered_at DESC LIMIT 5")->fetchAll();
        $dispatches = $pdo->query("SELECT d.*, v.registration_number, e.full_name AS driver_name FROM dispatches d JOIN vehicles v ON v.id=d.vehicle_id JOIN employees e ON e.id=d.driver_id ORDER BY planned_departure DESC LIMIT 5")->fetchAll();
        $products = $pdo->query("SELECT p.name, p.unit, SUM(l.available_quantity) AS quantity, MIN(l.expiry_date) AS nearest_expiry FROM products p JOIN inventory_lots l ON l.product_id=p.id WHERE p.category='finished_good' GROUP BY p.id,p.name,p.unit ORDER BY quantity DESC LIMIT 5")->fetchAll();
        $zones = $pdo->query("SELECT z.*, COALESCE(SUM(l.available_quantity),0) AS used_kg FROM storage_zones z LEFT JOIN inventory_lots l ON l.storage_zone_id=z.id AND l.status IN ('available','allocated') GROUP BY z.id ORDER BY z.id")->fetchAll();
        $productionBatch = $pdo->query("SELECT id,batch_number,birds_input,input_weight_kg,output_weight_kg,stage,status FROM production_batches ORDER BY production_date DESC,id DESC LIMIT 1")->fetch() ?: null;
        $stages = $productionBatch ? $this->productionStages($pdo, $productionBatch) : [];
        $activities = $pdo->query("SELECT a.*, u.name AS user_name FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id ORDER BY a.created_at DESC LIMIT 8")->fetchAll();

        $trend = $pdo->query("SELECT d.day,
            COALESCE((SELECT SUM(pb.output_weight_kg) FROM production_batches pb WHERE pb.production_date=d.day),0) AS production,
            COALESCE((SELECT SUM(so.total_amount) FROM sales_orders so WHERE so.order_date=d.day AND so.status <> 'cancelled'),0) AS sales
            FROM (SELECT CURDATE() - INTERVAL 6 DAY AS day UNION ALL SELECT CURDATE() - INTERVAL 5 DAY UNION ALL SELECT CURDATE() - INTERVAL 4 DAY UNION ALL SELECT CURDATE() - INTERVAL 3 DAY UNION ALL SELECT CURDATE() - INTERVAL 2 DAY UNION ALL SELECT CURDATE() - INTERVAL 1 DAY UNION ALL SELECT CURDATE()) d ORDER BY d.day")->fetchAll();
        return compact('cards', 'alerts', 'dispatches', 'products', 'zones', 'stages', 'productionBatch', 'activities', 'trend');
    }

    private function productionStages(PDO $pdo, array $batch): array
    {
        $logStatement = $pdo->prepare('SELECT stage,quality_status,started_at,completed_at FROM production_stage_logs WHERE production_batch_id=? ORDER BY id');
        $logStatement->execute([$batch['id']]);
        $logs = [];
        foreach ($logStatement->fetchAll() as $row) $logs[(string) $row['stage']] = $row;

        $measurementStatement = $pdo->prepare('SELECT stage,MAX(measured_at) measured_at FROM production_stage_measurements WHERE production_batch_id=? GROUP BY stage');
        $measurementStatement->execute([$batch['id']]);
        $measurements = [];
        foreach ($measurementStatement->fetchAll() as $row) $measurements[(string) $row['stage']] = $row;

        $definitions = [
            'receiving' => ['measurement'=>'receiving','log'=>'receiving'],
            'slaughtering' => ['measurement'=>'slaughtering','log'=>'slaughtering'],
            'scalding' => ['measurement'=>'scalding','log'=>'scalding'],
            'defeathering' => ['measurement'=>'defeathering','log'=>'defeathering'],
            'evisceration' => ['measurement'=>'evisceration','log'=>'evisceration'],
            'chilling' => ['measurement'=>'chilling','log'=>'chilling'],
            'grading' => ['measurement'=>'grading','log'=>'grading'],
            'packing' => ['measurement'=>'packing','log'=>'packing'],
            'storage' => ['measurement'=>'storage','log'=>'storage'],
            'dispatch' => ['measurement'=>null,'log'=>'dispatch'],
        ];
        $rows = [];
        foreach ($definitions as $stage => $sources) {
            $measurement = $sources['measurement'] ? ($measurements[$sources['measurement']] ?? null) : null;
            $log = $sources['log'] ? ($logs[$sources['log']] ?? null) : null;
            $completed = $measurement['measured_at'] ?? ($log['completed_at'] ?? null);
            $rows[$stage] = [
                'stage' => $stage,
                'state' => $completed ? 'complete' : 'pending',
                'status_label' => $measurement ? 'Recorded' : ($log ? human_status((string) $log['quality_status']) : 'Pending'),
                'completed_at' => $completed,
            ];
        }
        $stageNames = array_keys($definitions);
        $currentIndex = array_search((string) $batch['stage'], $stageNames, true);
        $furthestIndex = $currentIndex === false ? -1 : (int) $currentIndex;
        foreach ($rows as $stage => $row) {
            if ($row['state'] === 'complete') {
                $stageIndex = array_search($stage, $stageNames, true);
                if ($stageIndex !== false) $furthestIndex = max($furthestIndex, (int) $stageIndex);
            }
        }
        if ($batch['status'] === 'completed') $furthestIndex = count($stageNames);
        foreach ($stageNames as $index => $stage) {
            if ($index < $furthestIndex && $rows[$stage]['state'] === 'pending') {
                $rows[$stage]['state'] = 'complete';
                $rows[$stage]['status_label'] = 'Completed';
            }
        }
        if (!in_array($batch['status'], ['completed','cancelled'], true)) {
            $currentGroups = array_combine(array_keys($definitions), array_map(static fn(string $stage): array => [$stage], array_keys($definitions)));
            foreach ($currentGroups[(string) $batch['stage']] ?? [] as $candidate) {
                if ($rows[$candidate]['state'] !== 'complete') {
                    $rows[$candidate]['state'] = 'current';
                    $rows[$candidate]['status_label'] = $batch['status'] === 'hold' ? 'On hold' : 'Current';
                    break;
                }
            }
        }
        return array_values($rows);
    }
}
