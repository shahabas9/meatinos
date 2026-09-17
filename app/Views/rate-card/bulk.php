<?php use MeatinOS\Core\Csrf; ?>
<div class="report-hero">
    <div><p class="eyebrow">Sales pricing</p><h2>Bulk Rate Entry</h2><p>Enter the whole-bird base price once, apply the default margin, then review or override each finished-product and by-product rate.</p></div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-light border" href="<?= e(url('module',['name'=>'rate_cards'])) ?>"><i class="bi bi-arrow-left me-2"></i>Rate cards</a>
        <?php if ($card): ?><a class="btn btn-danger" href="<?= e(url('rate-card.export',['rate_card_id'=>$card['id']])) ?>"><i class="bi bi-download me-2"></i>Export CSV</a><?php endif; ?>
    </div>
</div>

<?php if (!$cards): ?>
    <div class="card-panel empty-state"><i class="bi bi-card-list empty-icon"></i><h2>Create a rate card first</h2><p>Add a draft or active rate card, then return here to enter the complete product price list.</p><a class="btn btn-danger" href="<?= e(url('module',['name'=>'rate_cards'])) ?>">Open rate cards</a></div>
<?php else: ?>
    <form method="get" class="card-panel bulk-rate-selector">
        <input type="hidden" name="route" value="rate-card.bulk">
        <div><label class="form-label" for="rateCardSelector">Rate card</label><select class="form-select" id="rateCardSelector" name="rate_card_id" onchange="if (window.MeatinOSNav) { MeatinOSNav.navigate('?route=rate-card.bulk&rate_card_id=' + encodeURIComponent(this.value)); } else { this.form.submit(); }"><?php foreach ($cards as $option): ?><option value="<?= e($option['id']) ?>" <?= (int)$option['id']===(int)$card['id']?'selected':'' ?>><?= e($option['rate_card_number'].' - '.$option['name'].' ('.human_status((string)$option['status']).')') ?></option><?php endforeach; ?></select></div>
        <div class="bulk-rate-meta"><span>Valid from</span><strong><?= e(date('j M Y',strtotime((string)$card['valid_from']))) ?></strong></div>
        <div class="bulk-rate-meta"><span>Products</span><strong><?= e(number_format(count($products))) ?></strong></div>
    </form>

    <form method="post" action="<?= e(url('rate-card.bulk.save')) ?>" class="card-panel bulk-rate-card" data-bulk-rate-form>
        <?= Csrf::field() ?><input type="hidden" name="rate_card_id" value="<?= e($card['id']) ?>">
        <div class="bulk-rate-controls">
            <div><label class="form-label" for="baseWholeBirdPrice">Whole-bird base price (INR / kg)</label><input class="form-control" id="baseWholeBirdPrice" name="base_whole_bird_price" type="number" min="0.01" step="0.01" required value="<?= e((float)$card['base_whole_bird_price']>0?$card['base_whole_bird_price']:$suggestedBasePrice) ?>"><div class="form-text">Suggested from recent accepted bird receipts: <?= e(money($suggestedBasePrice)) ?></div></div>
            <div><label class="form-label" for="defaultRateMargin">Default margin (%)</label><input class="form-control" id="defaultRateMargin" name="default_margin_percent" type="number" min="0" max="1000" step="0.001" required value="<?= e($card['default_margin_percent']) ?>"><div class="form-text">Used when a product margin is left blank.</div></div>
            <?php if ($canManage): ?><button class="btn btn-danger align-self-center" type="submit"><i class="bi bi-check2-circle me-2"></i>Save complete rate card</button><?php endif; ?>
        </div>
        <div class="table-responsive">
            <table class="table data-table align-middle bulk-rate-table">
                <thead><tr><th>SKU / Product</th><th>HSN</th><th>Cost factor</th><th>Margin %</th><th>Fixed margin</th><th>Calculated</th><th>Final price</th><th>Minimum qty</th></tr></thead>
                <tbody>
                <?php foreach ($products as $row):
                    $hasItem=$row['unit_price']!==null;
                    $factor=$hasItem?$row['live_bird_cost_factor']:$row['product_factor'];
                    $margin=$hasItem?$row['margin_percent']:((float)$row['product_margin']>0?$row['product_margin']:'');
                    $fixed=$hasItem?$row['fixed_margin']:0;
                    $base=(float)$card['base_whole_bird_price']>0?(float)$card['base_whole_bird_price']:(float)$suggestedBasePrice;
                    $effectiveMargin=$margin===''?(float)$card['default_margin_percent']:(float)$margin;
                    $calculated=round(($base*(float)$factor)*(1+$effectiveMargin/100)+(float)$fixed,2);
                    $final=$hasItem?(float)$row['unit_price']:$calculated;
                ?>
                    <tr data-rate-row>
                        <td><div class="record-primary"><span class="record-icon"><i class="bi bi-box-seam"></i></span><span><strong><?= e($row['sku']) ?></strong><small class="d-block text-secondary"><?= e($row['name'].' - '.$row['unit']) ?></small></span></div></td>
                        <td><?= e($row['hsn_code'] ?: '-') ?></td>
                        <td><input class="form-control form-control-sm" name="products[<?= e($row['id']) ?>][factor]" type="number" min="0" max="100" step="0.0001" value="<?= e($factor) ?>" data-rate-factor <?= $canManage?'':'disabled' ?>></td>
                        <td><input class="form-control form-control-sm" name="products[<?= e($row['id']) ?>][margin]" type="number" min="0" max="1000" step="0.001" value="<?= e($margin) ?>" placeholder="Default" data-rate-margin <?= $canManage?'':'disabled' ?>></td>
                        <td><input class="form-control form-control-sm" name="products[<?= e($row['id']) ?>][fixed_margin]" type="number" min="0" step="0.001" value="<?= e($fixed) ?>" data-rate-fixed <?= $canManage?'':'disabled' ?>></td>
                        <td><strong data-rate-calculated><?= e(money($calculated)) ?></strong></td>
                        <td><input class="form-control form-control-sm" name="products[<?= e($row['id']) ?>][unit_price]" type="number" min="0.01" step="0.01" value="<?= e($final) ?>" data-rate-final data-rate-manual="<?= abs($final-$calculated)>0.009?'1':'0' ?>" <?= $canManage?'':'disabled' ?>></td>
                        <td><input class="form-control form-control-sm" name="products[<?= e($row['id']) ?>][minimum_quantity]" type="number" min="0" step="0.001" value="<?= e($row['minimum_quantity'] ?? 0) ?>" <?= $canManage?'':'disabled' ?>></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$products): ?><tr><td colspan="8"><div class="table-empty"><i class="bi bi-box-seam"></i><strong>No saleable products</strong><span>Add active finished goods or by-products before building this rate card.</span></div></td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </form>
<?php endif; ?>
