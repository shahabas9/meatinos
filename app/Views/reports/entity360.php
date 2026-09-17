<?php
$moneyField = static fn(string $field): bool => str_contains($field,'amount') || str_contains($field,'price') || str_contains($field,'cost') || str_contains($field,'balance');
$format = static function(string $field,mixed $value) use($moneyField): string {
    if ($value === null || $value === '') return '—';
    if ($moneyField($field)) return money($value);
    if ((str_ends_with($field,'_date') || $field === 'date') && strtotime((string)$value)) return date('j M Y',strtotime((string)$value));
    if (str_ends_with($field,'_at') && strtotime((string)$value)) return date('j M Y, g:i A',strtotime((string)$value));
    if (str_contains($field,'percent')) return number_format((float)$value,2).'%';
    return human_status((string)$value);
};
$heading = $profile['name'] ?? ($profile['sku'] ?? 'Profile');
$code = $profile['code'] ?? ($profile['sku'] ?? '');
?>
<div class="workflow-hero card-panel"><div><a class="workflow-back" href="<?= e(url('search')) ?>"><i class="bi bi-arrow-left"></i> Global search</a><p class="eyebrow mt-3"><?= e(ucfirst($type)) ?> intelligence</p><h2><?= e($heading) ?></h2><p><?= e($code) ?> · complete operational and financial relationship history</p></div><span class="badge rounded-pill text-bg-<?= e(status_class((string)($profile['status'] ?? 'active'))) ?> px-3 py-2"><?= e(human_status((string)($profile['status'] ?? 'active'))) ?></span></div>
<section class="card-panel entity-profile"><div class="panel-heading"><div><h3>Master profile</h3><p>Current authorized master-data values</p></div><i class="bi bi-person-vcard"></i></div><div class="workflow-facts"><?php foreach($profile as $field=>$value): if(in_array($field,['id','created_at','updated_at'],true)) continue; ?><div><span><?= e(human_status($field)) ?></span><strong><?= e($format($field,$value)) ?></strong></div><?php endforeach; ?></div></section>
<?php foreach($sections as $sectionTitle=>$rows): ?><section class="card-panel table-panel workflow-table"><div class="workflow-section-title"><div><p class="eyebrow">360° relationship</p><h3><?= e($sectionTitle) ?></h3></div><span><?= e(count($rows)) ?> records</span></div><?php if(!$rows): ?><div class="table-empty"><i class="bi bi-inbox"></i><strong>No linked records</strong><span>This profile has no authorized <?= e(strtolower($sectionTitle)) ?> yet.</span></div><?php else: $columns=array_keys($rows[0]); ?><div class="table-responsive"><table class="table data-table align-middle"><thead><tr><?php foreach($columns as $column): ?><th><?= e(human_status($column)) ?></th><?php endforeach; ?></tr></thead><tbody><?php foreach($rows as $row): ?><tr><?php foreach($columns as $column): ?><td><?php if(in_array($column,['status','payment_status','quality_status','vet_status'],true)): ?><span class="badge rounded-pill text-bg-<?= e(status_class((string)$row[$column])) ?>"><?= e($format($column,$row[$column])) ?></span><?php else: ?><?= e($format($column,$row[$column])) ?><?php endif; ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></section><?php endforeach; ?>
