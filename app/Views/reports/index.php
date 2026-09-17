<div class="report-hero card-panel">
    <div>
        <p class="eyebrow">Management intelligence</p>
        <h2>Operational performance at a glance</h2>
        <p>Live summaries generated directly from MeatinOS transactions and quality records.</p>
    </div>
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <a class="btn btn-light border" href="<?= e(url('commercial')) ?>"><i class="bi bi-graph-up-arrow me-2"></i>Commercial analytics</a>
        <a class="btn btn-light border" href="<?= e(url('reports.scheduled')) ?>"><i class="bi bi-clock-history me-2"></i>Generated reports</a>
        <a class="btn btn-danger" href="<?= e(url('traceability')) ?>"><i class="bi bi-upc-scan me-2"></i>Trace a batch</a>
    </div>
</div>

<div class="report-kpi-grid">
    <div class="report-kpi">
        <span class="report-kpi-label">30-day yield</span>
        <strong class="report-kpi-val"><?= e(number_format($summary['yield'], 2)) ?>%</strong>
        <small class="report-kpi-sub">Output vs. input weight</small>
    </div>
    <div class="report-kpi">
        <span class="report-kpi-label">30-day mortality</span>
        <strong class="report-kpi-val"><?= e(number_format($summary['mortality'], 2)) ?>%</strong>
        <small class="report-kpi-sub">Transport mortality</small>
    </div>
    <div class="report-kpi">
        <span class="report-kpi-label">30-day sales</span>
        <strong class="report-kpi-val"><?= e(money($summary['sales'])) ?></strong>
        <small class="report-kpi-sub">Non-cancelled orders</small>
    </div>
    <div class="report-kpi">
        <span class="report-kpi-label">Receivables</span>
        <strong class="report-kpi-val"><?= e(money($summary['receivables'])) ?></strong>
        <small class="report-kpi-sub">Issued & overdue invoices</small>
    </div>
    <div class="report-kpi">
        <span class="report-kpi-label">Inventory value</span>
        <strong class="report-kpi-val"><?= e(money($summary['inventory'])) ?></strong>
        <small class="report-kpi-sub">Available & allocated lots</small>
    </div>
    <div class="report-kpi">
        <span class="report-kpi-label">Plant downtime</span>
        <strong class="report-kpi-val"><?= e(number_format($summary['downtime'])) ?> min</strong>
        <small class="report-kpi-sub">Last 30 days total</small>
    </div>
</div>

<div class="row g-4 mt-1">
    <div class="col-xl-7">
        <article class="report-card h-100">
            <div class="report-card-header">
                <div>
                    <h2><i class="bi bi-box-seam text-danger"></i>Inventory Position</h2>
                    <p>Available lots with nearest expiry</p>
                </div>
                <a class="btn btn-sm btn-light border" href="<?= e(url('reports.export', ['type' => 'inventory'])) ?>"><i class="bi bi-download me-1"></i>CSV</a>
            </div>
            <div class="table-responsive">
                <table class="table data-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 16%;">SKU</th>
                            <th style="width: 38%;">Product</th>
                            <th class="text-end" style="width: 23%;">Available</th>
                            <th class="text-center" style="width: 23%;">Nearest Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($stock)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-box-seam d-block mb-1 fs-4 text-secondary"></i>
                                    No inventory lots currently in stock.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($stock as $row): ?>
                                <tr>
                                    <td><strong class="font-monospace text-danger"><?= e($row['sku']) ?></strong></td>
                                    <td><span class="fw-medium"><?= e($row['name']) ?></span></td>
                                    <td class="text-end font-monospace"><?= e(number_format((float) $row['available'], 2)) ?> <span class="text-muted small"><?= e($row['unit']) ?></span></td>
                                    <td class="text-center"><?= !empty($row['nearest_expiry']) ? '<span class="badge bg-light text-dark border font-monospace">' . e(date('j M Y', strtotime((string) $row['nearest_expiry']))) . '</span>' : '<span class="text-muted">—</span>' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
    <div class="col-xl-5">
        <article class="report-card h-100">
            <div class="report-card-header">
                <div>
                    <h2><i class="bi bi-shield-check text-success"></i>Quality by Grade</h2>
                    <p>Inspection acceptance profile</p>
                </div>
                <a class="btn btn-sm btn-light border" href="<?= e(url('module', ['name' => 'quality_checks'])) ?>"><i class="bi bi-box-arrow-up-right me-1"></i>Open QC</a>
            </div>
            <div class="report-card-body">
                <div class="quality-bars">
                    <?php if (empty($quality)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-shield-check d-block mb-1 fs-4 text-secondary"></i>
                            No quality check records found.
                        </div>
                    <?php else: ?>
                        <?php foreach ($quality as $row): 
                            $total = max(1, (int) $row['accepted'] + (int) $row['rejected']); 
                            $pct = round(((int) $row['accepted'] / $total) * 100); 
                        ?>
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-1">
                                    <strong>Grade <?= e($row['grade']) ?></strong>
                                    <span class="text-muted small"><?= e($pct) ?>% accepted &middot; <?= e(number_format((int)$row['checks'])) ?> checks</span>
                                </div>
                                <div class="progress" style="height: 8px;">
                                    <div class="progress-bar <?= $pct >= 90 ? 'bg-success' : ($pct >= 75 ? 'bg-warning' : 'bg-danger') ?>" role="progressbar" style="width:<?= e($pct) ?>%" aria-valuenow="<?= e($pct) ?>" aria-valuemin="0" aria-valuemax="100"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </article>
    </div>
    <div class="col-xl-6">
        <article class="report-card h-100">
            <div class="report-card-header">
                <div>
                    <h2><i class="bi bi-journal-bookmark text-primary"></i>General Ledger Snapshot</h2>
                    <p>Posted debits and credits by account type</p>
                </div>
                <span class="badge bg-light text-secondary border font-monospace">GL · Posted</span>
            </div>
            <div class="table-responsive">
                <table class="table data-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th style="width: 28%;">Account Type</th>
                            <th class="text-end" style="width: 24%;">Debit</th>
                            <th class="text-end" style="width: 24%;">Credit</th>
                            <th class="text-end" style="width: 24%;">Net Balance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($finance)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-journal-text d-block mb-1 fs-4 text-secondary"></i>
                                    No posted journal entries recorded yet.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($finance as $row): 
                                $net = (float) $row['debit'] - (float) $row['credit'];
                            ?>
                                <tr>
                                    <td><strong><?= e(ucfirst($row['account_type'])) ?></strong></td>
                                    <td class="text-end font-monospace"><?= e(money($row['debit'])) ?></td>
                                    <td class="text-end font-monospace"><?= e(money($row['credit'])) ?></td>
                                    <td class="text-end font-monospace fw-semibold <?= $net < 0 ? 'text-danger' : 'text-success' ?>"><?= e(money($net)) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </article>
    </div>
    <div class="col-xl-6">
        <article class="report-card h-100">
            <div class="report-card-header">
                <div>
                    <h2><i class="bi bi-diagram-3 text-warning"></i>Analytics & Traceability</h2>
                    <p>Operational decision support and full audit tools</p>
                </div>
                <span class="badge bg-light text-secondary border font-monospace">Quick Access</span>
            </div>
            <div class="report-card-body">
                <div class="report-quick-links">
                    <a href="<?= e(url('commercial')) ?>" class="report-quick-item">
                        <div class="quick-icon bg-primary-subtle text-primary"><i class="bi bi-graph-up-arrow"></i></div>
                        <div class="quick-info">
                            <strong>Commercial Analytics</strong>
                            <small>Salesman performance, customer rankings, and vendor price comparisons</small>
                        </div>
                        <i class="bi bi-chevron-right quick-arrow"></i>
                    </a>
                    <a href="<?= e(url('intelligence')) ?>" class="report-quick-item">
                        <div class="quick-icon bg-warning-subtle text-warning"><i class="bi bi-stars"></i></div>
                        <div class="quick-info">
                            <strong>Operational Intelligence</strong>
                            <small>7-day demand forecasts, stock cover days, and automated exception rules</small>
                        </div>
                        <i class="bi bi-chevron-right quick-arrow"></i>
                    </a>
                    <a href="<?= e(url('reports.scheduled')) ?>" class="report-quick-item">
                        <div class="quick-icon bg-success-subtle text-success"><i class="bi bi-clock-history"></i></div>
                        <div class="quick-info">
                            <strong>Generated Reports Archive</strong>
                            <small>Automated scheduled exports, cron worker status, and private CSV downloads</small>
                        </div>
                        <i class="bi bi-chevron-right quick-arrow"></i>
                    </a>
                    <a href="<?= e(url('traceability')) ?>" class="report-quick-item">
                        <div class="quick-icon bg-danger-subtle text-danger"><i class="bi bi-upc-scan"></i></div>
                        <div class="quick-info">
                            <strong>Batch 360° Traceability</strong>
                            <small>Full farm-to-customer trace for batches, birds, lots, invoices, and dispatch reefers</small>
                        </div>
                        <i class="bi bi-chevron-right quick-arrow"></i>
                    </a>
                </div>
            </div>
        </article>
    </div>
    <div class="col-xl-12">
        <article class="report-card h-100">
            <div class="report-card-header">
                <div>
                    <h2><i class="bi bi-file-earmark-spreadsheet text-danger"></i>Downloadable Reports</h2>
                    <p>Excel-ready CSV exports across procurement, production, inventory, quality, sales, dispatch, HR and finance</p>
                </div>
                <span class="badge bg-danger-subtle text-danger font-monospace px-3 py-2"><?= count($catalog) ?> reports available</span>
            </div>
            <div class="report-card-body">
                <div class="export-grid">
                    <?php foreach ($catalog as $key => $label): ?>
                        <a href="<?= e(url('reports.export', ['type' => $key])) ?>" class="export-card">
                            <div class="export-icon"><i class="bi bi-file-earmark-spreadsheet"></i></div>
                            <div class="export-info">
                                <strong><?= e($label) ?></strong>
                                <small>Live Excel-ready CSV</small>
                            </div>
                            <div class="export-action"><i class="bi bi-download"></i></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        </article>
    </div>
</div>
