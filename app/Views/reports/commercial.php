<?php
$labels=['salesman'=>'Salesman Performance','sales_received_person'=>'Sales by Receiving Staff','customer_ranking'=>'Customer Ranking','customer_comparison'=>'Customer Comparison','vendor_comparison'=>'Vendor Comparison','customer_ledger'=>'Monthly Customer Ledger','price_comparison'=>'Market vs Rate Card'];
$icons=['salesman'=>'bi-person-lines-fill','sales_received_person'=>'bi-person-check','customer_ranking'=>'bi-trophy','customer_comparison'=>'bi-people','vendor_comparison'=>'bi-buildings','customer_ledger'=>'bi-journal-text','price_comparison'=>'bi-currency-exchange'];
$format=static function(string $column,mixed $value): string {
    if ($value===null || $value==='') return '—';
    if (preg_match('/(sales|value|price|balance|amount|cost|outstanding|overdue|variance)$/',$column)) return money($value);
    if (str_contains($column,'percent')) return number_format((float)$value,2).'%';
    if (str_ends_with($column,'_at') && strtotime((string)$value)) return date('j M Y, g:i A',strtotime((string)$value));
    if (str_ends_with($column,'_date') || $column==='month_start') return strtotime((string)$value) ? date('j M Y',strtotime((string)$value)) : (string)$value;
    return (string)$value;
};
?>
<div class="report-hero"><div><p class="eyebrow">Sales, CRM & procurement intelligence</p><h2>Commercial control center</h2><p>Rank customers, compare vendors and local market pricing, review salesman performance, and verify monthly opening and closing balances.</p></div><a class="btn btn-danger" href="<?= e(url('module',['name'=>'report_schedules'])) ?>"><i class="bi bi-clock-history me-2"></i>Schedule a report</a></div>
<form method="get" class="card-panel commercial-filter">
    <input type="hidden" name="route" value="commercial">
    <div><label class="form-label">Salesman</label><select class="form-select" name="salesman_id"><option value="">Select a salesman...</option><?php foreach($salespeople as $person): ?><option value="<?= e($person['id']) ?>" <?= (int)$salesmanId===(int)$person['id']?'selected':'' ?>><?= e($person['employee_number'].' - '.$person['full_name']) ?></option><?php endforeach; ?></select></div>
    <div><label class="form-label">Month</label><input class="form-control" type="month" name="month" value="<?= e($month) ?>"></div>
    <button class="btn btn-danger" type="submit"><i class="bi bi-funnel me-2"></i>Show order details</button>
</form>
<?php if($selectedSalesman && $salesmanSummary): ?>
<section class="card-panel table-panel workflow-table salesman-detail-card">
    <div class="workflow-section-title"><div><p class="eyebrow">Selected monthly performance</p><h3><?= e($selectedSalesman['full_name'].' - '.date('F Y',strtotime($month.'-01'))) ?></h3></div><a href="<?= e(url('reports.export',['type'=>'salesman_detail','salesman_id'=>$salesmanId,'month'=>$month])) ?>"><i class="bi bi-download me-1"></i>CSV</a></div>
    <div class="salesman-summary"><div><span>Orders</span><strong><?= e(number_format((int)$salesmanSummary['orders'])) ?></strong></div><div><span>Item quantity</span><strong><?= e(number_format((float)$salesmanSummary['quantity'],2)) ?></strong></div><div><span>Sales value</span><strong><?= e(money($salesmanSummary['sales_value'])) ?></strong></div></div>
    <?php if(!$salesmanDetails): ?><div class="table-empty"><i class="bi bi-cart"></i><strong>No orders in this month</strong><span>Choose another month or salesman.</span></div><?php else: ?><div class="table-responsive"><table class="table data-table align-middle"><thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>SKU</th><th>Product</th><th class="text-end">Quantity</th><th class="text-end">Unit price</th><th class="text-end">Line total</th><th>Status</th></tr></thead><tbody><?php foreach($salesmanDetails as $row): ?><tr><td><strong><?= e($row['order_number']) ?></strong></td><td><?= e(date('j M Y',strtotime((string)$row['order_date']))) ?></td><td><?= e($row['customer']) ?></td><td><strong class="font-monospace text-danger"><?= e($row['sku']) ?></strong></td><td><?= e($row['product']) ?></td><td class="text-end font-monospace"><?= e(number_format((float)$row['quantity'],2)) ?></td><td class="text-end font-monospace"><?= e(money($row['unit_price'])) ?></td><td class="text-end font-monospace fw-semibold"><?= e(money($row['line_total'])) ?></td><td><span class="badge rounded-pill text-bg-<?= e(status_class((string)$row['status'])) ?>"><?= e(human_status((string)$row['status'])) ?></span></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section>
<?php endif; ?>
<div class="row g-4 mt-1">
<?php foreach($datasets as $type=>$rows): ?>
<div class="col-12"><section class="card-panel table-panel workflow-table"><div class="workflow-section-title"><div><p class="eyebrow">Live transactional analysis</p><h3><i class="bi <?= e($icons[$type]) ?> me-2 text-danger"></i><?= e($labels[$type]) ?></h3></div><a class="btn btn-sm btn-light border" href="<?= e(url('reports.export',['type'=>$type])) ?>"><i class="bi bi-download me-1"></i>CSV</a></div>
<?php if(!$rows): ?><div class="table-empty"><i class="bi <?= e($icons[$type]) ?>"></i><strong>No report rows yet</strong><span>Rows appear automatically when the linked transactions are posted.</span></div><?php else: $columns=array_keys($rows[0]); ?><div class="table-responsive"><table class="table data-table align-middle"><thead><tr><?php foreach($columns as $column): $isNum = (bool) preg_match('/(quantity|orders|birds|price|value|sales|amount|balance|cost|outstanding|overdue|variance|percent)$/', $column); ?><th class="<?= $isNum ? 'text-end' : '' ?>"><?= e(human_status($column)) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($rows as $row): ?><tr><?php foreach($columns as $column): $isNum = (bool) preg_match('/(quantity|orders|birds|price|value|sales|amount|balance|cost|outstanding|overdue|variance|percent)$/', $column); ?><td class="<?= $isNum ? 'text-end font-monospace' : '' ?>"><?= e($format($column,$row[$column] ?? null)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
</section></div><?php endforeach; ?>
</div>
