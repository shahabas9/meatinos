<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use MeatinOS\Core\Database;
use PDO;
use Throwable;

final class WorkflowService
{
    private PDO $pdo;
    private int $savepointSequence = 0;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::connection();
    }

    public function transition(string $entity, int $id, string $action, array $input = []): string
    {
        if ($id < 1) {
            throw new \RuntimeException('A valid workflow record is required.', 422);
        }

        return $this->atomic(function () use ($entity, $id, $action, $input): string {
            return match ($entity . ':' . $action) {
                'purchase_order:submit' => $this->submitPurchaseOrder($id),
                'purchase_order:approve' => $this->approvePurchaseOrder($id),
                'purchase_order:cancel' => $this->cancelPurchaseOrder($id),
                'goods_receipt:submit' => $this->submitGoodsReceipt($id),
                'goods_receipt:accept' => $this->acceptGoodsReceipt($id),
                'goods_receipt:reject' => $this->rejectGoodsReceipt($id),
                'bird_receipt:release' => $this->releaseBirdReceipt($id),
                'bird_receipt:reject' => $this->rejectBirdReceipt($id),
                'production_batch:start' => $this->startProduction($id),
                'production_batch:advance' => $this->advanceProduction($id, $input),
                'sales_order:submit' => $this->submitSalesOrder($id),
                'sales_order:approve' => $this->approveSalesOrder($id),
                'sales_order:cancel' => $this->cancelSalesOrder($id),
                'sales_order:invoice' => $this->createInvoice($id),
                'invoice:issue' => $this->issueInvoice($id),
                'invoice:cancel' => $this->cancelInvoice($id),
                'payment:clear' => $this->clearPayment($id),
                'payment:reverse' => $this->reversePayment($id),
                'supplier_invoice:approve' => $this->approveSupplierInvoice($id),
                'supplier_invoice:cancel' => $this->cancelSupplierInvoice($id),
                'supplier_payment:clear' => $this->clearSupplierPayment($id),
                'supplier_payment:reverse' => $this->reverseSupplierPayment($id),
                'operating_expense:approve' => $this->approveOperatingExpense($id),
                'operating_expense:pay' => $this->payOperatingExpense($id),
                'operating_expense:reverse' => $this->reverseOperatingExpense($id, $input),
                'operating_expense:cancel' => $this->cancelOperatingExpense($id),
                'partner_transaction:post' => $this->postPartnerTransaction($id),
                'partner_transaction:reverse' => $this->reversePartnerTransaction($id, $input),
                'partner_transaction:cancel' => $this->cancelPartnerTransaction($id),
                'partner_dividend:approve' => $this->approvePartnerDividend($id),
                'partner_dividend:pay' => $this->payPartnerDividend($id),
                'partner_dividend:reverse' => $this->reversePartnerDividend($id, $input),
                'partner_dividend:cancel' => $this->cancelPartnerDividend($id),
                'salary_advance:approve' => $this->approveSalaryAdvance($id),
                'salary_advance:pay' => $this->paySalaryAdvance($id),
                'salary_advance:reverse' => $this->reverseSalaryAdvance($id, $input),
                'salary_advance:cancel' => $this->cancelSalaryAdvance($id),
                'asset_depreciation:post' => $this->postAssetDepreciation($id),
                'asset_depreciation:reverse' => $this->reverseAssetDepreciation($id, $input),
                'dispatch:load' => $this->updateDispatch($id, 'loading', $input),
                'dispatch:depart' => $this->updateDispatch($id, 'in_transit', $input),
                'dispatch:deliver' => $this->updateDispatch($id, 'delivered', $input),
                'dispatch:cancel' => $this->updateDispatch($id, 'cancelled', $input),
                default => throw new \RuntimeException('That workflow action is not supported.', 422),
            };
        });
    }

    public function saveLine(string $entity, int $recordId, array $input): string
    {
        return $this->atomic(function () use ($entity, $recordId, $input): string {
            return match ($entity) {
                'purchase_order' => $this->savePurchaseLine($recordId, $input),
                'goods_receipt' => $this->saveGoodsReceiptLine($recordId, $input),
                'sales_order' => $this->saveSalesLine($recordId, $input),
                'production_batch' => $this->saveProductionOutput($recordId, $input),
                default => throw new \RuntimeException('Lines are not supported for that workflow.', 422),
            };
        });
    }

    public function deleteLine(string $entity, int $recordId, int $lineId): string
    {
        return $this->atomic(function () use ($entity, $recordId, $lineId): string {
            $map = [
                'purchase_order' => ['purchase_orders', 'purchase_order_items', 'purchase_order_id'],
                'goods_receipt' => ['goods_receipts', 'goods_receipt_items', 'goods_receipt_id'],
                'sales_order' => ['sales_orders', 'sales_order_items', 'sales_order_id'],
                'production_batch' => ['production_batches', 'production_outputs', 'production_batch_id'],
            ];
            if (!isset($map[$entity])) {
                throw new \RuntimeException('Lines are not supported for that workflow.', 422);
            }
            [$headerTable, $lineTable, $foreignKey] = $map[$entity];
            $header = $this->row($headerTable, $recordId);
            $line = $this->fetchOne("SELECT * FROM `{$lineTable}` WHERE id=? AND `{$foreignKey}`=?", [$lineId, $recordId]);
            $this->expect($line !== null, 'Line not found.');
            if ($entity === 'production_batch') {
                $this->expect(in_array($header['status'], ['in_progress','hold'], true) && empty($line['inventory_lot_id']), 'Posted production output cannot be removed.');
            } else {
                $this->expect($header['status'] === 'draft', 'Only draft records can have lines removed.');
            }
            $delete = $this->pdo->prepare("DELETE FROM `{$lineTable}` WHERE id=? AND `{$foreignKey}`=?");
            $delete->execute([$lineId, $recordId]);
            $this->expect($delete->rowCount() === 1, 'Line not found.');
            if ($entity === 'purchase_order') {
                $this->recalculatePurchaseOrder($recordId);
            } elseif ($entity === 'sales_order') {
                $this->recalculateSalesOrder($recordId);
            } elseif ($entity === 'goods_receipt') {
                $this->recalculateGoodsReceipt($recordId);
            }
            AuditService::log('line_deleted', $entity, $recordId, 'Draft workflow line removed.', ['line_id' => $lineId]);
            return 'Line removed and totals recalculated.';
        });
    }

    public function finalizeDispatch(int $dispatchId): void
    {
        $dispatch = $this->row('dispatches', $dispatchId);
        $this->expect($dispatch['status'] === 'delivered', 'Dispatch is not delivered.');
        $items = $this->fetchAll('SELECT soa.inventory_lot_id,soa.quantity FROM sales_order_allocations soa JOIN sales_order_items soi ON soi.id=soa.sales_order_item_id WHERE soi.sales_order_id=? AND soa.released_at IS NULL', [$dispatch['sales_order_id']]);
        if (!$items) {
            $items = $this->fetchAll('SELECT inventory_lot_id,quantity FROM sales_order_items WHERE sales_order_id=? AND inventory_lot_id IS NOT NULL', [$dispatch['sales_order_id']]);
        }
        foreach ($items as $item) {
            $exists = $this->fetchValue("SELECT COUNT(*) FROM stock_movements WHERE inventory_lot_id=? AND movement_type='dispatch' AND reference_type='dispatch' AND reference_id=?", [$item['inventory_lot_id'], $dispatchId]);
            if ((int) $exists === 0) {
                $this->pdo->prepare("INSERT INTO stock_movements (inventory_lot_id,movement_type,quantity,from_zone_id,reference_type,reference_id,reason,moved_by,moved_at) SELECT id,'dispatch',?,storage_zone_id,'dispatch',?,'Proof-of-delivery confirmed',?,NOW() FROM inventory_lots WHERE id=?")
                    ->execute([$item['quantity'], $dispatchId, $this->userId(), $item['inventory_lot_id']]);
            }
        }
        $cost = (float) $this->fetchValue('SELECT COALESCE(SUM(soa.quantity*il.unit_cost),0) FROM sales_order_allocations soa JOIN sales_order_items soi ON soi.id=soa.sales_order_item_id JOIN inventory_lots il ON il.id=soa.inventory_lot_id WHERE soi.sales_order_id=? AND soa.released_at IS NULL', [$dispatch['sales_order_id']]);
        if ($cost <= 0) $cost = (float) $this->fetchValue('SELECT COALESCE(SUM(soi.quantity*il.unit_cost),0) FROM sales_order_items soi JOIN inventory_lots il ON il.id=soi.inventory_lot_id WHERE soi.sales_order_id=?', [$dispatch['sales_order_id']]);
        $posted = (int) $this->fetchValue("SELECT COUNT(*) FROM journal_entries WHERE reference_type='dispatch' AND reference_id=? AND status='posted'", [$dispatchId]);
        if ($cost > 0 && $posted === 0) {
            $this->postJournal('dispatch', $dispatchId, (string) $dispatch['dispatch_number'], date('Y-m-d'), [
                ['5200', $cost, 0.0, 'Cost of goods sold'],
                ['1200', 0.0, $cost, 'Finished-goods inventory relieved'],
            ]);
        }
        $open = (int) $this->fetchValue("SELECT COUNT(*) FROM dispatches WHERE sales_order_id=? AND status NOT IN ('delivered','cancelled')", [$dispatch['sales_order_id']]);
        if ($open === 0) {
            $this->pdo->prepare("UPDATE sales_orders SET status='completed' WHERE id=? AND status IN ('approved','partial')")->execute([$dispatch['sales_order_id']]);
        }
    }

    public function saveDispatchCheck(int $dispatchId, array $input): string
    {
        return $this->atomic(function () use ($dispatchId, $input): string {
            $dispatch = $this->row('dispatches', $dispatchId);
            $this->expect(in_array($dispatch['status'], ['scheduled','loading','delayed'], true), 'The pre-dispatch checklist is locked after departure.');
            $this->ensureDispatchChecklist($dispatchId);
            $checkId = (int) ($input['check_id'] ?? 0);
            $passed = !empty($input['passed']) ? 1 : 0;
            $notes = mb_substr(trim((string) ($input['notes'] ?? '')), 0, 500);
            $statement = $this->pdo->prepare('UPDATE dispatch_checks SET passed=?,checked_by=?,checked_at=NOW(),notes=? WHERE id=? AND dispatch_id=?');
            $statement->execute([$passed, $this->userId(), $notes ?: null, $checkId, $dispatchId]);
            $this->expect($statement->rowCount() === 1, 'Checklist item not found.');
            AuditService::log('checklist_updated', 'dispatches', $dispatchId, 'Pre-dispatch checklist item updated.', null, ['check_id'=>$checkId,'passed'=>$passed,'notes'=>$notes]);
            return 'Pre-dispatch checklist updated.';
        });
    }

    private function submitPurchaseOrder(int $id): string
    {
        $order = $this->row('purchase_orders', $id);
        $this->expect($order['status'] === 'draft', 'Only a draft purchase order can be submitted.');
        $this->recalculatePurchaseOrder($id);
        $this->expect((int) $this->fetchValue('SELECT COUNT(*) FROM purchase_order_items WHERE purchase_order_id=?', [$id]) > 0, 'Add at least one purchase-order line before submitting.');
        $this->pdo->prepare("UPDATE purchase_orders SET status='pending' WHERE id=?")->execute([$id]);
        (new ApprovalService($this->pdo))->request('purchase_orders', $id, (float) $order['total_amount'], !empty($order['plant_id']) ? (int) $order['plant_id'] : null);
        $this->auditTransition('purchase_orders', $id, 'draft', 'pending');
        return 'Purchase order submitted for approval.';
    }

    private function approvePurchaseOrder(int $id): string
    {
        $order = $this->row('purchase_orders', $id);
        $this->expect($order['status'] === 'pending', 'Only a pending purchase order can be approved.');
        $supplierOk = (int) $this->fetchValue("SELECT COUNT(*) FROM suppliers WHERE id=? AND status='active' AND approval_status='approved'", [$order['supplier_id']]);
        $this->expect($supplierOk === 1, 'The supplier must be active and approved.');
        $this->expect((float) $order['total_amount'] > 0, 'The purchase order total must be greater than zero.');
        if (!(new ApprovalService($this->pdo))->approve('purchase_orders', $id)) return 'Approval step recorded. The purchase order remains pending for the next configured approver.';
        $this->pdo->prepare("UPDATE purchase_orders SET status='approved',approved_by=? WHERE id=?")->execute([$this->userId(), $id]);
        $this->auditTransition('purchase_orders', $id, 'pending', 'approved');
        return 'Purchase order approved and released for receiving.';
    }

    private function cancelPurchaseOrder(int $id): string
    {
        $order = $this->row('purchase_orders', $id);
        $this->expect(in_array($order['status'], ['draft', 'pending', 'approved'], true), 'This purchase order can no longer be cancelled.');
        $receiving = (int) $this->fetchValue("SELECT COUNT(*) FROM goods_receipts WHERE purchase_order_id=? AND status IN ('received','quarantine','accepted')", [$id]);
        $this->expect($receiving === 0, 'A purchase order with active or accepted receipts cannot be cancelled.');
        if ($order['status'] === 'pending') (new ApprovalService($this->pdo))->reject('purchase_orders',$id,'Purchase order rejected or cancelled from its controlled workflow.');
        $this->pdo->prepare("UPDATE purchase_orders SET status='cancelled' WHERE id=?")->execute([$id]);
        $this->auditTransition('purchase_orders', $id, (string) $order['status'], 'cancelled');
        return 'Purchase order cancelled.';
    }

    private function submitGoodsReceipt(int $id): string
    {
        $receipt = $this->row('goods_receipts', $id);
        $this->expect($receipt['status'] === 'draft', 'Only a draft goods receipt can be submitted.');
        $this->expect((int) $this->fetchValue('SELECT COUNT(*) FROM goods_receipt_items WHERE goods_receipt_id=?', [$id]) > 0, 'Add at least one receipt line before submitting.');
        $this->recalculateGoodsReceipt($id);
        if ($receipt['purchase_order_id']) {
            $po = $this->row('purchase_orders', (int) $receipt['purchase_order_id']);
            $this->expect(in_array($po['status'], ['approved', 'partial'], true), 'The linked purchase order is not approved for receiving.');
            $this->expect((int) $po['supplier_id'] === (int) $receipt['supplier_id'], 'Receipt supplier must match the purchase order supplier.');
        }
        $this->pdo->prepare("UPDATE goods_receipts SET status='received' WHERE id=?")->execute([$id]);
        $this->auditTransition('goods_receipts', $id, 'draft', 'received');
        return 'Goods receipt submitted for quality disposition.';
    }

    private function acceptGoodsReceipt(int $id): string
    {
        $receipt = $this->row('goods_receipts', $id);
        $this->expect(in_array($receipt['status'], ['received', 'quarantine'], true), 'Only received or quarantined goods can be accepted.');
        $this->expect(in_array($receipt['quality_status'], ['passed', 'conditional'], true), 'Quality must pass or grant a conditional release.');
        $items = $this->fetchAll('SELECT * FROM goods_receipt_items WHERE goods_receipt_id=? FOR UPDATE', [$id]);
        $this->expect($items !== [], 'The goods receipt has no line items.');
        if ($receipt['purchase_order_id']) {
            $po = $this->row('purchase_orders', (int) $receipt['purchase_order_id']);
            $this->expect(in_array($po['status'], ['approved', 'partial'], true), 'The linked purchase order is no longer open for receiving.');
        }
        foreach ($items as $item) {
            $this->expect(empty($item['inventory_lot_id']), 'This receipt has already created inventory and cannot be accepted twice.');
            $lotStatus = $receipt['quality_status'] === 'conditional' ? 'quarantine' : 'available';
            $insert = $this->pdo->prepare("INSERT INTO inventory_lots (lot_number,product_id,storage_zone_id,received_date,expiry_date,quantity,available_quantity,unit_cost,barcode,status) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $insert->execute([$item['lot_number'], $item['product_id'], $item['storage_zone_id'], $receipt['received_date'], $item['expiry_date'], $item['quantity'], $item['quantity'], $item['unit_cost'], $item['lot_number'], $lotStatus]);
            $lotId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE goods_receipt_items SET inventory_lot_id=? WHERE id=?')->execute([$lotId, $item['id']]);
            $this->pdo->prepare("INSERT INTO stock_movements (inventory_lot_id,movement_type,quantity,to_zone_id,reference_type,reference_id,reason,moved_by,moved_at) VALUES (?,'receipt',?,?,'goods_receipt',?,'Accepted supplier receipt',?,NOW())")
                ->execute([$lotId, $item['quantity'], $item['storage_zone_id'], $id, $this->userId()]);
        }
        $this->pdo->prepare("UPDATE goods_receipts SET status='accepted' WHERE id=?")->execute([$id]);
        if ((float) $receipt['total_amount'] > 0) {
            $this->postJournal('goods_receipt', $id, (string) $receipt['grn_number'], (string) $receipt['received_date'], [
                ['1200', (float) $receipt['total_amount'], 0.0, 'Inventory received'],
                ['2050', 0.0, (float) $receipt['total_amount'], 'Goods received not invoiced'],
            ]);
        }
        if ($receipt['purchase_order_id']) {
            $this->reconcilePurchaseOrder((int) $receipt['purchase_order_id']);
        }
        $this->auditTransition('goods_receipts', $id, (string) $receipt['status'], 'accepted');
        return 'Goods accepted; inventory lots and stock-ledger receipts were created.';
    }

    private function rejectGoodsReceipt(int $id): string
    {
        $receipt = $this->row('goods_receipts', $id);
        $this->expect(in_array($receipt['status'], ['received', 'quarantine'], true), 'Only received or quarantined goods can be rejected.');
        $this->pdo->prepare("UPDATE goods_receipts SET status='rejected',quality_status='failed' WHERE id=?")->execute([$id]);
        $this->auditTransition('goods_receipts', $id, (string) $receipt['status'], 'rejected');
        return 'Goods receipt rejected without changing inventory.';
    }

    private function releaseBirdReceipt(int $id): string
    {
        $receipt = $this->row('bird_receipts', $id);
        $this->expect(in_array($receipt['status'], ['quarantine', 'accepted'], true), 'This bird receipt cannot be released.');
        $this->expect($receipt['vet_status'] === 'approved' && trim((string) $receipt['vet_certificate']) !== '', 'Veterinary approval and certificate are required.');
        $this->expect((int) $receipt['bird_count'] > (int) $receipt['mortality_count'], 'No live birds are available for production.');
        if ($receipt['purchase_order_id']) {
            $po = $this->row('purchase_orders', (int) $receipt['purchase_order_id']);
            $this->expect(in_array($po['status'], ['approved', 'partial'], true), 'The linked purchase order is not approved.');
            $this->expect((int) $po['supplier_id'] === (int) $receipt['supplier_id'], 'Receipt supplier does not match the purchase order.');
            $this->pdo->prepare("UPDATE purchase_orders SET status='partial' WHERE id=? AND status='approved'")->execute([$po['id']]);
        }
        $this->pdo->prepare("UPDATE bird_receipts SET status='released' WHERE id=?")->execute([$id]);
        $this->auditTransition('bird_receipts', $id, (string) $receipt['status'], 'released');
        return 'Bird batch released to production.';
    }

    private function rejectBirdReceipt(int $id): string
    {
        $receipt = $this->row('bird_receipts', $id);
        $this->expect(in_array($receipt['status'], ['quarantine', 'accepted'], true), 'This bird receipt can no longer be rejected.');
        $this->pdo->prepare("UPDATE bird_receipts SET status='rejected',vet_status='rejected' WHERE id=?")->execute([$id]);
        $this->auditTransition('bird_receipts', $id, (string) $receipt['status'], 'rejected');
        return 'Bird receipt rejected and blocked from production.';
    }

    private function startProduction(int $id): string
    {
        $batch = $this->row('production_batches', $id);
        $this->expect($batch['status'] === 'scheduled' && $batch['stage'] === 'receiving', 'Only a scheduled receiving batch can start.');
        $receipt = $this->row('bird_receipts', (int) $batch['bird_receipt_id']);
        $this->expect($receipt['status'] === 'released', 'The receiving batch must be veterinary released.');
        $usedBirds = (int) $this->fetchValue("SELECT COALESCE(SUM(birds_input),0) FROM production_batches WHERE bird_receipt_id=? AND id<>? AND status IN ('in_progress','completed','hold')", [$receipt['id'], $id]);
        $this->expect($usedBirds + (int) $batch['birds_input'] <= ((int) $receipt['bird_count'] - (int) $receipt['mortality_count']), 'Production input exceeds the remaining released live-bird count.');
        $this->pdo->prepare("UPDATE production_batches SET status='in_progress',started_at=COALESCE(started_at,NOW()) WHERE id=?")->execute([$id]);
        $this->pdo->prepare("INSERT INTO production_stage_logs (production_batch_id,stage,started_at,quantity_in,operator_id,quality_status) VALUES (?,'receiving',NOW(),?,?,'pending')")
            ->execute([$id, $batch['input_weight_kg'], $this->userId()]);
        $existingCost = (int) $this->fetchValue("SELECT COUNT(*) FROM production_batch_costs WHERE production_batch_id=? AND source_type='bird_receipt' AND source_id=? AND status<>'reversed'", [$id,$receipt['id']]);
        if ($existingCost === 0 && (float) $receipt['total_value'] > 0) {
            $this->pdo->prepare("INSERT INTO production_batch_costs (production_batch_id,cost_component,description,quantity,unit_rate,amount,source_type,source_id,status,created_by) VALUES (?,'live_bird','Released live-bird input',?,?,?,'bird_receipt',?,'actual',?)")
                ->execute([$id,max(1,(float) $receipt['received_quantity']),(float) $receipt['unit_purchase_cost'],(float) $receipt['total_value'],$receipt['id'],$this->userId()]);
        }
        $this->auditTransition('production_batches', $id, 'scheduled', 'in_progress');
        return 'Production started at receiving.';
    }

    private function advanceProduction(int $id, array $input): string
    {
        $batch = $this->row('production_batches', $id);
        $this->expect(in_array($batch['status'], ['in_progress', 'hold'], true), 'This production batch is not active.');
        $quality = (string) ($input['quality_status'] ?? '');
        $this->expect(in_array($quality, ['passed', 'failed', 'hold'], true), 'Select a valid stage quality result.');
        $quantityOut = filter_var($input['quantity_out'] ?? null, FILTER_VALIDATE_FLOAT);
        $this->expect($quantityOut !== false && $quantityOut >= 0 && $quantityOut <= (float) $batch['input_weight_kg'], 'Enter a valid stage output quantity.');
        $temperature = ($input['temperature_c'] ?? '') === '' ? null : filter_var($input['temperature_c'], FILTER_VALIDATE_FLOAT);
        $this->expect($temperature !== false, 'Enter a valid stage temperature.');
        $birdCount = ($input['bird_count'] ?? '') === '' ? null : filter_var($input['bird_count'], FILTER_VALIDATE_INT);
        $this->expect($birdCount !== false && ($birdCount === null || $birdCount >= 0), 'Enter a valid bird count.');
        $notes = mb_substr(trim((string) ($input['notes'] ?? '')), 0, 1500);
        $stage = (string) $batch['stage'];
        $stages = ['receiving','slaughtering','scalding','defeathering','evisceration','chilling','grading','packing','storage','dispatch'];
        $this->expect(in_array($stage, $stages, true), 'Unknown production stage.');
        if ($stage === 'grading') {
            $passedCheck = (int) $this->fetchValue("SELECT COUNT(*) FROM quality_checks WHERE production_batch_id=? AND status IN ('passed','conditional')", [$id]);
            $this->expect($passedCheck > 0, 'A passed quality-control record is required before packing.');
        }
        $log = $this->fetchOne('SELECT * FROM production_stage_logs WHERE production_batch_id=? AND stage=? AND completed_at IS NULL ORDER BY id DESC LIMIT 1 FOR UPDATE', [$id, $stage]);
        if (!$log) {
            $this->pdo->prepare('INSERT INTO production_stage_logs (production_batch_id,stage,started_at,quantity_in,operator_id,quality_status) VALUES (?,?,NOW(),?,?,\'pending\')')
                ->execute([$id, $stage, $batch['input_weight_kg'], $this->userId()]);
            $log = ['id' => (int) $this->pdo->lastInsertId()];
            $log['quantity_in'] = $batch['input_weight_kg'];
        }
        $this->expect($quantityOut <= (float) ($log['quantity_in'] ?? $batch['input_weight_kg']) + 0.001, 'Stage output cannot exceed its recorded stage input.');
        $this->expect($quantityOut + (float) $batch['rejected_weight_kg'] <= (float) $batch['input_weight_kg'] + 0.001, 'Output and rejected weight cannot exceed production input.');
        $this->pdo->prepare('UPDATE production_stage_logs SET completed_at=NOW(),quantity_out=?,temperature_c=?,operator_id=?,quality_status=?,notes=? WHERE id=?')
            ->execute([$quantityOut, $temperature, $this->userId(), $quality, $notes ?: null, $log['id']]);
        if ($stage !== 'dispatch' && !(int) $this->fetchValue('SELECT COUNT(*) FROM production_stage_measurements WHERE production_batch_id=? AND stage=?', [$id,$stage])) {
            $number = 'PSM-AUTO-' . $id . '-' . strtoupper(substr($stage, 0, 8));
            $this->pdo->prepare('INSERT INTO production_stage_measurements (measurement_number,production_batch_id,stage,measured_at,gross_weight_kg,net_weight_kg,scalding_temperature_c,screw_chiller_temperature_c,defeathered_bird_count,packed_bird_count,packed_weight_kg,storage_temperature_c,storage_door_open,notes,recorded_by) VALUES (?,?,?,NOW(),?,?,?,?,?,?,?,?,0,?,?)')
                ->execute([$number,$id,$stage,(float) ($log['quantity_in'] ?? $batch['input_weight_kg']),$quantityOut,$stage==='scalding'?$temperature:null,$stage==='chilling'?$temperature:null,$stage==='defeathering'?($birdCount??0):0,$stage==='packing'?($birdCount??0):0,$stage==='packing'?$quantityOut:0,$stage==='storage'?$temperature:null,'Minimum traceability reading created from governed stage completion. ' . $notes,$this->userId()]);
        }
        if ($quality !== 'passed') {
            $this->pdo->prepare("UPDATE production_batches SET status='hold' WHERE id=?")->execute([$id]);
            AuditService::log('production_hold', 'production_batches', $id, 'Production stage placed on hold.', ['stage' => $stage], ['quality_status' => $quality, 'notes' => $notes]);
            return 'Stage recorded and the batch placed on quality hold.';
        }
        $index = array_search($stage, $stages, true);
        if ($stage === 'dispatch') {
            $totalCost = (float) $this->fetchValue("SELECT COALESCE(SUM(amount),0) FROM production_batch_costs WHERE production_batch_id=? AND status='actual'", [$id]);
            $declaredQuantity = (float) $this->fetchValue("SELECT COALESCE(SUM(quantity),0) FROM production_outputs WHERE production_batch_id=? AND qc_status='released'", [$id]);
            if ($totalCost > 0 && $declaredQuantity > 0) $this->pdo->prepare('UPDATE production_outputs SET unit_cost=? WHERE production_batch_id=? AND qc_status=\'released\' AND unit_cost<=0')->execute([round($totalCost/$declaredQuantity,3),$id]);
            $this->completeProductionOutputs($id, (float) $quantityOut);
            $yield = (float) $batch['input_weight_kg'] > 0 ? round(($quantityOut / (float) $batch['input_weight_kg']) * 100, 2) : 0;
            $this->pdo->prepare("UPDATE production_batches SET status='completed',output_weight_kg=?,yield_percent=?,completed_at=NOW() WHERE id=?")->execute([$quantityOut, $yield, $id]);
            $this->auditTransition('production_batches', $id, 'in_progress', 'completed');
            return 'Production completed; finished lots, stock ledger, packaging consumption, and yield were posted atomically.';
        }
        $next = $stages[$index + 1];
        $this->pdo->prepare("UPDATE production_batches SET status='in_progress',stage=? WHERE id=?")->execute([$next, $id]);
        $this->pdo->prepare("INSERT INTO production_stage_logs (production_batch_id,stage,started_at,quantity_in,operator_id,quality_status) VALUES (?,?,NOW(),?,?,'pending')")
            ->execute([$id, $next, $quantityOut, $this->userId()]);
        AuditService::log('stage_advanced', 'production_batches', $id, 'Production advanced to the next governed stage.', ['stage' => $stage], ['stage' => $next, 'quantity_out' => $quantityOut]);
        return 'Production advanced to ' . ucwords(str_replace('_', ' ', $next)) . '.';
    }

    private function submitSalesOrder(int $id): string
    {
        $order = $this->row('sales_orders', $id);
        $this->expect($order['status'] === 'draft', 'Only a draft sales order can be submitted.');
        $this->recalculateSalesOrder($id);
        $this->expect((int) $this->fetchValue('SELECT COUNT(*) FROM sales_order_items WHERE sales_order_id=?', [$id]) > 0, 'Add at least one sales-order line before submitting.');
        $this->pdo->prepare("UPDATE sales_orders SET status='pending' WHERE id=?")->execute([$id]);
        (new ApprovalService($this->pdo))->request('sales_orders', $id, (float) $order['total_amount'], !empty($order['plant_id']) ? (int) $order['plant_id'] : null);
        $this->auditTransition('sales_orders', $id, 'draft', 'pending');
        return 'Sales order submitted for credit and stock approval.';
    }

    private function approveSalesOrder(int $id): string
    {
        $order = $this->row('sales_orders', $id);
        $this->expect($order['status'] === 'pending', 'Only a pending sales order can be approved.');
        $customer = $this->row('customers', (int) $order['customer_id']);
        $this->expect($customer['status'] === 'active', 'The customer is not active.');
        $exposure = (float) $customer['outstanding_balance'] + (float) $order['total_amount'];
        $this->expect((float) $customer['credit_limit'] <= 0 || $exposure <= (float) $customer['credit_limit'], 'Customer credit limit would be exceeded.');
        if (!(new ApprovalService($this->pdo))->approve('sales_orders', $id)) return 'Approval step recorded. The sales order remains pending for the next configured approver.';
        $items = $this->fetchAll('SELECT * FROM sales_order_items WHERE sales_order_id=? ORDER BY id FOR UPDATE', [$id]);
        $this->expect($items !== [], 'The sales order has no line items.');

        $plans = [];
        $shortages = [];
        $plannedByLot = [];
        foreach ($items as $item) {
            if ($item['inventory_lot_id']) {
                $lots = $this->fetchAll("SELECT il.* FROM inventory_lots il WHERE il.id=? AND il.product_id=? AND il.available_quantity>0 AND il.status IN ('available','allocated') AND (il.expiry_date IS NULL OR il.expiry_date>=CURDATE()) AND NOT EXISTS (SELECT 1 FROM quality_holds qh WHERE qh.inventory_lot_id=il.id AND qh.status='open') FOR UPDATE", [$item['inventory_lot_id'], $item['product_id']]);
            } else {
                $lots = $this->fetchAll("SELECT il.* FROM inventory_lots il WHERE il.product_id=? AND il.available_quantity>0 AND il.status IN ('available','allocated') AND (il.expiry_date IS NULL OR il.expiry_date>=CURDATE()) AND NOT EXISTS (SELECT 1 FROM quality_holds qh WHERE qh.inventory_lot_id=il.id AND qh.status='open') ORDER BY COALESCE(il.expiry_date,'9999-12-31'),il.received_date,il.id FOR UPDATE", [$item['product_id']]);
            }
            $remaining = (float) $item['quantity'];
            $available = 0.0;
            $linePlan = [];
            foreach ($lots as $lot) {
                $effectiveAvailable = max(0, (float) $lot['available_quantity'] - (float) ($plannedByLot[$lot['id']] ?? 0));
                $available += $effectiveAvailable;
                if ($remaining <= 0.0005) continue;
                $allocate = min($remaining, $effectiveAvailable);
                if ($allocate > 0) {
                    $linePlan[] = ['item' => $item, 'lot' => $lot, 'quantity' => round($allocate, 3)];
                    $remaining = round($remaining - $allocate, 3);
                }
            }
            if ($remaining > 0.0005) {
                $shortages[] = ['item' => $item, 'available' => min((float) $item['quantity'], $available), 'shortage' => $remaining];
            } else {
                $plans = array_merge($plans, $linePlan);
                foreach ($linePlan as $planned) $plannedByLot[$planned['lot']['id']] = (float) ($plannedByLot[$planned['lot']['id']] ?? 0) + (float) $planned['quantity'];
            }
        }

        if ($shortages) {
            foreach ($shortages as $shortage) {
                $item = $shortage['item'];
                $number = 'REQ-' . date('Ymd') . '-' . str_pad((string) $item['id'], 6, '0', STR_PAD_LEFT);
                $existing = $this->fetchOne("SELECT id FROM production_requirements WHERE sales_order_id=? AND product_id=? AND status IN ('open','planned','in_progress') LIMIT 1", [$id, $item['product_id']]);
                if ($existing) {
                    $this->pdo->prepare('UPDATE production_requirements SET required_date=?,required_quantity=?,available_quantity=?,shortage_quantity=?,priority=?,updated_at=NOW() WHERE id=?')
                        ->execute([$order['delivery_date'], $item['quantity'], $shortage['available'], $shortage['shortage'], strtotime((string) $order['delivery_date']) <= strtotime('+1 day') ? 'critical' : 'high', $existing['id']]);
                } else {
                    $this->pdo->prepare("INSERT INTO production_requirements (requirement_number,sales_order_id,product_id,plant_id,required_date,required_quantity,available_quantity,shortage_quantity,priority,status,notes) VALUES (?,?,?,?,?,?,?,?,?,'open','Automatically raised by sales stock confirmation')")
                        ->execute([$number, $id, $item['product_id'], $order['plant_id'] ?: null, $order['delivery_date'], $item['quantity'], $shortage['available'], $shortage['shortage'], strtotime((string) $order['delivery_date']) <= strtotime('+1 day') ? 'critical' : 'high']);
                }
            }
            $alertExists = (int) $this->fetchValue("SELECT COUNT(*) FROM alerts WHERE source_type='sales_order' AND source_reference=? AND title='Production requirement raised' AND status IN ('open','acknowledged')", [$order['order_number']]);
            if ($alertExists === 0) {
                $this->pdo->prepare("INSERT INTO alerts (severity,title,message,source_type,source_reference,triggered_at,status) VALUES ('warning','Production requirement raised',?,'sales_order',?,NOW(),'open')")
                    ->execute(['Stock is insufficient for ' . count($shortages) . ' sales-order line(s). Production requirements were created.', $order['order_number']]);
            }
            AuditService::log('stock_shortage', 'sales_orders', $id, 'Sales approval paused and production requirements created.', null, ['shortage_lines' => count($shortages)]);
            return 'Approval paused: stock shortages were detected and production requirements were raised automatically.';
        }

        foreach ($plans as $plan) {
            $item = $plan['item'];
            $lot = $plan['lot'];
            $quantity = (float) $plan['quantity'];
            $currentAvailable = (float) $this->fetchValue('SELECT available_quantity FROM inventory_lots WHERE id=? FOR UPDATE', [$lot['id']]);
            $this->expect($currentAvailable + 0.001 >= $quantity, 'Inventory changed during allocation. Retry approval.');
            $newAvailable = round($currentAvailable - $quantity, 3);
            $lotStatus = $newAvailable <= 0 ? 'depleted' : 'available';
            $this->pdo->prepare('UPDATE inventory_lots SET available_quantity=?,status=? WHERE id=?')->execute([$newAvailable, $lotStatus, $lot['id']]);
            $this->pdo->prepare('INSERT INTO sales_order_allocations (sales_order_item_id,inventory_lot_id,quantity,allocated_by,allocated_at) VALUES (?,?,?,?,NOW())')
                ->execute([$item['id'], $lot['id'], $quantity, $this->userId()]);
            $this->pdo->prepare("INSERT INTO stock_movements (inventory_lot_id,movement_type,quantity,from_zone_id,reference_type,reference_id,reason,moved_by,moved_at) VALUES (?,'allocation',?,?,'sales_order',?,'FEFO sales allocation',?,NOW())")
                ->execute([$lot['id'], $quantity, $lot['storage_zone_id'], $id, $this->userId()]);
            if (empty($item['inventory_lot_id'])) $this->pdo->prepare('UPDATE sales_order_items SET inventory_lot_id=? WHERE id=?')->execute([$lot['id'], $item['id']]);
        }
        $this->pdo->prepare("UPDATE sales_orders SET status='approved',approved_by=? WHERE id=?")->execute([$this->userId(), $id]);
        $pickNumber = 'PICK-' . date('Ymd') . '-' . str_pad((string) $id, 6, '0', STR_PAD_LEFT);
        $this->pdo->prepare("INSERT INTO picking_lists (pick_number,sales_order_id,status) VALUES (?,?,'open')")->execute([$pickNumber, $id]);
        $pickId = (int) $this->pdo->lastInsertId();
        $this->pdo->prepare("INSERT INTO picking_list_items (picking_list_id,sales_order_item_id,inventory_lot_id,requested_quantity,picked_quantity,status) SELECT ?,sales_order_item_id,inventory_lot_id,quantity,0,'open' FROM sales_order_allocations WHERE sales_order_item_id IN (SELECT id FROM sales_order_items WHERE sales_order_id=?)")
            ->execute([$pickId, $id]);
        $this->auditTransition('sales_orders', $id, 'pending', 'approved');
        return 'Sales order approved; FEFO stock was allocated across eligible lots and a picking list was generated.';
    }

    private function cancelSalesOrder(int $id): string
    {
        $order = $this->row('sales_orders', $id);
        $this->expect(in_array($order['status'], ['draft', 'pending', 'approved'], true), 'This sales order can no longer be cancelled.');
        $activeInvoice = (int) $this->fetchValue("SELECT COUNT(*) FROM invoices WHERE sales_order_id=? AND status NOT IN ('draft','cancelled')", [$id]);
        $this->expect($activeInvoice === 0, 'Cancel or settle the issued invoice before cancelling this order.');
        $activeDispatch = (int) $this->fetchValue("SELECT COUNT(*) FROM dispatches WHERE sales_order_id=? AND status NOT IN ('cancelled','delivered')", [$id]);
        $this->expect($activeDispatch === 0, 'Cancel the active dispatch before cancelling this sales order.');
        if ($order['status'] === 'pending') (new ApprovalService($this->pdo))->reject('sales_orders',$id,'Sales order rejected or cancelled from its controlled workflow.');
        if ($order['status'] === 'approved') {
            $allocations = $this->fetchAll("SELECT sm.inventory_lot_id,SUM(sm.quantity) quantity FROM stock_movements sm WHERE sm.reference_type='sales_order' AND sm.reference_id=? AND sm.movement_type='allocation' GROUP BY sm.inventory_lot_id", [$id]);
            foreach ($allocations as $allocation) {
                $lot = $this->row('inventory_lots', (int) $allocation['inventory_lot_id']);
                $newAvailable = min((float) $lot['quantity'], (float) $lot['available_quantity'] + (float) $allocation['quantity']);
                $this->pdo->prepare("UPDATE inventory_lots SET available_quantity=?,status='available' WHERE id=?")->execute([$newAvailable, $lot['id']]);
                $this->pdo->prepare("INSERT INTO stock_movements (inventory_lot_id,movement_type,quantity,to_zone_id,reference_type,reference_id,reason,moved_by,moved_at) VALUES (?,'return',?,?,'sales_order',?,'Allocation released on cancellation',?,NOW())")
                    ->execute([$lot['id'], $allocation['quantity'], $lot['storage_zone_id'], $id, $this->userId()]);
            }
            $this->pdo->prepare('UPDATE sales_order_allocations soa JOIN sales_order_items soi ON soi.id=soa.sales_order_item_id SET soa.released_at=NOW() WHERE soi.sales_order_id=? AND soa.released_at IS NULL')->execute([$id]);
            $this->pdo->prepare("UPDATE picking_lists SET status='cancelled',completed_at=NOW() WHERE sales_order_id=? AND status<>'completed'")->execute([$id]);
        }
        $this->pdo->prepare("UPDATE sales_orders SET status='cancelled' WHERE id=?")->execute([$id]);
        $this->auditTransition('sales_orders', $id, (string) $order['status'], 'cancelled');
        return 'Sales order cancelled and allocated inventory released.';
    }

    private function createInvoice(int $orderId): string
    {
        $order = $this->row('sales_orders', $orderId);
        $this->expect(in_array($order['status'], ['approved', 'partial', 'completed'], true), 'Only an approved order can be invoiced.');
        $existing = (int) $this->fetchValue("SELECT COUNT(*) FROM invoices WHERE sales_order_id=? AND status<>'cancelled'", [$orderId]);
        $this->expect($existing === 0, 'This sales order already has an active invoice.');
        $customer = $this->row('customers', (int) $order['customer_id']);
        $number = 'INV-' . date('Ymd') . '-' . str_pad((string) $orderId, 5, '0', STR_PAD_LEFT);
        $due = date('Y-m-d', strtotime('+' . max(0, (int) $customer['payment_terms_days']) . ' days'));
        $insert = $this->pdo->prepare("INSERT INTO invoices (invoice_number,sales_order_id,customer_id,invoice_date,due_date,subtotal,tax_amount,total_amount,paid_amount,balance_amount,status,notes,created_by) VALUES (?,?,?,CURDATE(),?,?,?,?,0,?,'draft','Generated from governed sales order',?)");
        $insert->execute([$number, $orderId, $order['customer_id'], $due, $order['subtotal'] - $order['discount_amount'], $order['tax_amount'], $order['total_amount'], $order['total_amount'], $this->userId()]);
        $invoiceId = (int) $this->pdo->lastInsertId();
        AuditService::log('created', 'invoices', $invoiceId, 'Draft invoice generated from sales order.', null, ['sales_order_id' => $orderId, 'total_amount' => $order['total_amount']]);
        return "Draft invoice {$number} created. Review and issue it from Finance.";
    }

    private function issueInvoice(int $id): string
    {
        $invoice = $this->row('invoices', $id);
        $this->expect($invoice['status'] === 'draft', 'Only a draft invoice can be issued.');
        $order = $this->row('sales_orders', (int) $invoice['sales_order_id']);
        $this->expect(in_array($order['status'], ['approved', 'partial', 'completed'], true), 'The source sales order is not approved.');
        $this->postJournal('invoice', $id, (string) $invoice['invoice_number'], (string) $invoice['invoice_date'], [
            ['1100', (float) $invoice['total_amount'], 0.0, 'Customer receivable'],
            ['4000', 0.0, (float) $invoice['subtotal'], 'Poultry sales revenue'],
            ['2100', 0.0, (float) $invoice['tax_amount'], 'Output GST payable'],
        ]);
        $this->pdo->prepare("UPDATE invoices SET status='issued',balance_amount=total_amount WHERE id=?")->execute([$id]);
        $this->refreshCustomerBalances((int) $invoice['customer_id']);
        $this->auditTransition('invoices', $id, 'draft', 'issued');
        return 'Invoice issued and posted to the general ledger.';
    }

    private function cancelInvoice(int $id): string
    {
        $invoice = $this->row('invoices', $id);
        $this->expect($invoice['status'] === 'draft', 'Only an unposted draft invoice can be cancelled.');
        $this->pdo->prepare("UPDATE invoices SET status='cancelled',balance_amount=0 WHERE id=?")->execute([$id]);
        $this->auditTransition('invoices', $id, 'draft', 'cancelled');
        return 'Draft invoice cancelled.';
    }

    private function clearPayment(int $id): string
    {
        $payment = $this->row('payments', $id);
        $this->expect($payment['status'] === 'pending', 'Only a pending payment can be cleared.');
        $this->expect(!empty($payment['invoice_id']), 'Apply the payment to an invoice before clearing it.');
        $invoice = $this->row('invoices', (int) $payment['invoice_id']);
        $this->expect(in_array($invoice['status'], ['issued', 'partial', 'overdue'], true), 'The linked invoice is not open for payment.');
        $this->expect((int) $payment['customer_id'] === (int) $invoice['customer_id'], 'Payment customer does not match the invoice.');
        $this->expect((float) $payment['amount'] <= (float) $invoice['balance_amount'] + 0.001, 'Payment exceeds the invoice balance.');
        $this->postJournal('payment', $id, (string) $payment['payment_number'], (string) $payment['payment_date'], [
            ['1000', (float) $payment['amount'], 0.0, 'Cash or bank received'],
            ['1100', 0.0, (float) $payment['amount'], 'Customer receivable settled'],
        ]);
        $this->adjustBank(!empty($payment['company_bank_account_id']) ? (int) $payment['company_bank_account_id'] : null, (float) $payment['amount']);
        $this->pdo->prepare("UPDATE payments SET status='cleared' WHERE id=?")->execute([$id]);
        $this->refreshInvoice((int) $payment['invoice_id']);
        $this->refreshCustomerBalances((int) $payment['customer_id']);
        $this->auditTransition('payments', $id, 'pending', 'cleared');
        return 'Payment cleared; invoice, customer balance, and ledger were updated.';
    }

    private function reversePayment(int $id): string
    {
        $payment = $this->row('payments', $id);
        $this->expect($payment['status'] === 'cleared', 'Only a cleared payment can be reversed.');
        $this->postJournal('payment_reversal', $id, 'REV-' . $payment['payment_number'], date('Y-m-d'), [
            ['1100', (float) $payment['amount'], 0.0, 'Restore customer receivable'],
            ['1000', 0.0, (float) $payment['amount'], 'Reverse cash or bank receipt'],
        ]);
        $this->adjustBank(!empty($payment['company_bank_account_id']) ? (int) $payment['company_bank_account_id'] : null, -(float) $payment['amount']);
        $this->pdo->prepare("UPDATE payments SET status='reversed' WHERE id=?")->execute([$id]);
        if ($payment['invoice_id']) {
            $this->refreshInvoice((int) $payment['invoice_id']);
        }
        $this->refreshCustomerBalances((int) $payment['customer_id']);
        $this->auditTransition('payments', $id, 'cleared', 'reversed');
        return 'Payment reversed with a balanced reversal journal.';
    }

    private function approveSupplierInvoice(int $id): string
    {
        $invoice = $this->row('supplier_invoices', $id);
        $this->expect($invoice['status'] === 'draft', 'Only a draft supplier invoice can be approved.');
        $supplier = $this->row('suppliers', (int) $invoice['supplier_id']);
        $this->expect($supplier['status'] === 'active' && $supplier['approval_status'] === 'approved', 'The supplier must be active and approved.');
        $this->expect(!empty($invoice['goods_receipt_id']), 'An accepted goods receipt is required.');
        $receipt = $this->row('goods_receipts', (int) $invoice['goods_receipt_id']);
        $this->expect($receipt['status'] === 'accepted', 'The linked goods receipt has not been accepted.');
        $this->expect((int) $receipt['supplier_id'] === (int) $invoice['supplier_id'], 'Supplier invoice does not match the goods receipt supplier.');
        $this->expect(abs((float) $receipt['total_amount'] - (float) $invoice['subtotal']) < 0.01, 'Supplier invoice subtotal must match the accepted receipt value.');
        $duplicate = (int) $this->fetchValue("SELECT COUNT(*) FROM supplier_invoices WHERE goods_receipt_id=? AND id<>? AND status<>'cancelled'", [$invoice['goods_receipt_id'], $id]);
        $this->expect($duplicate === 0, 'The goods receipt already has an active supplier invoice.');
        $this->postJournal('supplier_invoice', $id, (string) $invoice['invoice_number'], (string) $invoice['invoice_date'], [
            ['2050', (float) $invoice['subtotal'], 0.0, 'Clear goods-received accrual'],
            ['1400', (float) $invoice['tax_amount'], 0.0, 'Recoverable input GST'],
            ['2000', 0.0, (float) $invoice['total_amount'], 'Supplier payable'],
        ]);
        $this->pdo->prepare("UPDATE supplier_invoices SET status='approved',approved_by=?,paid_amount=0,balance_amount=total_amount WHERE id=?")->execute([$this->userId(), $id]);
        $this->refreshSupplierBalance((int) $invoice['supplier_id']);
        $this->auditTransition('supplier_invoices', $id, 'draft', 'approved');
        return 'Supplier invoice approved; GRNI, input GST, and Accounts Payable were posted.';
    }

    private function cancelSupplierInvoice(int $id): string
    {
        $invoice = $this->row('supplier_invoices', $id);
        $this->expect($invoice['status'] === 'draft', 'Only an unposted supplier invoice can be cancelled.');
        $this->pdo->prepare("UPDATE supplier_invoices SET status='cancelled',balance_amount=0 WHERE id=?")->execute([$id]);
        $this->auditTransition('supplier_invoices', $id, 'draft', 'cancelled');
        return 'Draft supplier invoice cancelled.';
    }

    private function clearSupplierPayment(int $id): string
    {
        $payment = $this->row('supplier_payments', $id);
        $this->expect($payment['status'] === 'pending', 'Only a pending supplier payment can be cleared.');
        $invoice = $this->row('supplier_invoices', (int) $payment['supplier_invoice_id']);
        $this->expect(in_array($invoice['status'], ['approved', 'partial', 'overdue'], true), 'The supplier invoice is not open for payment.');
        $this->expect((int) $invoice['supplier_id'] === (int) $payment['supplier_id'], 'Payment supplier does not match the invoice.');
        $this->expect((float) $payment['amount'] <= (float) $invoice['balance_amount'] + 0.001, 'Payment exceeds the supplier invoice balance.');
        $this->postJournal('supplier_payment', $id, (string) $payment['payment_number'], (string) $payment['payment_date'], [
            ['2000', (float) $payment['amount'], 0.0, 'Supplier payable settled'],
            ['1000', 0.0, (float) $payment['amount'], 'Cash or bank paid'],
        ]);
        $this->adjustBank(!empty($payment['company_bank_account_id']) ? (int) $payment['company_bank_account_id'] : null, -(float) $payment['amount']);
        $this->pdo->prepare("UPDATE supplier_payments SET status='cleared' WHERE id=?")->execute([$id]);
        $this->refreshSupplierInvoice((int) $payment['supplier_invoice_id']);
        $this->refreshSupplierBalance((int) $payment['supplier_id']);
        $this->auditTransition('supplier_payments', $id, 'pending', 'cleared');
        return 'Supplier payment cleared and posted to Accounts Payable.';
    }

    private function reverseSupplierPayment(int $id): string
    {
        $payment = $this->row('supplier_payments', $id);
        $this->expect($payment['status'] === 'cleared', 'Only a cleared supplier payment can be reversed.');
        $this->postJournal('supplier_payment_reversal', $id, 'REV-' . $payment['payment_number'], date('Y-m-d'), [
            ['1000', (float) $payment['amount'], 0.0, 'Restore cash or bank'],
            ['2000', 0.0, (float) $payment['amount'], 'Restore supplier payable'],
        ]);
        $this->adjustBank(!empty($payment['company_bank_account_id']) ? (int) $payment['company_bank_account_id'] : null, (float) $payment['amount']);
        $this->pdo->prepare("UPDATE supplier_payments SET status='reversed' WHERE id=?")->execute([$id]);
        $this->refreshSupplierInvoice((int) $payment['supplier_invoice_id']);
        $this->refreshSupplierBalance((int) $payment['supplier_id']);
        $this->auditTransition('supplier_payments', $id, 'cleared', 'reversed');
        return 'Supplier payment reversed with a balanced journal.';
    }

    private function approveOperatingExpense(int $id): string
    {
        $expense = $this->row('operating_expenses', $id);
        $this->expect($expense['status'] === 'draft', 'Only a draft operating expense can be approved.');
        $this->expect((float) $expense['total_amount'] > 0, 'Expense total must be greater than zero.');
        $this->pdo->prepare("UPDATE operating_expenses SET status='approved',approved_by=? WHERE id=?")->execute([$this->userId(), $id]);
        $this->auditTransition('operating_expenses', $id, 'draft', 'approved');
        return 'Operating expense approved for payment.';
    }

    private function payOperatingExpense(int $id): string
    {
        $expense = $this->row('operating_expenses', $id);
        $this->expect($expense['status'] === 'approved', 'Only an approved expense can be paid.');
        if ($expense['payment_method'] !== 'cash') $this->expect(!empty($expense['company_bank_account_id']), 'Select the paying bank or card account before payment.');
        $this->postJournal('operating_expense', $id, (string) $expense['expense_number'], (string) $expense['expense_date'], [
            ['5300', (float) $expense['taxable_amount'], 0.0, 'Operating expense'],
            ['1400', (float) $expense['gst_amount'], 0.0, 'Recoverable input GST'],
            ['1000', 0.0, (float) $expense['total_amount'], 'Cash or bank paid'],
        ]);
        $this->adjustBank(!empty($expense['company_bank_account_id']) ? (int) $expense['company_bank_account_id'] : null, -(float) $expense['total_amount']);
        $this->pdo->prepare("UPDATE operating_expenses SET status='paid',paid_at=NOW() WHERE id=?")->execute([$id]);
        $this->auditTransition('operating_expenses', $id, 'approved', 'paid');
        return 'Expense paid and posted to the general ledger.';
    }

    private function reverseOperatingExpense(int $id, array $input): string
    {
        $expense = $this->row('operating_expenses', $id);
        $this->expect($expense['status'] === 'paid', 'Only a paid expense can be reversed.');
        $reason = $this->reversalReason($input);
        $this->postJournal('operating_expense_reversal', $id, 'REV-' . $expense['expense_number'], date('Y-m-d'), [
            ['1000', (float) $expense['total_amount'], 0.0, 'Restore cash or bank'],
            ['5300', 0.0, (float) $expense['taxable_amount'], 'Reverse operating expense'],
            ['1400', 0.0, (float) $expense['gst_amount'], 'Reverse recoverable input GST'],
        ]);
        $this->adjustBank(!empty($expense['company_bank_account_id']) ? (int) $expense['company_bank_account_id'] : null, (float) $expense['total_amount']);
        $this->pdo->prepare("UPDATE operating_expenses SET status='reversed',reversed_at=NOW(),reversal_reason=? WHERE id=?")->execute([$reason, $id]);
        $this->auditTransition('operating_expenses', $id, 'paid', 'reversed');
        return 'Expense reversed with an equal and opposite journal.';
    }

    private function cancelOperatingExpense(int $id): string
    {
        $expense = $this->row('operating_expenses', $id);
        $this->expect(in_array($expense['status'], ['draft','approved'], true), 'Paid expenses must be reversed, not cancelled.');
        $this->pdo->prepare("UPDATE operating_expenses SET status='cancelled' WHERE id=?")->execute([$id]);
        $this->auditTransition('operating_expenses', $id, (string) $expense['status'], 'cancelled');
        return 'Operating expense cancelled before posting.';
    }

    private function postPartnerTransaction(int $id): string
    {
        $transaction = $this->row('partner_transactions', $id);
        $this->expect($transaction['status'] === 'draft', 'Only a draft partner transaction can be posted.');
        $this->expect((float) $transaction['amount'] > 0, 'Partner transaction amount must be greater than zero.');
        $incoming = in_array($transaction['transaction_type'], ['installment','share_addition','other'], true);
        if (!$incoming) {
            $shareholder = $this->row('shareholders', (int) $transaction['shareholder_id']);
            $this->expect((float) $transaction['amount'] <= (float) $shareholder['received_share'] + 0.001, 'A deduction, refund, or settlement cannot exceed the partner share actually received.');
            if (in_array($transaction['transaction_type'], ['share_deduction','cancellation_settlement'], true)) {
                $this->expect((float) $transaction['amount'] <= (float) $shareholder['share_amount'] + 0.001, 'A share reduction cannot exceed the partner total share amount.');
            }
        }
        if (!$incoming || $transaction['payment_method'] !== 'cash') $this->expect(!empty($transaction['company_bank_account_id']), 'Select the receiving or paying bank account.');
        $lines = $incoming
            ? [['1000',(float) $transaction['amount'],0.0,'Partner funds received'],['3000',0.0,(float) $transaction['amount'],'Partner capital credited']]
            : [['3000',(float) $transaction['amount'],0.0,'Partner capital reduced'],['1000',0.0,(float) $transaction['amount'],'Partner refund paid']];
        $this->postJournal('partner_transaction', $id, (string) $transaction['transaction_number'], (string) $transaction['transaction_date'], $lines);
        $this->applyPartnerShareEffect($transaction, 1);
        $this->adjustBank(!empty($transaction['company_bank_account_id']) ? (int) $transaction['company_bank_account_id'] : null, $incoming ? (float) $transaction['amount'] : -(float) $transaction['amount']);
        $this->pdo->prepare("UPDATE partner_transactions SET status='posted',posted_by=? WHERE id=?")->execute([$this->userId(), $id]);
        $this->auditTransition('partner_transactions', $id, 'draft', 'posted');
        return 'Partner transaction posted; capital, bank, and partner balances were updated.';
    }

    private function reversePartnerTransaction(int $id, array $input): string
    {
        $transaction = $this->row('partner_transactions', $id);
        $this->expect($transaction['status'] === 'posted', 'Only a posted partner transaction can be reversed.');
        $incoming = in_array($transaction['transaction_type'], ['installment','share_addition','other'], true);
        $lines = $incoming
            ? [['3000',(float) $transaction['amount'],0.0,'Reverse partner capital'],['1000',0.0,(float) $transaction['amount'],'Reverse partner receipt']]
            : [['1000',(float) $transaction['amount'],0.0,'Restore cash or bank'],['3000',0.0,(float) $transaction['amount'],'Restore partner capital']];
        $this->postJournal('partner_transaction_reversal', $id, 'REV-' . $transaction['transaction_number'], date('Y-m-d'), $lines);
        $this->applyPartnerShareEffect($transaction, -1);
        $this->adjustBank(!empty($transaction['company_bank_account_id']) ? (int) $transaction['company_bank_account_id'] : null, $incoming ? -(float) $transaction['amount'] : (float) $transaction['amount']);
        $this->pdo->prepare("UPDATE partner_transactions SET status='reversed',reversed_at=NOW(),reversal_reason=? WHERE id=?")->execute([$this->reversalReason($input), $id]);
        $this->auditTransition('partner_transactions', $id, 'posted', 'reversed');
        return 'Partner transaction reversed without changing the original posting.';
    }

    private function cancelPartnerTransaction(int $id): string
    {
        $transaction = $this->row('partner_transactions', $id);
        $this->expect($transaction['status'] === 'draft', 'Only a draft partner transaction can be cancelled.');
        $this->pdo->prepare("UPDATE partner_transactions SET status='cancelled' WHERE id=?")->execute([$id]);
        $this->auditTransition('partner_transactions', $id, 'draft', 'cancelled');
        return 'Draft partner transaction cancelled.';
    }

    private function approvePartnerDividend(int $id): string
    {
        $dividend = $this->row('partner_dividends', $id);
        $this->expect($dividend['status'] === 'declared', 'Only a declared dividend can be approved.');
        $this->expect((float) $dividend['net_amount'] > 0, 'Net dividend must be greater than zero.');
        $this->pdo->prepare("UPDATE partner_dividends SET status='approved',approved_by=? WHERE id=?")->execute([$this->userId(), $id]);
        $this->auditTransition('partner_dividends', $id, 'declared', 'approved');
        return 'Partner dividend approved for payment.';
    }

    private function payPartnerDividend(int $id): string
    {
        $dividend = $this->row('partner_dividends', $id);
        $this->expect($dividend['status'] === 'approved', 'Only an approved dividend can be paid.');
        $this->expect(!empty($dividend['company_bank_account_id']), 'Select the paying bank account.');
        $this->postJournal('partner_dividend', $id, (string) $dividend['dividend_number'], date('Y-m-d'), [
            ['3000',(float) $dividend['gross_amount'],0.0,'Dividend distribution'],
            ['1000',0.0,(float) $dividend['net_amount'],'Net dividend paid'],
            ['2200',0.0,(float) $dividend['tds_amount'],'Dividend TDS payable'],
        ]);
        $this->adjustBank((int) $dividend['company_bank_account_id'], -(float) $dividend['net_amount']);
        $this->pdo->prepare("UPDATE partner_dividends SET status='paid',paid_date=CURDATE() WHERE id=?")->execute([$id]);
        $this->auditTransition('partner_dividends', $id, 'approved', 'paid');
        return 'Dividend paid and posted with TDS separation.';
    }

    private function reversePartnerDividend(int $id, array $input): string
    {
        $dividend = $this->row('partner_dividends', $id);
        $this->expect($dividend['status'] === 'paid', 'Only a paid dividend can be reversed.');
        $this->postJournal('partner_dividend_reversal', $id, 'REV-' . $dividend['dividend_number'], date('Y-m-d'), [
            ['1000',(float) $dividend['net_amount'],0.0,'Restore dividend payment'],
            ['2200',(float) $dividend['tds_amount'],0.0,'Reverse dividend TDS'],
            ['3000',0.0,(float) $dividend['gross_amount'],'Reverse dividend distribution'],
        ]);
        $this->adjustBank((int) $dividend['company_bank_account_id'], (float) $dividend['net_amount']);
        $this->pdo->prepare("UPDATE partner_dividends SET status='reversed' WHERE id=?")->execute([$id]);
        AuditService::log('workflow_transition', 'partner_dividends', $id, $this->reversalReason($input), ['status'=>'paid'], ['status'=>'reversed']);
        return 'Dividend reversed with an equal and opposite journal.';
    }

    private function cancelPartnerDividend(int $id): string
    {
        $dividend = $this->row('partner_dividends', $id);
        $this->expect(in_array($dividend['status'], ['declared','approved'], true), 'Paid dividends must be reversed, not cancelled.');
        $this->pdo->prepare("UPDATE partner_dividends SET status='cancelled' WHERE id=?")->execute([$id]);
        $this->auditTransition('partner_dividends', $id, (string) $dividend['status'], 'cancelled');
        return 'Dividend declaration cancelled before payment.';
    }

    private function approveSalaryAdvance(int $id): string
    {
        $advance = $this->row('salary_advances', $id);
        $this->expect($advance['status'] === 'requested', 'Only a requested salary advance can be approved.');
        $approved = (float) $advance['approved_amount'] > 0 ? (float) $advance['approved_amount'] : (float) $advance['amount'];
        $this->expect($approved > 0 && $approved <= (float) $advance['amount'], 'Approved amount must be positive and cannot exceed the request.');
        $this->expect((float) $advance['monthly_recovery'] > 0, 'Set the monthly recovery amount before approval.');
        $this->pdo->prepare("UPDATE salary_advances SET approved_amount=?,balance_amount=?,status='approved',approved_by=? WHERE id=?")->execute([$approved,$approved,$this->userId(),$id]);
        $this->auditTransition('salary_advances', $id, 'requested', 'approved');
        return 'Salary advance approved for controlled payment.';
    }

    private function paySalaryAdvance(int $id): string
    {
        $advance = $this->row('salary_advances', $id);
        $this->expect($advance['status'] === 'approved', 'Only an approved salary advance can be paid.');
        $this->expect(!empty($advance['company_bank_account_id']), 'Select the paying bank account.');
        $this->postJournal('salary_advance', $id, (string) $advance['advance_number'], date('Y-m-d'), [
            ['1600',(float) $advance['approved_amount'],0.0,'Employee salary advance receivable'],
            ['1000',0.0,(float) $advance['approved_amount'],'Salary advance paid'],
        ]);
        $this->adjustBank((int) $advance['company_bank_account_id'], -(float) $advance['approved_amount']);
        $this->pdo->prepare("UPDATE salary_advances SET status='paid',paid_at=NOW(),balance_amount=approved_amount WHERE id=?")->execute([$id]);
        $this->auditTransition('salary_advances', $id, 'approved', 'paid');
        return 'Salary advance paid and recorded as an employee receivable.';
    }

    private function reverseSalaryAdvance(int $id, array $input): string
    {
        $advance = $this->row('salary_advances', $id);
        $this->expect(in_array($advance['status'], ['paid','recovering'], true), 'Only a paid salary advance can be reversed.');
        $this->expect((float) $advance['recovered_amount'] <= 0.001, 'An advance with payroll recoveries cannot be reversed; post an HR adjustment instead.');
        $this->postJournal('salary_advance_reversal', $id, 'REV-' . $advance['advance_number'], date('Y-m-d'), [
            ['1000',(float) $advance['approved_amount'],0.0,'Restore bank balance'],
            ['1600',0.0,(float) $advance['approved_amount'],'Reverse salary advance receivable'],
        ]);
        $this->adjustBank((int) $advance['company_bank_account_id'], (float) $advance['approved_amount']);
        $this->pdo->prepare("UPDATE salary_advances SET status='cancelled',balance_amount=0 WHERE id=?")->execute([$id]);
        AuditService::log('workflow_transition', 'salary_advances', $id, $this->reversalReason($input), ['status'=>$advance['status']], ['status'=>'cancelled']);
        return 'Salary advance payment reversed and the employee receivable cleared.';
    }

    private function cancelSalaryAdvance(int $id): string
    {
        $advance = $this->row('salary_advances', $id);
        $this->expect(in_array($advance['status'], ['requested','approved'], true), 'Paid salary advances must use the reversal action.');
        $this->pdo->prepare("UPDATE salary_advances SET status='cancelled',balance_amount=0 WHERE id=?")->execute([$id]);
        $this->auditTransition('salary_advances', $id, (string) $advance['status'], 'cancelled');
        return 'Salary advance cancelled before payment.';
    }

    private function postAssetDepreciation(int $id): string
    {
        $entry=$this->row('asset_depreciation_entries',$id);
        $this->expect($entry['status']==='draft','Only a draft depreciation entry can be posted.');
        $asset=$this->row('assets',(int)$entry['asset_id']);
        $this->expect((float)$entry['depreciation_amount']>0,'Depreciation amount must be greater than zero.');
        $this->postJournal('asset_depreciation',$id,'DEP-'.$asset['asset_code'],(string)$entry['period_end'],[
            ['5400',(float)$entry['depreciation_amount'],0.0,'Depreciation expense'],['1550',0.0,(float)$entry['depreciation_amount'],'Accumulated depreciation'],
        ]);
        $journalId=$this->fetchValue("SELECT id FROM journal_entries WHERE reference_type='asset_depreciation' AND reference_id=? AND status='posted' ORDER BY id DESC LIMIT 1",[$id]);
        $this->pdo->prepare('UPDATE assets SET accumulated_depreciation=LEAST(acquisition_cost-residual_value,accumulated_depreciation+?) WHERE id=?')->execute([$entry['depreciation_amount'],$asset['id']]);
        $this->pdo->prepare("UPDATE asset_depreciation_entries SET status='posted',journal_entry_id=?,created_by=COALESCE(created_by,?) WHERE id=?")->execute([$journalId,$this->userId(),$id]);
        $this->auditTransition('asset_depreciation_entries',$id,'draft','posted');
        return 'Asset depreciation posted to expense and accumulated depreciation.';
    }

    private function reverseAssetDepreciation(int $id, array $input): string
    {
        $entry=$this->row('asset_depreciation_entries',$id);
        $this->expect($entry['status']==='posted','Only a posted depreciation entry can be reversed.');
        $asset=$this->row('assets',(int)$entry['asset_id']);
        $this->postJournal('asset_depreciation_reversal',$id,'REV-DEP-'.$asset['asset_code'],date('Y-m-d'),[
            ['1550',(float)$entry['depreciation_amount'],0.0,'Reverse accumulated depreciation'],['5400',0.0,(float)$entry['depreciation_amount'],'Reverse depreciation expense'],
        ]);
        $this->pdo->prepare('UPDATE assets SET accumulated_depreciation=GREATEST(0,accumulated_depreciation-?) WHERE id=?')->execute([$entry['depreciation_amount'],$asset['id']]);
        $this->pdo->prepare("UPDATE asset_depreciation_entries SET status='reversed' WHERE id=?")->execute([$id]);
        AuditService::log('workflow_transition','asset_depreciation_entries',$id,$this->reversalReason($input),['status'=>'posted'],['status'=>'reversed']);
        return 'Depreciation reversed through a separate journal.';
    }

    private function updateDispatch(int $id, string $status, array $input): string
    {
        $dispatch = $this->row('dispatches', $id);
        $allowed = [
            'loading' => ['scheduled', 'delayed'],
            'in_transit' => ['loading', 'delayed'],
            'delivered' => ['in_transit', 'delayed'],
            'cancelled' => ['scheduled', 'loading', 'delayed'],
        ];
        $this->expect(in_array($dispatch['status'], $allowed[$status] ?? [], true), 'That dispatch transition is not allowed.');
        if (in_array($status, ['loading','in_transit'], true)) {
            $vehicleReady = (int) $this->fetchValue("SELECT COUNT(*) FROM vehicles WHERE id=? AND status='active'", [$dispatch['vehicle_id']]);
            $driverReady = (int) $this->fetchValue("SELECT COUNT(*) FROM employees WHERE id=? AND department='Logistics' AND status='active'", [$dispatch['driver_id']]);
            $this->expect($vehicleReady === 1 && $driverReady === 1, 'Dispatch requires an active vehicle and active logistics driver.');
            $this->ensureDispatchChecklist($id);
        }
        if ($status === 'in_transit') {
            $missing = (int) $this->fetchValue('SELECT COUNT(*) FROM dispatch_checks WHERE dispatch_id=? AND is_mandatory=1 AND passed=0', [$id]);
            $this->expect($missing === 0, 'Complete every mandatory pre-dispatch checklist item before releasing the vehicle.');
        }
        $pod = mb_substr(trim((string) ($input['pod_reference'] ?? '')), 0, 100);
        if ($status === 'delivered') {
            $this->expect($pod !== '', 'Proof of delivery is required.');
        }
        $temperature = ($input['temperature_c'] ?? '') === '' ? $dispatch['temperature_c'] : filter_var($input['temperature_c'], FILTER_VALIDATE_FLOAT);
        $this->expect($temperature !== false && $temperature >= -40 && $temperature <= 30, 'Enter a valid cold-chain temperature between -40C and 30C.');
        $deliveryTemperature = $status === 'delivered' ? $temperature : $dispatch['delivery_temperature_c'];
        $sql = 'UPDATE dispatches SET status=?,temperature_c=CASE WHEN ?=\'delivered\' THEN temperature_c ELSE ? END,delivery_temperature_c=?,delivery_temperature_recorded_at=CASE WHEN ?=\'delivered\' THEN NOW() ELSE delivery_temperature_recorded_at END,pod_reference=COALESCE(NULLIF(?,\'\'),pod_reference),actual_departure=CASE WHEN ?=\'in_transit\' AND actual_departure IS NULL THEN NOW() ELSE actual_departure END,delivered_at=CASE WHEN ?=\'delivered\' AND delivered_at IS NULL THEN NOW() ELSE delivered_at END WHERE id=?';
        $this->pdo->prepare($sql)->execute([$status, $status, $temperature, $deliveryTemperature, $status, $pod, $status, $status, $id]);
        if ($status === 'delivered') {
            $this->finalizeDispatch($id);
        }
        $this->auditTransition('dispatches', $id, (string) $dispatch['status'], $status);
        return 'Dispatch updated to ' . ucwords(str_replace('_', ' ', $status)) . '.';
    }

    private function ensureDispatchChecklist(int $dispatchId): void
    {
        $defaults = [
            'vehicle_available'=>'Vehicle available and assigned','driver_assigned'=>'Driver assigned and documents verified','vehicle_condition'=>'Vehicle condition acceptable',
            'refrigeration'=>'Refrigeration operating','temperature'=>'Load temperature within limit','fuel'=>'Fuel level sufficient','documents'=>'Delivery documents complete',
            'cleanliness'=>'Cargo area clean and sanitized','loading_condition'=>'Load secured and loading condition accepted',
        ];
        $insert = $this->pdo->prepare('INSERT IGNORE INTO dispatch_checks (dispatch_id,check_code,label,is_mandatory,passed) VALUES (?,?,?,1,0)');
        foreach ($defaults as $code=>$label) $insert->execute([$dispatchId,$code,$label]);
    }

    private function savePurchaseLine(int $orderId, array $input): string
    {
        $order = $this->row('purchase_orders', $orderId);
        $this->expect($order['status'] === 'draft', 'Purchase lines can only be changed in draft.');
        $description = mb_substr(trim((string) ($input['description'] ?? '')), 0, 255);
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
        $unitPrice = filter_var($input['unit_price'] ?? null, FILTER_VALIDATE_FLOAT);
        $unit = mb_substr(trim((string) ($input['unit'] ?? '')), 0, 20);
        $productId = (int) ($input['product_id'] ?? 0) ?: null;
        $this->expect($description !== '' && $unit !== '', 'Description and unit are required.');
        $this->expect($quantity !== false && $quantity > 0 && $unitPrice !== false && $unitPrice >= 0, 'Enter valid quantity and unit price values.');
        if ($productId) {
            $this->expect((int) $this->fetchValue("SELECT COUNT(*) FROM products WHERE id=? AND status='active'", [$productId]) === 1, 'Select an active product.');
        }
        $total = round($quantity * $unitPrice, 2);
        $this->pdo->prepare('INSERT INTO purchase_order_items (purchase_order_id,product_id,description,quantity,unit,unit_price,total_price) VALUES (?,?,?,?,?,?,?)')
            ->execute([$orderId, $productId, $description, $quantity, $unit, $unitPrice, $total]);
        $this->recalculatePurchaseOrder($orderId);
        AuditService::log('line_added', 'purchase_orders', $orderId, 'Purchase order line added.', null, ['description' => $description, 'quantity' => $quantity, 'total_price' => $total]);
        return 'Purchase line added and order total recalculated.';
    }

    private function saveGoodsReceiptLine(int $receiptId, array $input): string
    {
        $receipt = $this->row('goods_receipts', $receiptId);
        $this->expect($receipt['status'] === 'draft', 'Receipt lines can only be changed in draft.');
        $poItemId = (int) ($input['purchase_order_item_id'] ?? 0) ?: null;
        $productId = (int) ($input['product_id'] ?? 0);
        $zoneId = (int) ($input['storage_zone_id'] ?? 0);
        $description = mb_substr(trim((string) ($input['description'] ?? '')), 0, 255);
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
        $unitCost = filter_var($input['unit_cost'] ?? null, FILTER_VALIDATE_FLOAT);
        $unit = mb_substr(trim((string) ($input['unit'] ?? '')), 0, 20);
        $lot = mb_substr(trim((string) ($input['lot_number'] ?? '')), 0, 60);
        $expiry = trim((string) ($input['expiry_date'] ?? '')) ?: null;
        $this->expect($productId > 0 && $zoneId > 0 && $description !== '' && $unit !== '' && $lot !== '', 'Product, zone, description, unit, and lot number are required.');
        $this->expect($quantity !== false && $quantity > 0 && $unitCost !== false && $unitCost >= 0, 'Enter valid receipt quantity and unit cost.');
        $this->expect((int) $this->fetchValue("SELECT COUNT(*) FROM products WHERE id=? AND status='active'", [$productId]) === 1, 'Select an active product.');
        $this->expect((int) $this->fetchValue("SELECT COUNT(*) FROM storage_zones WHERE id=? AND status='active'", [$zoneId]) === 1, 'Select an active storage zone.');
        $this->expect((int) $this->fetchValue('SELECT COUNT(*) FROM inventory_lots WHERE lot_number=?', [$lot]) === 0, 'That lot number already exists.');
        if ($poItemId) {
            $poItem = $this->fetchOne('SELECT * FROM purchase_order_items WHERE id=? AND purchase_order_id=?', [$poItemId, $receipt['purchase_order_id']]);
            $this->expect($poItem !== null && (int) $poItem['product_id'] === $productId, 'Receipt line does not match the selected purchase-order line.');
            $accepted = (float) $this->fetchValue("SELECT COALESCE(SUM(gri.quantity),0) FROM goods_receipt_items gri JOIN goods_receipts gr ON gr.id=gri.goods_receipt_id WHERE gri.purchase_order_item_id=? AND gr.status='accepted'", [$poItemId]);
            $this->expect($accepted + $quantity <= (float) $poItem['quantity'] + 0.001, 'Receipt quantity exceeds the remaining purchase-order quantity.');
        } elseif ($receipt['purchase_order_id']) {
            throw new \RuntimeException('Select the matching purchase-order line for every linked receipt item.', 422);
        }
        $this->pdo->prepare('INSERT INTO goods_receipt_items (goods_receipt_id,purchase_order_item_id,product_id,description,quantity,unit,unit_cost,lot_number,storage_zone_id,expiry_date) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$receiptId, $poItemId, $productId, $description, $quantity, $unit, $unitCost, $lot, $zoneId, $expiry]);
        $this->recalculateGoodsReceipt($receiptId);
        AuditService::log('line_added', 'goods_receipts', $receiptId, 'Goods receipt line added.', null, ['lot_number' => $lot, 'quantity' => $quantity]);
        return 'Receipt line added and receipt total recalculated.';
    }

    private function saveSalesLine(int $orderId, array $input): string
    {
        $order = $this->row('sales_orders', $orderId);
        $this->expect($order['status'] === 'draft', 'Sales lines can only be changed in draft.');
        $productId = (int) ($input['product_id'] ?? 0);
        $lotId = (int) ($input['inventory_lot_id'] ?? 0) ?: null;
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
        $priceInput = trim((string) ($input['unit_price'] ?? ''));
        $unitPrice = $priceInput === '' ? (new PricingService($this->pdo))->calculate($productId, !empty($order['rate_card_id']) ? (int) $order['rate_card_id'] : null)['effective_price'] : filter_var($priceInput, FILTER_VALIDATE_FLOAT);
        $discount = filter_var($input['discount_amount'] ?? 0, FILTER_VALIDATE_FLOAT);
        $this->expect($productId > 0 && $quantity !== false && $quantity > 0 && $unitPrice !== false && $unitPrice >= 0 && $discount !== false && $discount >= 0, 'Enter valid product, quantity, price, and discount values.');
        $this->expect((int) $this->fetchValue("SELECT COUNT(*) FROM products WHERE id=? AND status='active'", [$productId]) === 1, 'Select an active product.');
        if ($lotId) {
            $this->expect((int) $this->fetchValue("SELECT COUNT(*) FROM inventory_lots WHERE id=? AND product_id=? AND available_quantity>=? AND status IN ('available','allocated')", [$lotId, $productId, $quantity]) === 1, 'Selected lot is not eligible or has insufficient stock.');
        }
        $gross = round($quantity * $unitPrice, 2);
        $this->expect($discount <= $gross, 'Discount cannot exceed the gross line value.');
        $tax = round(($gross - $discount) * gst_rate(), 2);
        $lineTotal = round($gross - $discount + $tax, 2);
        $this->pdo->prepare('INSERT INTO sales_order_items (sales_order_id,product_id,inventory_lot_id,quantity,unit_price,discount_amount,tax_amount,line_total) VALUES (?,?,?,?,?,?,?,?)')
            ->execute([$orderId, $productId, $lotId, $quantity, $unitPrice, $discount, $tax, $lineTotal]);
        $this->recalculateSalesOrder($orderId);
        AuditService::log('line_added', 'sales_orders', $orderId, 'Sales order line added.', null, ['product_id' => $productId, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'price_source' => $priceInput === '' ? 'live_bird_cost_rate_card' : 'manual', 'line_total' => $lineTotal]);
        $gst = number_format(gst_rate() * 100, 2);
        return $priceInput === '' ? "Sales line priced from live-bird cost and the assigned rate card; {$gst}% GST and totals were recalculated." : "Sales line added with {$gst}% GST and order totals recalculated.";
    }

    private function saveProductionOutput(int $batchId, array $input): string
    {
        $batch = $this->row('production_batches', $batchId);
        $this->expect(in_array($batch['status'], ['in_progress','hold'], true), 'Production outputs can only be prepared for an active batch.');
        $this->expect(in_array($batch['stage'], ['grading','packing','storage','dispatch'], true), 'Complete chilling before defining finished output lots.');
        $productId = (int) ($input['product_id'] ?? 0);
        $zoneId = (int) ($input['storage_zone_id'] ?? 0);
        $lotNumber = mb_substr(trim((string) ($input['lot_number'] ?? '')), 0, 60);
        $grade = strtoupper(mb_substr(trim((string) ($input['grade'] ?? 'A')), 0, 20));
        $quantity = filter_var($input['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
        $unitCost = filter_var($input['unit_cost'] ?? 0, FILTER_VALIDATE_FLOAT);
        $expiry = trim((string) ($input['expiry_date'] ?? '')) ?: null;
        $qcStatus = (string) ($input['qc_status'] ?? 'released');
        $this->expect($productId > 0 && $zoneId > 0 && $lotNumber !== '', 'Product, storage zone, and lot number are required.');
        $this->expect($quantity !== false && $quantity > 0 && $unitCost !== false && $unitCost >= 0, 'Enter a valid output quantity and unit cost.');
        $this->expect(in_array($qcStatus, ['released','hold','rejected'], true), 'Select a valid quality disposition.');
        $this->expect((int) $this->fetchValue("SELECT COUNT(*) FROM products WHERE id=? AND category='finished_good' AND status='active'", [$productId]) === 1, 'Select an active finished-goods product.');
        $this->expect((int) $this->fetchValue("SELECT COUNT(*) FROM storage_zones WHERE id=? AND status='active'", [$zoneId]) === 1, 'Select an active storage zone.');
        $this->expect((int) $this->fetchValue('SELECT COUNT(*) FROM inventory_lots WHERE lot_number=?', [$lotNumber]) === 0 && (int) $this->fetchValue('SELECT COUNT(*) FROM production_outputs WHERE lot_number=?', [$lotNumber]) === 0, 'That lot number already exists.');
        $this->pdo->prepare('INSERT INTO production_outputs (production_batch_id,product_id,storage_zone_id,lot_number,grade,quantity,unit_cost,expiry_date,qc_status,created_by) VALUES (?,?,?,?,?,?,?,?,?,?)')
            ->execute([$batchId, $productId, $zoneId, $lotNumber, $grade, $quantity, $unitCost, $expiry, $qcStatus, $this->userId()]);
        if ($qcStatus === 'hold') {
            $holdNumber = 'QH-' . date('YmdHis') . '-' . $batchId;
            $this->pdo->prepare("INSERT INTO quality_holds (hold_number,production_batch_id,reason,held_by,held_at,disposition,status,notes) VALUES (?,?,?, ?,NOW(),'pending','open',?)")
                ->execute([$holdNumber, $batchId, 'Finished output lot placed on quality hold.', $this->userId(), $lotNumber]);
            $this->pdo->prepare("UPDATE production_batches SET status='hold' WHERE id=?")->execute([$batchId]);
        }
        AuditService::log('output_added', 'production_batches', $batchId, 'Finished production output lot prepared.', null, ['lot_number'=>$lotNumber,'product_id'=>$productId,'quantity'=>$quantity,'qc_status'=>$qcStatus]);
        return 'Production output lot added. It will post to inventory only when the batch completes.';
    }

    private function completeProductionOutputs(int $batchId, float $quantityOut): void
    {
        $batch = $this->row('production_batches', $batchId);
        $this->expect((int) $this->fetchValue("SELECT COUNT(*) FROM quality_holds WHERE production_batch_id=? AND status='open'", [$batchId]) === 0, 'Resolve all open quality holds before completing production.');
        $outputs = $this->fetchAll("SELECT * FROM production_outputs WHERE production_batch_id=? AND qc_status='released' ORDER BY id FOR UPDATE", [$batchId]);
        $this->expect($outputs !== [], 'Add at least one released finished-output lot before completing production.');
        $total = array_sum(array_map(static fn (array $row): float => (float) $row['quantity'], $outputs));
        $this->expect(abs($total - $quantityOut) <= 0.01, 'Final stage output must equal the total quantity of released production output lots.');
        $rejected = (float) $this->fetchValue("SELECT COALESCE(SUM(quantity),0) FROM production_outputs WHERE production_batch_id=? AND qc_status='rejected'", [$batchId]);

        foreach ($outputs as $output) {
            if ($output['inventory_lot_id']) continue;
            $this->consumePackaging((int) $output['product_id'], (float) $output['quantity'], $batchId);
            $insert = $this->pdo->prepare("INSERT INTO inventory_lots (lot_number,product_id,production_batch_id,storage_zone_id,received_date,expiry_date,quantity,available_quantity,unit_cost,barcode,status) VALUES (?,?,?,?,CURDATE(),?,?,?,?,?,'available')");
            $insert->execute([$output['lot_number'], $output['product_id'], $batchId, $output['storage_zone_id'], $output['expiry_date'], $output['quantity'], $output['quantity'], $output['unit_cost'], $output['lot_number']]);
            $lotId = (int) $this->pdo->lastInsertId();
            $this->pdo->prepare('UPDATE production_outputs SET inventory_lot_id=? WHERE id=?')->execute([$lotId, $output['id']]);
            $this->pdo->prepare("INSERT INTO stock_movements (inventory_lot_id,movement_type,quantity,to_zone_id,reference_type,reference_id,reason,moved_by,moved_at) VALUES (?,'production_in',?,?,'production_batch',?,'Finished goods receipt from completed production',?,NOW())")
                ->execute([$lotId, $output['quantity'], $output['storage_zone_id'], $batchId, $this->userId()]);
        }

        $input = (float) $batch['input_weight_kg'];
        $saleableYield = $input > 0 ? round(($total / $input) * 100, 3) : 0;
        $waste = max(0, $input - $total - $rejected);
        $wastePercent = $input > 0 ? round(($waste / $input) * 100, 3) : 0;
        $expected = (float) $this->fetchValue("SELECT COALESCE((SELECT setting_value FROM settings WHERE setting_key='expected_yield_percent'),70)");
        $this->pdo->prepare("INSERT INTO yield_records (production_batch_id,carcass_quantity,carcass_weight_kg,by_product_weight_kg,waste_weight_kg,finished_weight_kg,deboned_weight_kg,carcass_yield_percent,saleable_yield_percent,waste_percent,expected_yield_percent,variance_percent,calculated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE waste_weight_kg=VALUES(waste_weight_kg),finished_weight_kg=VALUES(finished_weight_kg),saleable_yield_percent=VALUES(saleable_yield_percent),waste_percent=VALUES(waste_percent),expected_yield_percent=VALUES(expected_yield_percent),variance_percent=VALUES(variance_percent),calculated_at=NOW()")
            ->execute([$batchId, 0, $total, 0, $waste, $total, 0, $saleableYield, $saleableYield, $wastePercent, $expected, round($saleableYield-$expected, 3)]);
        $this->pdo->prepare("UPDATE production_requirements pr SET pr.status='fulfilled' WHERE pr.product_id IN (SELECT product_id FROM production_outputs WHERE production_batch_id=?) AND pr.status IN ('open','planned','in_progress') AND (SELECT COALESCE(SUM(available_quantity),0) FROM inventory_lots WHERE product_id=pr.product_id AND status IN ('available','allocated'))>=pr.shortage_quantity")->execute([$batchId]);
    }

    private function consumePackaging(int $finishedProductId, float $outputQuantity, int $batchId): void
    {
        $specs = $this->fetchAll("SELECT * FROM packaging_specs WHERE finished_product_id=? AND status='active'", [$finishedProductId]);
        foreach ($specs as $spec) {
            $required = round($outputQuantity * (float) $spec['quantity_per_unit'] * (1 + ((float) $spec['waste_percent'] / 100)), 3);
            if ($required <= 0) continue;
            $lots = $this->fetchAll("SELECT * FROM inventory_lots WHERE product_id=? AND available_quantity>0 AND status IN ('available','allocated') AND (expiry_date IS NULL OR expiry_date>=CURDATE()) ORDER BY COALESCE(expiry_date,'9999-12-31'),received_date,id FOR UPDATE", [$spec['packaging_product_id']]);
            $available = array_sum(array_map(static fn (array $lot): float => (float) $lot['available_quantity'], $lots));
            $this->expect($available + 0.001 >= $required, 'Insufficient packaging material to complete production. Replenish the configured packaging BOM.');
            $remaining = $required;
            foreach ($lots as $lot) {
                if ($remaining <= 0.0005) break;
                $consume = min($remaining, (float) $lot['available_quantity']);
                $newAvailable = round((float) $lot['available_quantity'] - $consume, 3);
                $this->pdo->prepare('UPDATE inventory_lots SET available_quantity=?,status=? WHERE id=?')->execute([$newAvailable, $newAvailable <= 0 ? 'depleted' : 'available', $lot['id']]);
                $this->pdo->prepare("INSERT INTO stock_movements (inventory_lot_id,movement_type,quantity,from_zone_id,reference_type,reference_id,reason,moved_by,moved_at) VALUES (?,'packaging_consumption',?,?,'production_batch',?,'Packaging BOM consumption',?,NOW())")
                    ->execute([$lot['id'], $consume, $lot['storage_zone_id'], $batchId, $this->userId()]);
                $remaining = round($remaining - $consume, 3);
            }
        }
    }

    private function recalculatePurchaseOrder(int $id): void
    {
        $this->pdo->prepare('UPDATE purchase_orders SET total_amount=(SELECT COALESCE(SUM(total_price),0) FROM purchase_order_items WHERE purchase_order_id=?) WHERE id=?')->execute([$id, $id]);
    }

    private function recalculateGoodsReceipt(int $id): void
    {
        $this->pdo->prepare('UPDATE goods_receipts SET total_amount=(SELECT COALESCE(SUM(quantity*unit_cost),0) FROM goods_receipt_items WHERE goods_receipt_id=?) WHERE id=?')->execute([$id, $id]);
    }

    private function recalculateSalesOrder(int $id): void
    {
        $this->pdo->prepare('UPDATE sales_orders SET subtotal=(SELECT COALESCE(SUM(quantity*unit_price),0) FROM sales_order_items WHERE sales_order_id=?),discount_amount=(SELECT COALESCE(SUM(discount_amount),0) FROM sales_order_items WHERE sales_order_id=?),tax_amount=(SELECT COALESCE(SUM(tax_amount),0) FROM sales_order_items WHERE sales_order_id=?),total_amount=(SELECT COALESCE(SUM(line_total),0) FROM sales_order_items WHERE sales_order_id=?) WHERE id=?')->execute([$id, $id, $id, $id, $id]);
    }

    private function reconcilePurchaseOrder(int $poId): void
    {
        $remaining = (int) $this->fetchValue("SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.purchase_order_id=? AND (SELECT COALESCE(SUM(gri.quantity),0) FROM goods_receipt_items gri JOIN goods_receipts gr ON gr.id=gri.goods_receipt_id WHERE gri.purchase_order_item_id=poi.id AND gr.status='accepted')+0.001<poi.quantity", [$poId]);
        $status = $remaining === 0 ? 'completed' : 'partial';
        $this->pdo->prepare('UPDATE purchase_orders SET status=? WHERE id=?')->execute([$status, $poId]);
    }

    private function refreshInvoice(int $invoiceId): void
    {
        $invoice = $this->row('invoices', $invoiceId);
        $paid = (float) $this->fetchValue("SELECT COALESCE(SUM(amount),0) FROM payments WHERE invoice_id=? AND status='cleared'", [$invoiceId]);
        $balance = max(0, round((float) $invoice['total_amount'] - $paid, 2));
        $status = $balance <= 0.001 ? 'paid' : ($paid > 0 ? 'partial' : (strtotime((string) $invoice['due_date']) < strtotime(date('Y-m-d')) ? 'overdue' : 'issued'));
        $this->pdo->prepare('UPDATE invoices SET paid_amount=?,balance_amount=?,status=? WHERE id=?')->execute([$paid, $balance, $status, $invoiceId]);
        $paymentStatus = $status === 'paid' ? 'paid' : ($paid > 0 ? 'partial' : ($status === 'overdue' ? 'overdue' : 'unpaid'));
        $this->pdo->prepare('UPDATE sales_orders SET payment_status=? WHERE id=?')->execute([$paymentStatus, $invoice['sales_order_id']]);
    }

    private function refreshCustomerBalances(int $customerId): void
    {
        $this->pdo->prepare("UPDATE customers SET outstanding_balance=(SELECT COALESCE(SUM(balance_amount),0) FROM invoices WHERE customer_id=? AND status IN ('issued','partial','overdue')) WHERE id=?")
            ->execute([$customerId, $customerId]);
    }

    private function refreshSupplierInvoice(int $invoiceId): void
    {
        $invoice = $this->row('supplier_invoices', $invoiceId);
        $paid = (float) $this->fetchValue("SELECT COALESCE(SUM(amount),0) FROM supplier_payments WHERE supplier_invoice_id=? AND status='cleared'", [$invoiceId]);
        $balance = max(0, round((float) $invoice['total_amount'] - $paid, 2));
        $status = $balance <= 0.001 ? 'paid' : ($paid > 0 ? 'partial' : (strtotime((string) $invoice['due_date']) < strtotime(date('Y-m-d')) ? 'overdue' : 'approved'));
        $this->pdo->prepare('UPDATE supplier_invoices SET paid_amount=?,balance_amount=?,status=? WHERE id=?')->execute([$paid, $balance, $status, $invoiceId]);
    }

    private function refreshSupplierBalance(int $supplierId): void
    {
        $this->pdo->prepare("UPDATE suppliers SET outstanding_balance=(SELECT COALESCE(SUM(balance_amount),0) FROM supplier_invoices WHERE supplier_id=? AND status IN ('approved','partial','overdue')) WHERE id=?")
            ->execute([$supplierId, $supplierId]);
    }

    private function applyPartnerShareEffect(array $transaction, int $direction): void
    {
        $amount = (float) $transaction['amount'] * $direction;
        $type = (string) $transaction['transaction_type'];
        if ($type === 'installment' || $type === 'other') {
            $sql = 'UPDATE shareholders SET received_share=GREATEST(0,received_share+?),pending_share_amount=GREATEST(0,share_amount-(received_share+?)) WHERE id=?';
            $this->pdo->prepare($sql)->execute([$amount,$amount,$transaction['shareholder_id']]);
            return;
        }
        if ($type === 'share_addition') {
            $this->pdo->prepare('UPDATE shareholders SET share_amount=GREATEST(0,share_amount+?),received_share=GREATEST(0,received_share+?),pending_share_amount=GREATEST(0,(share_amount+?)-(received_share+?)) WHERE id=?')
                ->execute([$amount,$amount,$amount,$amount,$transaction['shareholder_id']]);
            return;
        }
        if ($type === 'share_deduction' || $type === 'cancellation_settlement') {
            $this->pdo->prepare('UPDATE shareholders SET share_amount=GREATEST(0,share_amount-?),received_share=GREATEST(0,received_share-?),pending_share_amount=GREATEST(0,(share_amount-?)-(received_share-?)) WHERE id=?')
                ->execute([$amount,$amount,$amount,$amount,$transaction['shareholder_id']]);
            return;
        }
        if ($type === 'refund') {
            $this->pdo->prepare('UPDATE shareholders SET received_share=GREATEST(0,received_share-?),pending_share_amount=GREATEST(0,share_amount-(received_share-?)) WHERE id=?')
                ->execute([$amount,$amount,$transaction['shareholder_id']]);
        }
    }

    private function adjustBank(?int $bankAccountId, float $amount): void
    {
        if (!$bankAccountId || abs($amount) < 0.001) return;
        $statement = $this->pdo->prepare('UPDATE company_bank_accounts SET current_balance=current_balance+? WHERE id=? AND status=\'active\'');
        $statement->execute([$amount,$bankAccountId]);
        $this->expect($statement->rowCount() === 1, 'The selected company bank account is not active.');
    }

    private function reversalReason(array $input): string
    {
        $reason = mb_substr(trim((string) ($input['reversal_reason'] ?? '')), 0, 1000);
        $this->expect(mb_strlen($reason) >= 5, 'Enter a reversal reason of at least five characters.');
        return $reason;
    }

    private function postJournal(string $referenceType, int $referenceId, string $reference, string $date, array $lines): void
    {
        $exists = (int) $this->fetchValue('SELECT COUNT(*) FROM journal_entries WHERE reference_type=? AND reference_id=? AND status=\'posted\'', [$referenceType, $referenceId]);
        $this->expect($exists === 0, 'This transaction is already posted to the ledger.');
        $debit = array_sum(array_column($lines, 1));
        $credit = array_sum(array_column($lines, 2));
        $this->expect(abs($debit - $credit) < 0.01, 'Journal entry is not balanced.');
        $normalizedType = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $referenceType));
        $entryNumber = 'JE-' . substr($normalizedType, 0, 18) . '-' . $referenceId . '-' . strtoupper(substr(hash('sha256', $referenceType), 0, 6));
        $this->pdo->prepare("INSERT INTO journal_entries (entry_number,entry_date,reference_type,reference_id,description,status,posted_by,posted_at) VALUES (?,?,?,?,?,'posted',?,NOW())")
            ->execute([$entryNumber, $date, $referenceType, $referenceId, 'Automated posting: ' . $reference, $this->userId()]);
        $entryId = (int) $this->pdo->lastInsertId();
        foreach ($lines as [$code, $lineDebit, $lineCredit, $description]) {
            if ((float) $lineDebit === 0.0 && (float) $lineCredit === 0.0) {
                continue;
            }
            $account = $this->fetchValue("SELECT id FROM chart_of_accounts WHERE account_code=? AND status='active'", [$code]);
            $this->expect($account !== false, "Required ledger account {$code} is missing or inactive.");
            $this->pdo->prepare('INSERT INTO journal_lines (journal_entry_id,account_id,debit,credit,description) VALUES (?,?,?,?,?)')
                ->execute([$entryId, $account, $lineDebit, $lineCredit, $description]);
        }
    }

    private function auditTransition(string $module, int $id, string $from, string $to): void
    {
        AuditService::log('workflow_transition', $module, $id, 'Governed workflow transition.', ['status' => $from], ['status' => $to]);
    }

    private function row(string $table, int $id): array
    {
        $allowed = ['purchase_orders', 'goods_receipts', 'bird_receipts', 'production_batches', 'sales_orders', 'invoices', 'payments', 'supplier_invoices', 'supplier_payments', 'dispatches', 'inventory_lots', 'customers', 'suppliers', 'operating_expenses', 'partner_transactions', 'partner_dividends', 'salary_advances', 'asset_depreciation_entries', 'assets'];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Unsafe workflow table.');
        }
        $row = $this->fetchOne("SELECT * FROM `{$table}` WHERE id=? FOR UPDATE", [$id]);
        if (!$row) {
            throw new \RuntimeException('Workflow record not found.', 404);
        }
        return $row;
    }

    private function fetchOne(string $sql, array $params = []): ?array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetch() ?: null;
    }

    private function fetchAll(string $sql, array $params = []): array
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    private function fetchValue(string $sql, array $params = []): mixed
    {
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchColumn();
    }

    private function userId(): ?int
    {
        $id = (int) ($_SESSION['user_id'] ?? 0);
        return $id > 0 ? $id : null;
    }

    private function expect(bool $condition, string $message): void
    {
        if (!$condition) {
            throw new \RuntimeException($message, 422);
        }
    }

    private function atomic(callable $callback): mixed
    {
        $ownsTransaction = !$this->pdo->inTransaction();
        $savepoint = 'workflow_' . (++$this->savepointSequence);
        if ($ownsTransaction) {
            $this->pdo->beginTransaction();
        } else {
            $this->pdo->exec("SAVEPOINT {$savepoint}");
        }
        try {
            $result = $callback();
            if ($ownsTransaction) {
                $this->pdo->commit();
            } else {
                $this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");
            }
            return $result;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            } elseif (!$ownsTransaction && $this->pdo->inTransaction()) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT {$savepoint}");
                $this->pdo->exec("RELEASE SAVEPOINT {$savepoint}");
            }
            throw $exception;
        }
    }
}
