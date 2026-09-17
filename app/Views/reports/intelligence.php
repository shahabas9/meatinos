<div class="report-hero intelligence-hero">
    <div><p class="eyebrow">Auditable decision support</p><h2>Operational Intelligence</h2><p>Transparent forecasts and recommendations generated from live Meatin transactions, thresholds, and exception rules.</p></div>
    <a class="btn btn-danger" href="<?= e(url('reports')) ?>"><i class="bi bi-file-earmark-bar-graph me-2"></i>Open reports</a>
</div>

<div class="report-kpi-grid">
    <div class="report-kpi"><span>30-day yield</span><strong><?= e(number_format($metrics['yield'], 2)) ?>%</strong><small>Output vs. input</small></div>
    <div class="report-kpi"><span>30-day mortality</span><strong><?= e(number_format($metrics['mortality'], 2)) ?>%</strong><small>Receiving risk</small></div>
    <div class="report-kpi"><span>7-day production forecast</span><strong><?= e(number_format($metrics['forecast_production'])) ?> kg</strong><small>30-day run-rate model</small></div>
    <div class="report-kpi"><span>7-day sales forecast</span><strong><?= e(money($metrics['forecast_sales'])) ?></strong><small>30-day run-rate model</small></div>
    <div class="report-kpi"><span>Expiry risk</span><strong><?= e($metrics['expiry_risk']) ?> lots</strong><small>Due within seven days</small></div>
    <div class="report-kpi"><span>Critical exceptions</span><strong><?= e($metrics['critical_alerts']) ?></strong><small>Open and actionable</small></div>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-5"><article class="card-panel h-100"><div class="panel-heading"><div><h2>Recommended Actions</h2><p>Priority-ranked, explainable operating rules</p></div></div><div class="intelligence-list"><?php foreach($recommendations as [$severity,$recommendationTitle,$action,$basis]): $theme=$severity==='critical'?'danger':$severity; ?><div class="intelligence-item"><span class="activity-icon bg-<?= e($theme) ?>-subtle text-<?= e($theme) ?>"><i class="bi bi-<?= $severity==='critical'?'exclamation-triangle':'lightbulb' ?>"></i></span><span><strong><?= e($recommendationTitle) ?></strong><small><?= e($action) ?></small><em>Basis: <?= e($basis) ?></em></span></div><?php endforeach; ?></div></article></div>
    <div class="col-xl-7"><article class="card-panel table-panel h-100"><div class="panel-heading p-3 mb-0"><div><h2>Demand & Stock Cover</h2><p>Seven-day product demand projection and current days of cover</p></div></div><div class="table-responsive"><table class="table data-table align-middle"><thead><tr><th>SKU</th><th>Product</th><th class="text-end">Available</th><th class="text-end">30-day demand</th><th class="text-end">7-day forecast</th><th class="text-end">Days cover</th></tr></thead><tbody><?php foreach($demand as $row): ?><tr><td><strong class="font-monospace text-danger"><?= e($row['sku']) ?></strong></td><td><?= e($row['name']) ?></td><td class="text-end font-monospace"><?= e(number_format((float)$row['available'],2)) ?> <span class="text-muted small"><?= e($row['unit']) ?></span></td><td class="text-end font-monospace"><?= e(number_format((float)$row['sold_30'],2)) ?> <span class="text-muted small"><?= e($row['unit']) ?></span></td><td class="text-end font-monospace"><?= e(number_format((float)$row['forecast_7'],2)) ?> <span class="text-muted small"><?= e($row['unit']) ?></span></td><td class="text-end font-monospace"><?= $row['cover_days'] === null ? '<span class="text-muted">No history</span>' : e(number_format((float)$row['cover_days'],1)) . ' <span class="text-muted small">days</span>' ?></td></tr><?php endforeach; ?></tbody></table></div></article></div>
</div>

<div class="alert alert-secondary mt-4 mb-0"><i class="bi bi-info-circle me-2"></i>Forecasts are deterministic run-rate estimates, not generative AI. They are intentionally explainable and should be reviewed by authorized managers before action.</div>
