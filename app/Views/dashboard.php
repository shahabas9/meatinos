<?php
$stageLabels = [
    'receiving' => ['Bird Receiving', 'bi-clipboard2-pulse'],
    'slaughtering' => ['Slaughtering', 'bi-scissors'],
    'scalding' => ['Scalding', 'bi-thermometer-half'],
    'defeathering' => ['Defeathering', 'bi-wind'],
    'evisceration' => ['Evisceration', 'bi-droplet'],
    'chilling' => ['Screw Chilling', 'bi-snow2'],
    'grading' => ['Grading', 'bi-shield-check'],
    'packing' => ['Packing', 'bi-box-seam'],
    'storage' => ['Store Keeping', 'bi-house-lock'],
    'dispatch' => ['Dispatch', 'bi-truck'],
];
$stageRows = [];
foreach ($stages as $row) $stageRows[$row['stage']] = $row;
$productionSeries = array_map(static fn ($row) => (float) $row['production'], $trend);
$salesSeries = array_map(static fn ($row) => (float) $row['sales'], $trend);
$trendLabels = array_map(static fn ($row) => date('j M', strtotime((string) $row['day'])), $trend);
$productionTotal = max(1, (float) $cards['processed']);
?>
<section class="dashboard-grid">
    <div class="date-filter-row">
        <div class="system-health <?= $cards['critical_alerts'] > 0 ? 'warning' : '' ?>"><i class="bi bi-<?= $cards['critical_alerts'] > 0 ? 'exclamation-triangle' : 'shield-check' ?>"></i><span><?= $cards['critical_alerts'] > 0 ? e(number_format($cards['critical_alerts'])) . ' critical exception' . ($cards['critical_alerts'] == 1 ? '' : 's') . ' open' : 'All core services operational' ?></span></div>
        <button class="btn btn-light border"><i class="bi bi-calendar3 me-2"></i><?= e(date('j F Y, l')) ?></button>
    </div>

    <div class="kpi-grid">
        <article class="kpi-card accent-red">
            <div><span>Live Birds Received</span><strong><?= e(number_format((float) $cards['birds'])) ?></strong><small><i class="bi bi-arrow-up"></i> Today</small></div>
            <div class="kpi-icon"><i class="bi bi-clipboard2-pulse"></i></div>
            <div class="sparkline"><span></span></div>
        </article>
        <article class="kpi-card accent-green">
            <div><span>Processed Today</span><strong><?= e(number_format((float) $cards['processed'])) ?> <em>kg</em></strong><small><i class="bi bi-arrow-up"></i> Live production</small></div>
            <div class="kpi-icon"><i class="bi bi-speedometer2"></i></div>
            <div class="sparkline"><span></span></div>
        </article>
        <article class="kpi-card accent-purple">
            <div><span>Sales Today</span><strong><?= e(money($cards['sales'])) ?></strong><small><i class="bi bi-arrow-up"></i> Approved orders</small></div>
            <div class="kpi-icon"><i class="bi bi-cart-check"></i></div>
            <div class="sparkline"><span></span></div>
        </article>
        <article class="kpi-card accent-orange">
            <div><span>Orders Pending</span><strong><?= e(number_format((float) $cards['orders'])) ?></strong><small>Awaiting fulfillment</small></div>
            <div class="kpi-icon"><i class="bi bi-clipboard-check"></i></div>
            <div class="sparkline"><span></span></div>
        </article>
        <article class="kpi-card accent-blue">
            <div><span>Storage Utilization</span><strong><?= e(number_format((float) $cards['storage_percent'], 1)) ?>%</strong><small><?= e(number_format((float) $cards['storage_used'])) ?> kg stored</small></div>
            <div class="kpi-icon"><i class="bi bi-snow2"></i></div>
            <div class="sparkline"><span></span></div>
        </article>
    </div>

    <article class="card-panel process-card span-7">
        <div class="panel-heading"><div><h2>Production Process Flow</h2><p><?= $productionBatch ? e($productionBatch['batch_number'].' · '.number_format((int)$productionBatch['birds_input']).' birds · '.number_format((float)$productionBatch['input_weight_kg'],2).' kg input') : 'No production batch recorded' ?></p></div><span class="badge-soft-success"><i class="bi bi-shield-check"></i> Batch traceability</span></div>
        <div class="process-flow">
            <?php $index = 0; foreach ($stageLabels as $key => [$label, $icon]): $index++; $row = $stageRows[$key] ?? null; ?>
                <div class="process-step <?= e($row['state'] ?? 'pending') ?>">
                    <div class="step-icon"><i class="bi <?= e($icon) ?>"></i></div>
                    <small><?= $index ?></small><strong><?= e($label) ?></strong><span><?= e($row['status_label'] ?? 'Pending') ?></span>
                </div>
                <?php if ($index < count($stageLabels)): ?><i class="bi bi-arrow-right process-arrow"></i><?php endif; ?>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card-panel production-overview span-4">
        <div class="panel-heading"><div><h2>Today's Production Overview</h2><p>Finished product mix</p></div></div>
        <div class="donut-wrap">
            <div class="donut-chart" style="--p1:60;--p2:85;--p3:95"><div><strong><?= e(number_format((float) $cards['processed'])) ?></strong><span>kg</span></div></div>
            <div class="legend-list">
                <span><i class="legend-dot dot-blue"></i>Whole Bird <strong>60%</strong></span>
                <span><i class="legend-dot dot-green"></i>Boneless <strong>25%</strong></span>
                <span><i class="legend-dot dot-orange"></i>Wings <strong>10%</strong></span>
                <span><i class="legend-dot dot-purple"></i>By-products <strong>5%</strong></span>
            </div>
        </div>
    </article>

    <article class="card-panel alert-panel span-4 row-span-2">
        <div class="panel-heading"><div><h2>Alerts & Notifications</h2><p>Exceptions requiring attention</p></div><a href="<?= e(url('module', ['name' => 'alerts'])) ?>">View all</a></div>
        <div class="alert-list">
            <?php foreach ($alerts as $alert): ?>
                <a href="<?= e(url('module', ['name' => 'alerts', 'edit' => $alert['id']])) ?>" class="alert-item">
                    <span class="alert-icon bg-<?= e(status_class($alert['severity'])) ?>-subtle text-<?= e(status_class($alert['severity'])) ?>"><i class="bi <?= $alert['severity'] === 'critical' ? 'bi-thermometer-high' : ($alert['source_type'] === 'maintenance' ? 'bi-tools' : 'bi-exclamation-triangle') ?>"></i></span>
                    <span><strong><?= e($alert['title']) ?></strong><small><?= e($alert['message']) ?></small></span>
                    <time><?= e(date('g:i A', strtotime((string) $alert['triggered_at']))) ?></time>
                </a>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card-panel chart-panel span-7">
        <div class="panel-heading"><div><h2>Production & Sales</h2><p>Last seven days</p></div><span class="badge text-bg-light">This week</span></div>
        <div class="chart-legend"><span><i class="legend-line red"></i>Production (kg)</span><span><i class="legend-line blue"></i>Sales (INR)</span></div>
        <div class="chart-container"><canvas id="productionSalesChart" data-labels='<?= e(json_encode($trendLabels)) ?>' data-production='<?= e(json_encode($productionSeries)) ?>' data-sales='<?= e(json_encode($salesSeries)) ?>'></canvas></div>
    </article>

    <article class="card-panel revenue-panel span-4">
        <div class="panel-heading"><div><h2>Revenue Summary</h2><p>This month</p></div></div>
        <div class="finance-metric"><span>Total Revenue</span><strong><?= e(money($cards['revenue'])) ?></strong><em class="text-success">Recorded</em></div>
        <div class="finance-metric"><span>Total Procurement</span><strong><?= e(money($cards['cost'])) ?></strong><em class="text-warning">Committed</em></div>
        <div class="finance-metric profit <?= $cards['profit'] < 0 ? 'loss' : '' ?>"><span>Operating Contribution</span><strong><?= e(money($cards['profit'])) ?></strong><em class="<?= $cards['profit'] < 0 ? 'text-danger' : 'text-success' ?>"><i class="bi bi-graph-<?= $cards['profit'] < 0 ? 'down' : 'up' ?>-arrow"></i></em></div>
    </article>

    <article class="card-panel vehicle-panel span-4">
        <div class="panel-heading"><div><h2>Live Vehicle Tracking</h2><p>Reefer fleet status</p></div><a href="<?= e(url('module', ['name' => 'dispatches'])) ?>">View dispatch</a></div>
        <div class="vehicle-list">
            <?php foreach ($dispatches as $dispatch): ?>
                <div class="vehicle-item"><span class="vehicle-icon"><i class="bi bi-truck"></i></span><span><strong><?= e($dispatch['registration_number']) ?></strong><small><?= e($dispatch['route_name']) ?> · <?= e($dispatch['temperature_c']) ?>C</small></span><span class="badge rounded-pill text-bg-<?= e(status_class($dispatch['status'])) ?>"><?= e(human_status((string) $dispatch['status'])) ?></span></div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card-panel span-4">
        <div class="panel-heading"><div><h2>Top Stocked Products</h2><p>Available finished goods</p></div><a href="<?= e(url('module', ['name' => 'inventory_lots'])) ?>">View stock</a></div>
        <div class="product-list">
            <?php foreach ($products as $product): ?>
                <div class="product-item"><span class="product-thumb"><i class="bi bi-box-seam"></i></span><span><strong><?= e($product['name']) ?></strong><small><?= e(number_format((float) $product['quantity'])) ?> <?= e($product['unit']) ?></small></span><span class="badge-soft-success">FEFO</span></div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card-panel span-4">
        <div class="panel-heading"><div><h2>Cold Storage Status</h2><p>Temperature and capacity</p></div></div>
        <div class="zone-list">
            <?php foreach (array_slice($zones, 0, 3) as $zone): $percent = (float) $zone['capacity_kg'] > 0 ? min(100, ((float) $zone['used_kg'] / (float) $zone['capacity_kg']) * 100) : 0; ?>
                <div class="zone-item"><div><span class="zone-icon"><i class="bi bi-snow2"></i></span><span><strong><?= e($zone['name']) ?></strong><small><?= e($zone['current_temperature_c']) ?>C · <?= e(number_format($percent, 0)) ?>% used</small></span></div><div class="progress" role="progressbar" aria-valuenow="<?= e($percent) ?>" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar" style="width:<?= e($percent) ?>%"></div></div></div>
            <?php endforeach; ?>
        </div>
    </article>

    <article class="card-panel span-4">
        <div class="panel-heading"><div><h2>Employee Attendance</h2><p>Today's workforce</p></div><a href="<?= e(url('module', ['name' => 'attendance'])) ?>">Details</a></div>
        <?php $attendanceTotal = max(1, $cards['attendance_present'] + $cards['attendance_absent'] + $cards['attendance_leave']); $presentPct = round(($cards['attendance_present'] / $attendanceTotal) * 100); ?>
        <div class="attendance-wrap"><div class="attendance-ring" style="--attendance:<?= e($presentPct) ?>"><strong><?= e($presentPct) ?>%</strong></div><div class="legend-list"><span><i class="legend-dot dot-green"></i>Present <strong><?= e(number_format($cards['attendance_present'])) ?></strong></span><span><i class="legend-dot dot-red"></i>Absent <strong><?= e(number_format($cards['attendance_absent'])) ?></strong></span><span><i class="legend-dot dot-orange"></i>On leave <strong><?= e(number_format($cards['attendance_leave'])) ?></strong></span></div></div>
    </article>

    <article class="card-panel span-4">
        <div class="panel-heading"><div><h2>Recent Activities</h2><p>Audited actions</p></div><?php if (\MeatinOS\Core\Auth::can('audit.view')): ?><a href="<?= e(url('audit')) ?>">View all</a><?php endif; ?></div>
        <div class="activity-list">
            <?php foreach (array_slice($activities, 0, 5) as $activity): ?>
                <div class="activity-item"><span class="activity-icon"><i class="bi bi-check2-circle"></i></span><span><strong><?= e($activity['description']) ?></strong><small><?= e($activity['user_name'] ?: 'System') ?> · <?= e(date('g:i A', strtotime((string) $activity['created_at']))) ?></small></span></div>
            <?php endforeach; ?>
        </div>
    </article>
</section>
