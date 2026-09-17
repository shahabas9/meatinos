<section class="report-hero wastage-hero">
    <div><p class="eyebrow">Separate production control</p><h2>Production Wastage Tracking</h2><p>Review process-stage loss, yield-record waste and deboning waste independently from finished production output.</p></div>
    <div class="d-flex gap-2"><a class="btn btn-light border" href="<?= e(url('production.wastage.export',['from'=>$from,'to'=>$to])) ?>"><i class="bi bi-download me-2"></i>Stage CSV</a><a class="btn btn-danger" href="<?= e(url('production.wastage.export',['from'=>$from,'to'=>$to,'type'=>'damaged'])) ?>"><i class="bi bi-download me-2"></i>Damaged birds CSV</a></div>
</section>

<form method="get" class="card-panel wastage-filter" novalidate>
    <input type="hidden" name="route" value="production.wastage">
    <div><label class="form-label" for="wastage_from">From</label><input class="form-control" id="wastage_from" type="date" name="from" value="<?= e($from) ?>"></div>
    <div><label class="form-label" for="wastage_to">To</label><input class="form-control" id="wastage_to" type="date" name="to" value="<?= e($to) ?>"></div>
    <button class="btn btn-dark" type="submit"><i class="bi bi-funnel me-2"></i>Apply period</button>
</form>

<div class="update-summary-grid wastage-summary">
    <article class="card-panel update-summary-card"><span>Damaged birds</span><strong><?= e(number_format((float)$summary['damaged_birds'])) ?> birds / <?= e(number_format((float)$summary['damaged'],3)) ?> kg</strong></article>
    <article class="card-panel update-summary-card"><span>Stage process waste</span><strong><?= e(number_format((float)$summary['stage'],3)) ?> kg</strong></article>
    <article class="card-panel update-summary-card"><span>Condemned weight</span><strong><?= e(number_format((float)$summary['condemned'],3)) ?> kg</strong></article>
    <article class="card-panel update-summary-card"><span>Yield-record waste</span><strong><?= e(number_format((float)$summary['yield'],3)) ?> kg</strong></article>
    <article class="card-panel update-summary-card"><span>Deboning waste</span><strong><?= e(number_format((float)$summary['deboning'],3)) ?> kg</strong></article>
</div>

<section class="card-panel table-panel workflow-table">
    <ul class="nav nav-tabs wastage-tabs" role="tablist">
        <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#damaged-birds" type="button">Damaged birds <span><?= e(count($damagedRows)) ?></span></button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#stage-waste" type="button">Stage waste <span><?= e(count($stageRows)) ?></span></button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#yield-waste" type="button">Yield waste <span><?= e(count($yieldRows)) ?></span></button></li>
        <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#deboning-waste" type="button">Deboning waste <span><?= e(count($deboningRows)) ?></span></button></li>
    </ul>
    <div class="tab-content">
        <div class="tab-pane fade show active" id="damaged-birds"><?php if(!$damagedRows): ?><div class="table-empty"><i class="bi bi-exclamation-octagon"></i><strong>No damaged birds in this period</strong><span>Use Damaged Bird Wastage to record quantity, stage, and reason.</span></div><?php else: ?><div class="table-responsive"><table class="table data-table align-middle"><thead><tr><th>Record</th><th>Batch</th><th>Stage</th><th>Birds</th><th>Weight kg</th><th>Reason</th><th>Disposition</th><th>Recorded</th><th>Status</th></tr></thead><tbody><?php foreach($damagedRows as $row): ?><tr><td><strong><?= e($row['damage_number']) ?></strong></td><td><?= e($row['batch_number']) ?></td><td><?= e(human_status($row['stage'])) ?></td><td><?= e($row['damaged_bird_count']) ?></td><td><?= e(number_format((float)$row['damaged_weight_kg'],3)) ?></td><td><?= e(human_status($row['reason_category'])) ?><small class="d-block text-muted"><?= e($row['reason_details']) ?></small></td><td><?= e(human_status($row['disposition'])) ?></td><td><?= e(date('j M Y, g:i A',strtotime($row['recorded_at']))) ?></td><td><?= e(human_status($row['status'])) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></div>
        <div class="tab-pane fade" id="stage-waste">
            <?php if (!$stageRows): ?><div class="table-empty"><i class="bi bi-recycle"></i><strong>No stage wastage in this period</strong><span>Recorded waste categories will appear here without mixing with finished output.</span></div><?php else: ?>
            <div class="table-responsive"><table class="table data-table align-middle"><thead><tr><th>Reading</th><th>Batch</th><th>Stage</th><th>Measured</th><th>Blood</th><th>Head</th><th>Defeathering</th><th>Evisceration</th><th>Skin</th><th>Condemned</th><th>Packaging</th><th>Total</th></tr></thead><tbody><?php foreach($stageRows as $row): ?><tr><td><strong><?= e($row['measurement_number']) ?></strong></td><td><?= e($row['batch_number']) ?></td><td><?= e(human_status($row['stage'])) ?></td><td><?= e(date('j M Y, g:i A',strtotime($row['measured_at']))) ?></td><?php foreach(['blood_loss_kg','head_waste_kg','defeathering_waste_kg','evisceration_waste_kg','peeled_skin_waste_kg','condemned_weight_kg','packaging_waste_kg','total_waste_kg'] as $field): ?><td><?= e(number_format((float)$row[$field],3)) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>
        </div>
        <div class="tab-pane fade" id="yield-waste">
            <?php if (!$yieldRows): ?><div class="table-empty"><i class="bi bi-percent"></i><strong>No yield waste in this period</strong></div><?php else: ?><div class="table-responsive"><table class="table data-table align-middle"><thead><tr><th>Batch</th><th>Production date</th><th>Waste kg</th><th>Waste percent</th><th>By-product kg</th><th>Calculated</th></tr></thead><tbody><?php foreach($yieldRows as $row): ?><tr><td><strong><?= e($row['batch_number']) ?></strong></td><td><?= e(date('j M Y',strtotime($row['production_date']))) ?></td><td><?= e(number_format((float)$row['waste_weight_kg'],3)) ?></td><td><?= e(number_format((float)$row['waste_percent'],3)) ?>%</td><td><?= e(number_format((float)$row['by_product_weight_kg'],3)) ?></td><td><?= e(date('j M Y, g:i A',strtotime($row['calculated_at']))) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </div>
        <div class="tab-pane fade" id="deboning-waste">
            <?php if (!$deboningRows): ?><div class="table-empty"><i class="bi bi-scissors"></i><strong>No deboning waste in this period</strong></div><?php else: ?><div class="table-responsive"><table class="table data-table align-middle"><thead><tr><th>Record</th><th>Batch</th><th>Processed</th><th>Input kg</th><th>Bone kg</th><th>Waste kg</th><th>Yield</th></tr></thead><tbody><?php foreach($deboningRows as $row): ?><tr><td><strong><?= e($row['deboning_number']) ?></strong></td><td><?= e($row['batch_number']) ?></td><td><?= e(date('j M Y, g:i A',strtotime($row['processed_at']))) ?></td><td><?= e(number_format((float)$row['input_weight_kg'],3)) ?></td><td><?= e(number_format((float)$row['bone_weight_kg'],3)) ?></td><td><?= e(number_format((float)$row['waste_weight_kg'],3)) ?></td><td><?= e(number_format((float)$row['yield_percent'],3)) ?>%</td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?>
        </div>
    </div>
</section>
