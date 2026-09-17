<?php

use MeatinOS\Core\Csrf;

$summaryMap = [
    'purchase_order' => ['supplier_id', 'order_date', 'expected_date', 'total_amount', 'status', 'approved_by'],
    'goods_receipt' => ['purchase_order_id', 'supplier_id', 'received_date', 'total_amount', 'quality_status', 'status'],
    'bird_receipt' => ['supplier_id', 'purchase_order_id', 'received_at', 'bird_count', 'mortality_count', 'net_weight_kg', 'vet_status', 'status'],
    'production_batch' => ['bird_receipt_id', 'production_date', 'shift', 'birds_input', 'input_weight_kg', 'output_weight_kg', 'yield_percent', 'stage', 'status'],
    'sales_order' => ['customer_id', 'sales_person_id', 'rate_card_id', 'order_date', 'delivery_date', 'delivery_time', 'delivery_location', 'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'payment_status', 'status'],
    'invoice' => ['sales_order_id', 'customer_id', 'invoice_date', 'due_date', 'subtotal', 'tax_amount', 'total_amount', 'paid_amount', 'balance_amount', 'status'],
    'payment' => ['invoice_id', 'customer_id', 'payment_date', 'amount', 'payment_method', 'reference_number', 'status'],
    'supplier_invoice' => ['supplier_id', 'purchase_order_id', 'goods_receipt_id', 'invoice_date', 'due_date', 'subtotal', 'tax_amount', 'total_amount', 'paid_amount', 'balance_amount', 'status'],
    'supplier_payment' => ['supplier_invoice_id', 'supplier_id', 'payment_date', 'amount', 'payment_method', 'reference_number', 'status'],
    'operating_expense' => ['expense_date','expense_type','cost_center_id','taxable_amount','gst_amount','total_amount','payment_method','reference_number','status'],
    'partner_transaction' => ['shareholder_id','transaction_date','transaction_type','share_quantity','amount','payment_method','reference_number','status'],
    'partner_dividend' => ['shareholder_id','financial_year','declaration_date','share_value_basis','dividend_rate','gross_amount','tds_amount','net_amount','paid_date','status'],
    'salary_advance' => ['employee_id','request_date','amount','approved_amount','monthly_recovery','recovered_amount','balance_amount','recovery_start','paid_at','status'],
    'asset_depreciation' => ['asset_id','period_start','period_end','depreciation_amount','accumulated_amount','book_value','status'],
    'dispatch' => ['sales_order_id', 'vehicle_id', 'driver_id', 'planned_departure', 'actual_departure', 'delivered_at', 'temperature_c', 'delivery_temperature_c', 'delivery_temperature_recorded_at', 'status', 'pod_reference'],
];
$lineColumns = [
    'purchase_order' => ['sku', 'product_name', 'description', 'quantity', 'unit', 'unit_price', 'total_price'],
    'goods_receipt' => ['sku', 'product_name', 'description', 'quantity', 'unit', 'unit_cost', 'lot_number', 'zone_name', 'expiry_date'],
    'sales_order' => ['sku', 'product_name', 'lot_number', 'quantity', 'unit_price', 'discount_amount', 'tax_amount', 'line_total'],
    'production_batch' => ['stage', 'started_at', 'completed_at', 'quantity_in', 'quantity_out', 'temperature_c', 'quality_status', 'notes'],
    'invoice' => ['entry_number', 'entry_date', 'status', 'account_code', 'account_name', 'debit', 'credit'],
    'payment' => ['entry_number', 'entry_date', 'reference_type', 'account_code', 'account_name', 'debit', 'credit'],
    'supplier_invoice' => ['entry_number', 'entry_date', 'status', 'account_code', 'account_name', 'debit', 'credit'],
    'supplier_payment' => ['entry_number', 'entry_date', 'reference_type', 'account_code', 'account_name', 'debit', 'credit'],
    'operating_expense' => ['entry_number', 'entry_date', 'reference_type', 'account_code', 'account_name', 'debit', 'credit'],
    'partner_transaction' => ['entry_number', 'entry_date', 'reference_type', 'account_code', 'account_name', 'debit', 'credit'],
    'partner_dividend' => ['entry_number', 'entry_date', 'reference_type', 'account_code', 'account_name', 'debit', 'credit'],
    'salary_advance' => ['entry_number', 'entry_date', 'reference_type', 'account_code', 'account_name', 'debit', 'credit'],
    'asset_depreciation' => ['entry_number', 'entry_date', 'reference_type', 'account_code', 'account_name', 'debit', 'credit'],
    'dispatch' => ['sku', 'product_name', 'lot_number', 'quantity'],
];
$relatedColumns = [
    'purchase_order' => ['grn_number', 'received_date', 'total_amount', 'quality_status', 'status'],
    'sales_order' => ['invoice_number', 'invoice_date', 'total_amount', 'balance_amount', 'status'],
    'production_batch' => ['check_number', 'checkpoint', 'checked_at', 'grade', 'status'],
    'invoice' => ['payment_number', 'payment_date', 'amount', 'payment_method', 'status'],
    'payment' => ['invoice_number', 'total_amount', 'paid_amount', 'balance_amount', 'status'],
    'supplier_invoice' => ['payment_number', 'payment_date', 'amount', 'payment_method', 'status'],
    'supplier_payment' => ['invoice_number', 'total_amount', 'paid_amount', 'balance_amount', 'status'],
    'dispatch' => ['order_number', 'delivery_date', 'delivery_address', 'status', 'payment_status'],
];
$isMoney = static fn (string $key): bool => str_contains($key, 'amount') || in_array($key, ['unit_price', 'unit_cost', 'total_price', 'line_total', 'debit', 'credit'], true);
$format = static function (string $key, mixed $value) use ($isMoney): string {
    if ($value === null || $value === '') return '—';
    if ($isMoney($key)) return money($value);
    if (str_ends_with($key, '_at') && strtotime((string) $value)) return date('j M Y, g:i A', strtotime((string) $value));
    if (str_ends_with($key, '_date') && strtotime((string) $value)) return date('j M Y', strtotime((string) $value));
    if (in_array($key, ['status', 'payment_status', 'quality_status', 'vet_status', 'stage', 'payment_method', 'checkpoint', 'reference_type'], true)) return human_status((string) $value);
    if (is_numeric($value) && str_contains($key, 'percent')) return number_format((float) $value, 2) . '%';
    return (string) $value;
};
$number = $record[$definition['number']] ?? ('#' . $record['id']);
$draftLines = $canManage && $record['status'] === 'draft' && in_array($entity, ['purchase_order', 'goods_receipt', 'sales_order'], true);
$productionOutputEntry = $canManage && $entity === 'production_batch' && in_array($record['status'], ['in_progress','hold'], true) && in_array($record['stage'], ['grading','packing','storage','dispatch'], true);
?>

<div class="workflow-hero card-panel">
    <div>
        <a class="workflow-back" href="<?= e(url('module', ['name' => $definition['module']])) ?>"><i class="bi bi-arrow-left"></i> Back to <?= e(human_status($definition['module'])) ?></a>
        <p class="eyebrow mt-3">Controlled transaction</p>
        <h2><?= e($definition['title']) ?></h2>
        <p><?= e($number) ?> · approvals and dependent ledgers commit atomically</p>
    </div>
    <span class="badge rounded-pill text-bg-<?= e(status_class((string) $record['status'])) ?> px-3 py-2"><?= e(human_status((string) $record['status'])) ?></span>
</div>

<div class="workflow-grid">
    <section class="card-panel workflow-summary">
        <div class="panel-heading"><div><p class="eyebrow">Current record</p><h3>Transaction summary</h3></div><i class="bi bi-shield-check"></i></div>
        <div class="workflow-facts">
            <?php foreach ($summaryMap[$entity] ?? [] as $field): ?>
                <div><span><?= e(human_status($field)) ?></span><strong><?= e($format($field, $record[$field] ?? null)) ?></strong></div>
            <?php endforeach; ?>
        </div>
    </section>

    <aside class="card-panel workflow-actions-panel">
        <div class="panel-heading"><div><p class="eyebrow">Next step</p><h3>Available actions</h3></div><i class="bi bi-signpost-split"></i></div>
        <?php if (!$canManage): ?>
            <div class="alert alert-light border mb-0">You have read-only access to this workflow.</div>
        <?php elseif (!$actions): ?>
            <div class="workflow-complete"><i class="bi bi-check-circle"></i><strong>No action required</strong><span>This record is complete or has no valid transition from its current state.</span></div>
        <?php else: ?>
            <div class="workflow-action-list">
                <?php foreach ($actions as [$action, $label, $style]): ?>
                    <form action="<?= e(url('workflow.action')) ?>" method="post" class="workflow-action-form" novalidate data-prevent-double-submit>
                        <?= Csrf::field() ?><input type="hidden" name="entity" value="<?= e($entity) ?>"><input type="hidden" name="id" value="<?= e($record['id']) ?>"><input type="hidden" name="action" value="<?= e($action) ?>">
                        <?php if ($action === 'advance'): ?>
                            <div class="row g-2 mb-2">
                                <div class="col-6"><label class="form-label" for="workflow_quantity_out">Stage output</label><input class="form-control form-control-sm" id="workflow_quantity_out" name="quantity_out" type="number" min="0" step="0.001" required></div>
                                <div class="col-6"><label class="form-label" for="workflow_temperature">Temperature °C</label><input class="form-control form-control-sm" id="workflow_temperature" name="temperature_c" type="number" step="0.01"></div>
                                <?php if ($entity==='production_batch' && in_array($record['stage'],['defeathering','packing'],true)): ?><div class="col-12"><label class="form-label" for="workflow_bird_count"><?= $record['stage']==='packing'?'Packed':'Defeathered' ?> bird count</label><input class="form-control form-control-sm" id="workflow_bird_count" name="bird_count" type="number" min="0" step="1" required></div><?php endif; ?>
                                <div class="col-12"><label class="form-label" for="workflow_quality_status">Quality disposition</label><select class="form-select form-select-sm" id="workflow_quality_status" name="quality_status" required><option value="passed">Passed</option><option value="hold">Hold</option><option value="failed">Failed</option></select></div>
                                <div class="col-12"><label class="form-label" for="workflow_notes">Operator notes</label><textarea class="form-control form-control-sm" id="workflow_notes" name="notes" rows="2" maxlength="1500"></textarea></div>
                            </div>
                        <?php elseif ($action === 'deliver'): ?>
                            <div class="row g-2 mb-2">
                                <div class="col-12"><label class="form-label" for="workflow_pod_reference">Proof of delivery reference</label><input class="form-control form-control-sm" id="workflow_pod_reference" name="pod_reference" maxlength="100" required></div>
                                <div class="col-12"><label class="form-label" for="workflow_delivery_temperature">Delivery-point temperature °C</label><input class="form-control form-control-sm" id="workflow_delivery_temperature" name="temperature_c" type="number" min="-40" max="30" step="0.01" value="<?= e($record['delivery_temperature_c'] ?? $record['temperature_c'] ?? '') ?>" required></div>
                            </div>
                        <?php elseif (in_array($action, ['load', 'depart'], true)): ?>
                            <label class="form-label" for="workflow_load_temperature_<?= e($action) ?>">Load temperature °C</label><input class="form-control form-control-sm mb-2" id="workflow_load_temperature_<?= e($action) ?>" name="temperature_c" type="number" min="-40" max="30" step="0.01" value="<?= e($record['temperature_c'] ?? '') ?>" required>
                        <?php elseif ($action === 'reverse'): ?>
                            <label class="form-label" for="workflow_reversal_reason">Mandatory reversal reason</label><textarea class="form-control form-control-sm mb-2" id="workflow_reversal_reason" name="reversal_reason" rows="2" minlength="5" maxlength="1000" required></textarea>
                        <?php endif; ?>
                        <button class="btn btn-<?= e($style) ?> w-100" type="submit"><?= e($label) ?></button>
                    </form>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </aside>
</div>

<?php if ($draftLines): ?>
<section class="card-panel workflow-line-form">
    <div class="panel-heading"><div><p class="eyebrow">Draft preparation</p><h3>Add transaction line</h3></div><i class="bi bi-plus-square"></i></div>
    <form action="<?= e(url('workflow.line.save')) ?>" method="post" class="row g-3" novalidate data-prevent-double-submit>
        <?= Csrf::field() ?><input type="hidden" name="entity" value="<?= e($entity) ?>"><input type="hidden" name="id" value="<?= e($record['id']) ?>">
        <?php if ($entity === 'purchase_order'): ?>
            <div class="col-md-3"><label class="form-label" for="line_product">Product (optional)</label><select class="form-select" id="line_product" name="product_id"><option value="">Uncatalogued item</option><?php foreach ($lookups['products'] as $product): ?><option value="<?= e($product['id']) ?>"><?= e($product['sku'] . ' · ' . $product['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-3"><label class="form-label" for="line_description">Description</label><input class="form-control" id="line_description" name="description" maxlength="255" required></div>
            <div class="col-md-2"><label class="form-label" for="line_quantity">Quantity</label><input class="form-control" id="line_quantity" name="quantity" type="number" min="0.001" step="0.001" required></div>
            <div class="col-md-2"><label class="form-label" for="line_unit">Unit</label><input class="form-control" id="line_unit" name="unit" maxlength="20" placeholder="kg / pcs" required></div>
            <div class="col-md-2"><label class="form-label" for="line_unit_price">Unit price</label><input class="form-control" id="line_unit_price" name="unit_price" type="number" min="0" step="0.001" required></div>
        <?php elseif ($entity === 'goods_receipt'): ?>
            <?php if ($lookups['purchase_items']): ?><div class="col-md-4"><label class="form-label" for="line_purchase_item">Purchase-order line</label><select class="form-select" id="line_purchase_item" name="purchase_order_item_id"><option value="">No PO line</option><?php foreach ($lookups['purchase_items'] as $item): ?><option value="<?= e($item['id']) ?>"><?= e(($item['sku'] ?: 'ITEM') . ' · ' . $item['description'] . ' · ' . $item['quantity'] . ' ' . $item['unit']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            <div class="col-md-4"><label class="form-label" for="line_product">Product</label><select class="form-select" id="line_product" name="product_id" required><option value="">Select product</option><?php foreach ($lookups['products'] as $product): ?><option value="<?= e($product['id']) ?>"><?= e($product['sku'] . ' · ' . $product['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label" for="line_storage_zone">Storage zone</label><select class="form-select" id="line_storage_zone" name="storage_zone_id" required><option value="">Select zone</option><?php foreach ($lookups['zones'] as $zone): ?><option value="<?= e($zone['id']) ?>"><?= e($zone['code'] . ' · ' . $zone['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label" for="line_description">Description</label><input class="form-control" id="line_description" name="description" maxlength="255" required></div>
            <div class="col-md-2"><label class="form-label" for="line_quantity">Quantity</label><input class="form-control" id="line_quantity" name="quantity" type="number" min="0.001" step="0.001" required></div>
            <div class="col-md-2"><label class="form-label" for="line_unit">Unit</label><input class="form-control" id="line_unit" name="unit" maxlength="20" required></div>
            <div class="col-md-2"><label class="form-label" for="line_unit_cost">Unit cost</label><input class="form-control" id="line_unit_cost" name="unit_cost" type="number" min="0" step="0.001" required></div>
            <div class="col-md-2"><label class="form-label" for="line_lot_number">Lot number</label><input class="form-control" id="line_lot_number" name="lot_number" maxlength="60" required></div>
            <div class="col-md-2"><label class="form-label" for="line_expiry_date">Expiry date</label><input class="form-control" id="line_expiry_date" name="expiry_date" type="date"></div>
        <?php else: ?>
            <div class="col-md-4"><label class="form-label" for="line_product">Product</label><select class="form-select" id="line_product" name="product_id" required><option value="">Select product</option><?php foreach ($lookups['products'] as $product): ?><option value="<?= e($product['id']) ?>"><?= e($product['sku'] . ' · ' . $product['name']) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-4"><label class="form-label" for="line_inventory_lot">Specific lot (optional, otherwise FEFO)</label><select class="form-select" id="line_inventory_lot" name="inventory_lot_id"><option value="">Allocate automatically by FEFO</option><?php foreach ($lookups['lots'] as $lot): ?><option value="<?= e($lot['id']) ?>"><?= e($lot['lot_number'] . ' · ' . $lot['available_quantity'] . ' ' . $lot['unit'] . ' · exp ' . ($lot['expiry_date'] ?: 'n/a')) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label" for="line_quantity">Quantity</label><input class="form-control" id="line_quantity" name="quantity" type="number" min="0.001" step="0.001" required></div>
            <div class="col-md-2"><label class="form-label" for="line_unit_price">Unit price</label><input class="form-control" id="line_unit_price" name="unit_price" type="number" min="0" step="0.01" placeholder="Auto from rate card"><small class="text-muted">Leave blank for live-cost pricing.</small></div>
            <div class="col-md-2"><label class="form-label" for="line_discount">Discount</label><input class="form-control" id="line_discount" name="discount_amount" type="number" min="0" step="0.01" value="0"></div>
        <?php endif; ?>
        <div class="col-12 text-end"><button class="btn btn-danger" type="submit"><i class="bi bi-plus-lg me-1"></i>Add line</button></div>
    </form>
</section>
<?php endif; ?>

<?php if ($productionOutputEntry): ?>
<section class="card-panel workflow-line-form">
    <div class="panel-heading"><div><p class="eyebrow">Finished goods declaration</p><h3>Add production output lot</h3><p>Released lots post to inventory only with successful batch completion.</p></div><i class="bi bi-box-arrow-in-down"></i></div>
    <form action="<?= e(url('workflow.line.save')) ?>" method="post" class="row g-3" novalidate data-prevent-double-submit>
        <?= Csrf::field() ?><input type="hidden" name="entity" value="production_batch"><input type="hidden" name="id" value="<?= e($record['id']) ?>">
        <div class="col-md-4"><label class="form-label" for="output_product">Finished product</label><select class="form-select" id="output_product" name="product_id" required><option value="">Select product</option><?php foreach($lookups['products'] as $product): ?><option value="<?= e($product['id']) ?>"><?= e($product['sku'] . ' · ' . $product['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="output_storage_zone">Storage zone</label><select class="form-select" id="output_storage_zone" name="storage_zone_id" required><option value="">Select zone</option><?php foreach($lookups['zones'] as $zone): ?><option value="<?= e($zone['id']) ?>"><?= e($zone['code'] . ' · ' . $zone['name']) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label" for="output_lot_number">Lot / barcode number</label><input class="form-control" id="output_lot_number" name="lot_number" maxlength="60" required></div>
        <div class="col-md-2"><label class="form-label" for="output_grade">Grade</label><select class="form-select" id="output_grade" name="grade"><option value="A">Grade A</option><option value="B">Grade B</option><option value="C">Grade C</option></select></div>
        <div class="col-md-2"><label class="form-label" for="output_quantity">Quantity (kg)</label><input class="form-control" id="output_quantity" name="quantity" type="number" min="0.001" step="0.001" required></div>
        <div class="col-md-2"><label class="form-label" for="output_unit_cost">Unit cost</label><input class="form-control" id="output_unit_cost" name="unit_cost" type="number" min="0" step="0.001" required></div>
        <div class="col-md-3"><label class="form-label" for="output_expiry_date">Expiry date</label><input class="form-control" id="output_expiry_date" name="expiry_date" type="date"></div>
        <div class="col-md-3"><label class="form-label" for="output_qc_status">QC disposition</label><select class="form-select" id="output_qc_status" name="qc_status" required><option value="released">Released</option><option value="hold">Hold</option><option value="rejected">Rejected</option></select></div>
        <div class="col-12 text-end"><button class="btn btn-danger" type="submit"><i class="bi bi-plus-lg me-1"></i>Add output lot</button></div>
    </form>
</section>
<?php endif; ?>

<?php if ($entity === 'production_batch'): ?>
<section class="card-panel table-panel workflow-table">
    <div class="workflow-section-title"><div><p class="eyebrow">Detailed processing trace</p><h3>Stage measurements</h3></div><div class="d-flex gap-2"><a class="btn btn-sm btn-light border" href="<?= e(url('production.wastage')) ?>"><i class="bi bi-recycle me-1"></i>Wastage tab</a><a class="btn btn-sm btn-danger" href="<?= e(url('module',['name'=>'production_stage_measurements'])) ?>"><i class="bi bi-plus-lg me-1"></i>Record detailed reading</a></div></div>
    <div class="table-responsive"><table class="table align-middle data-table"><thead><tr><th>Reading</th><th>Stage</th><th>Measured</th><th>Gross kg</th><th>Net kg</th><th>Defeathered birds</th><th>Organ yield</th><th>Chiller C</th><th>Grading kg</th><th>Condemned</th><th>Packed birds</th><th>Packed kg</th><th>Storage C</th><th>Water</th></tr></thead><tbody>
        <?php if (empty($measurements)): ?><tr><td colspan="14"><div class="table-empty"><i class="bi bi-speedometer"></i><strong>No detailed readings yet</strong><span>The governed stage workflow creates minimum trace readings; operators can add full process measurements here.</span></div></td></tr><?php endif; ?>
        <?php foreach (($measurements ?? []) as $measurement): ?><tr><td><strong><?= e($measurement['measurement_number']) ?></strong></td><td><?= e(human_status($measurement['stage'])) ?></td><td><?= e(date('j M Y, g:i A',strtotime($measurement['measured_at']))) ?></td><td><?= e(number_format((float)$measurement['gross_weight_kg'],3)) ?></td><td><?= e(number_format((float)$measurement['net_weight_kg'],3)) ?></td><td><?= e((int)$measurement['defeathered_bird_count']) ?></td><td><small>Liver <?= e(number_format((float)$measurement['liver_weight_kg'],3)) ?> · Heart <?= e(number_format((float)$measurement['heart_weight_kg'],3)) ?> · Gizzard <?= e(number_format((float)$measurement['gizzard_weight_kg'],3)) ?></small></td><td><?= e($measurement['screw_chiller_temperature_c'] ?? '—') ?></td><td><?= e($measurement['graded_weight_kg'] === null ? '—' : number_format((float)$measurement['graded_weight_kg'],3)) ?></td><td><?= e($measurement['condemned_bird_count']) ?></td><td><?= e((int)$measurement['packed_bird_count']) ?></td><td><?= e(number_format((float)$measurement['packed_weight_kg'],3)) ?></td><td><?= e($measurement['storage_temperature_c'] ?? '—') ?></td><td><?= e($measurement['water_level_percent'] === null ? '—' : number_format((float)$measurement['water_level_percent'],1).'%') ?></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>

<section class="card-panel table-panel workflow-table">
    <div class="workflow-section-title"><div><p class="eyebrow">Inventory posting plan</p><h3>Finished output lots</h3></div><span><?= e(count($outputs ?? [])) ?> lots</span></div>
    <div class="table-responsive"><table class="table align-middle data-table"><thead><tr><th>Lot</th><th>Product</th><th>Grade</th><th>Quantity</th><th>Storage</th><th>QC</th><th>Inventory status</th><?php if ($productionOutputEntry): ?><th></th><?php endif; ?></tr></thead><tbody>
        <?php if (empty($outputs)): ?><tr><td colspan="8"><div class="table-empty"><i class="bi bi-box-seam"></i><strong>No output lots defined</strong><span>Add released finished goods before recording the final dispatch-ready stage.</span></div></td></tr><?php endif; ?>
        <?php foreach (($outputs ?? []) as $output): ?><tr><td><strong><?= e($output['lot_number']) ?></strong></td><td><?= e($output['sku'] . ' · ' . $output['product_name']) ?></td><td><?= e($output['grade']) ?></td><td><?= e(number_format((float) $output['quantity'],3)) ?> kg</td><td><?= e($output['zone_name']) ?></td><td><span class="badge rounded-pill text-bg-<?= e(status_class((string) $output['qc_status'])) ?>"><?= e(human_status((string) $output['qc_status'])) ?></span></td><td><?= e(human_status((string) ($output['lot_status'] ?: 'pending posting'))) ?></td><?php if ($productionOutputEntry): ?><td class="text-end"><?php if (!$output['inventory_lot_id']): ?><form action="<?= e(url('workflow.line.delete')) ?>" method="post" novalidate data-confirm-delete><?= Csrf::field() ?><input type="hidden" name="entity" value="production_batch"><input type="hidden" name="id" value="<?= e($record['id']) ?>"><input type="hidden" name="line_id" value="<?= e($output['id']) ?>"><button class="btn btn-sm btn-light border text-danger" type="submit" aria-label="Remove output"><i class="bi bi-trash"></i></button></form><?php endif; ?></td><?php endif; ?></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endif; ?>

<?php if ($entity === 'dispatch'): ?>
<section class="card-panel workflow-table">
    <div class="workflow-section-title"><div><p class="eyebrow">Cold-chain release control</p><h3>Vehicle pre-dispatch checklist</h3></div><span><?= e(count(array_filter($checks ?? [], static fn (array $check): bool => (bool) $check['passed']))) ?>/<?= e(count($checks ?? [])) ?> passed</span></div>
    <?php if (empty($checks)): ?><div class="alert alert-light border mb-0"><i class="bi bi-info-circle me-2"></i>Begin loading to generate the mandatory vehicle checklist.</div><?php else: ?>
    <div class="checklist-grid"><?php foreach ($checks as $check): ?><form action="<?= e(url('workflow.check.save')) ?>" method="post" class="checklist-item <?= $check['passed'] ? 'is-passed' : '' ?>" novalidate data-prevent-double-submit><?= Csrf::field() ?><input type="hidden" name="entity" value="dispatch"><input type="hidden" name="id" value="<?= e($record['id']) ?>"><input type="hidden" name="check_id" value="<?= e($check['id']) ?>"><label><input type="checkbox" name="passed" value="1" <?= $check['passed'] ? 'checked' : '' ?> <?= !$canManage || in_array($record['status'], ['in_transit','delivered','cancelled'], true) ? 'disabled' : '' ?>><span><strong><?= e($check['label']) ?></strong><small><?= $check['checked_by_name'] ? 'Checked by ' . e($check['checked_by_name']) : 'Mandatory before release' ?></small></span></label><?php if ($canManage && !in_array($record['status'], ['in_transit','delivered','cancelled'], true)): ?><input class="form-control form-control-sm" name="notes" value="<?= e($check['notes'] ?? '') ?>" maxlength="500" placeholder="Optional note"><button class="btn btn-sm btn-light border" type="submit">Save</button><?php endif; ?></form><?php endforeach; ?></div>
    <?php endif; ?>
</section>
<?php endif; ?>

<?php if (isset($lineColumns[$entity])): ?>
<section class="card-panel table-panel workflow-table">
    <div class="workflow-section-title"><div><p class="eyebrow">Transaction detail</p><h3><?= $entity === 'production_batch' ? 'Stage history' : ($entity === 'dispatch' ? 'Allocated delivery lines' : 'Lines & ledger') ?></h3></div><span><?= e(count($lines)) ?> records</span></div>
    <div class="table-responsive"><table class="table align-middle data-table"><thead><tr><?php foreach ($lineColumns[$entity] as $column): ?><th><?= e(human_status($column)) ?></th><?php endforeach; ?><?php if ($draftLines): ?><th></th><?php endif; ?></tr></thead><tbody>
        <?php if (!$lines): ?><tr><td colspan="<?= e(count($lineColumns[$entity]) + ($draftLines ? 1 : 0)) ?>"><div class="table-empty"><i class="bi bi-list-check"></i><strong>No lines yet</strong><span>Add lines before submitting this transaction.</span></div></td></tr><?php endif; ?>
        <?php foreach ($lines as $line): ?><tr><?php foreach ($lineColumns[$entity] as $column): ?><td><?= e($format($column, $line[$column] ?? null)) ?></td><?php endforeach; ?><?php if ($draftLines): ?><td class="text-end"><form action="<?= e(url('workflow.line.delete')) ?>" method="post" novalidate data-confirm-delete><?= Csrf::field() ?><input type="hidden" name="entity" value="<?= e($entity) ?>"><input type="hidden" name="id" value="<?= e($record['id']) ?>"><input type="hidden" name="line_id" value="<?= e($line['id']) ?>"><button class="btn btn-sm btn-light border text-danger" type="submit" aria-label="Remove line"><i class="bi bi-trash"></i></button></form></td><?php endif; ?></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php endif; ?>

<?php if ($related && isset($relatedColumns[$entity])): ?>
<section class="card-panel table-panel workflow-table">
    <div class="workflow-section-title"><div><p class="eyebrow">Linked controls</p><h3>Related records</h3></div><span><?= e(count($related)) ?> records</span></div>
    <div class="table-responsive"><table class="table align-middle data-table"><thead><tr><?php foreach ($relatedColumns[$entity] as $column): ?><th><?= e(human_status($column)) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach ($related as $row): ?><tr><?php foreach ($relatedColumns[$entity] as $column): ?><td><?= e($format($column, $row[$column] ?? null)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
</section>
<?php endif; ?>
