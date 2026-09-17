<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;
use PDO;

final class ProductionController
{
    public function wastage(): void
    {
        Auth::requirePermission('production.view');
        [$from, $to] = $this->period();
        $pdo = Database::connection();
        $stageRows = $this->stageRows($pdo, $from, $to);
        $damagedRows = $this->damagedRows($pdo, $from, $to);
        $yieldRows = $this->prepared($pdo, "SELECT pb.batch_number,pb.production_date,yr.waste_weight_kg,yr.waste_percent,yr.by_product_weight_kg,yr.calculated_at FROM yield_records yr JOIN production_batches pb ON pb.id=yr.production_batch_id WHERE pb.production_date BETWEEN ? AND ? AND yr.waste_weight_kg>0 ORDER BY pb.production_date DESC,yr.id DESC", [$from,$to]);
        $deboningRows = $this->prepared($pdo, "SELECT dr.deboning_number,pb.batch_number,dr.processed_at,dr.input_weight_kg,dr.bone_weight_kg,dr.waste_weight_kg,dr.yield_percent FROM deboning_records dr JOIN production_batches pb ON pb.id=dr.production_batch_id WHERE DATE(dr.processed_at) BETWEEN ? AND ? AND dr.waste_weight_kg>0 ORDER BY dr.processed_at DESC,dr.id DESC", [$from,$to]);

        $summary = [
            'stage' => array_sum(array_column($stageRows, 'total_waste_kg')),
            'damaged' => array_sum(array_column($damagedRows, 'damaged_weight_kg')),
            'damaged_birds' => array_sum(array_column($damagedRows, 'damaged_bird_count')),
            'condemned' => array_sum(array_column($stageRows, 'condemned_weight_kg')),
            'yield' => array_sum(array_column($yieldRows, 'waste_weight_kg')),
            'deboning' => array_sum(array_column($deboningRows, 'waste_weight_kg')),
        ];
        View::render('production/wastage', compact('from','to','stageRows','damagedRows','yieldRows','deboningRows','summary') + ['title'=>'Production Wastage']);
    }

    public function exportWastage(): void
    {
        Auth::requirePermission('production.view');
        [$from, $to] = $this->period();
        $type = ($_GET['type'] ?? '') === 'damaged' ? 'damaged' : 'stage';
        $rows = $type === 'damaged' ? $this->damagedRows(Database::connection(), $from, $to) : $this->stageRows(Database::connection(), $from, $to);
        AuditService::log('exported', 'production_wastage', null, 'Production wastage CSV exported.', null, ['from'=>$from,'to'=>$to,'rows'=>count($rows)]);
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="meatinos-' . $type . '-wastage-' . $from . '-to-' . $to . '.csv"');
        $output = fopen('php://output', 'wb');
        fwrite($output, "\xEF\xBB\xBF");
        if ($rows) {
            fputcsv($output, array_keys($rows[0]));
            foreach ($rows as $row) fputcsv($output, $row);
        }
        fclose($output);
        exit;
    }

    /** @return array{0:string,1:string} */
    private function period(): array
    {
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['from'] ?? '')) ? (string) $_GET['from'] : date('Y-m-d', strtotime('-30 days'));
        $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['to'] ?? '')) ? (string) $_GET['to'] : date('Y-m-d');
        if ($to < $from) [$from, $to] = [$to, $from];
        return [$from, $to];
    }

    /** @return list<array<string,mixed>> */
    private function stageRows(PDO $pdo, string $from, string $to): array
    {
        return $this->prepared($pdo, "SELECT psm.measurement_number,pb.batch_number,psm.stage,psm.measured_at,psm.blood_loss_kg,psm.head_waste_kg,psm.defeathering_waste_kg,psm.evisceration_waste_kg,psm.peeled_skin_waste_kg,psm.condemned_weight_kg,psm.packaging_waste_kg,(psm.blood_loss_kg+psm.head_waste_kg+psm.defeathering_waste_kg+psm.evisceration_waste_kg+psm.peeled_skin_waste_kg+psm.condemned_weight_kg+psm.packaging_waste_kg) total_waste_kg FROM production_stage_measurements psm JOIN production_batches pb ON pb.id=psm.production_batch_id WHERE DATE(psm.measured_at) BETWEEN ? AND ? HAVING total_waste_kg>0 ORDER BY psm.measured_at DESC,psm.id DESC", [$from,$to]);
    }

    /** @return list<array<string,mixed>> */
    private function damagedRows(PDO $pdo, string $from, string $to): array
    {
        return $this->prepared($pdo, "SELECT pdb.damage_number,pb.batch_number,pdb.stage,pdb.damaged_bird_count,pdb.damaged_weight_kg,pdb.reason_category,pdb.reason_details,pdb.disposition,pdb.recorded_at,pdb.status,e.full_name recorded_by FROM production_damaged_birds pdb JOIN production_batches pb ON pb.id=pdb.production_batch_id LEFT JOIN users u ON u.id=pdb.recorded_by LEFT JOIN employees e ON e.id=u.employee_id WHERE DATE(pdb.recorded_at) BETWEEN ? AND ? ORDER BY pdb.recorded_at DESC,pdb.id DESC", [$from,$to]);
    }

    /** @return list<array<string,mixed>> */
    private function prepared(PDO $pdo, string $sql, array $params): array
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}
