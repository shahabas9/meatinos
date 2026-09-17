<?php
use MeatinOS\Core\Csrf;

$errors = $_SESSION['_errors'] ?? [];
$oldInput = $_SESSION['_old'] ?? [];
unset($_SESSION['_errors'], $_SESSION['_old']);
$formatValue = static function (string $column, mixed $value) use ($module, $lookupMaps): string {
    if ($value === null || $value === '') return '—';
    if (isset($lookupMaps[$column][(string) $value])) return (string) $lookupMaps[$column][(string) $value];
    $field = $module['fields'][$column] ?? null;
    if ($field && isset($field['options'][(string) $value])) return (string) $field['options'][(string) $value];
    if ($field && in_array($field['type'] ?? '', ['decimal'], true)) return number_format((float) $value, 2);
    if (str_contains($column, 'amount') || str_contains($column, 'cost') || str_contains($column, 'price') || str_contains($column, 'salary') || str_contains($column, 'balance')) return money($value);
    if (str_ends_with($column, '_at') && strtotime((string) $value)) return date('j M Y, g:i A', strtotime((string) $value));
    if (str_ends_with($column, '_date') && strtotime((string) $value)) return date('j M Y', strtotime((string) $value));
    return (string) $value;
};
$fieldValue = static function (string $name, array $field) use ($edit, $oldInput): string {
    $value = array_key_exists($name, $oldInput) ? $oldInput[$name] : ($edit[$name] ?? ($field['default'] ?? ''));
    if (($field['type'] ?? '') === 'datetime-local' && $value && strtotime((string) $value)) return date('Y-m-d\TH:i', strtotime((string) $value));
    return (string) $value;
};
?>
<div class="module-toolbar card-panel">
    <div>
        <p class="eyebrow"><?= e($module['group'] ?? 'Operations') ?></p>
        <h2><?= e($module['title']) ?></h2>
        <p><?= e($module['description'] ?? (number_format((int) $result['total']) . ' records · secure, auditable operational data')) ?></p>
    </div>
    <div class="module-actions">
        <form method="get" class="module-search <?= $query !== '' ? 'has-query' : '' ?>" role="search" novalidate data-submit-on-clear>
            <input type="hidden" name="route" value="module"><input type="hidden" name="name" value="<?= e($moduleName) ?>">
            <i class="bi bi-search"></i><input class="form-control" id="module_search" type="search" name="q" value="<?= e($query) ?>" placeholder="Search <?= e(strtolower($module['title'])) ?>" aria-label="Search <?= e(strtolower($module['title'])) ?>"><button class="search-clear" type="button" data-clear-search aria-label="Clear search"><i class="bi bi-x-lg"></i></button>
        </form>
        <?php if (in_array($moduleName,['rate_cards','rate_card_items'],true)): ?><a class="btn btn-light border" href="<?= e(url('rate-card.bulk')) ?>"><i class="bi bi-table me-1"></i>Bulk rate entry</a><?php endif; ?>
        <?php if ($canManage && empty($module['workflow_only'])): ?><button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#recordModal"><i class="bi bi-plus-lg me-1"></i>Add <?= e($module['singular']) ?></button><?php endif; ?>
    </div>
</div>

<div class="card-panel table-panel">
    <div class="table-responsive">
        <table class="table align-middle data-table">
            <thead><tr>
                <?php foreach ($module['columns'] as $column): ?><th><?= e($module['fields'][$column]['label'] ?? ucwords(str_replace('_', ' ', $column))) ?></th><?php endforeach; ?>
                <th class="text-end">Actions</th>
            </tr></thead>
            <tbody>
            <?php if (!$result['rows']): ?>
                <tr><td colspan="<?= e(count($module['columns']) + 1) ?>"><div class="table-empty"><i class="bi <?= e($module['icon']) ?>"></i><strong>No records found</strong><span>Try a different search or add the first record.</span></div></td></tr>
            <?php endif; ?>
            <?php $lastRowGroup = null; foreach ($result['rows'] as $row): ?>
                <?php if ($moduleName === 'shareholders' && ($rowGroups[$row['id']] ?? null) !== $lastRowGroup): $lastRowGroup = $rowGroups[$row['id']] ?? null; ?>
                    <tr class="module-group-row"><td colspan="<?= e(count($module['columns']) + 1) ?>"><i class="bi bi-diagram-3-fill me-2"></i><?= e($lastRowGroup) ?></td></tr>
                <?php endif; ?>
                <tr>
                    <?php foreach ($module['columns'] as $index => $column): ?>
                        <?php $raw = $row[$column] ?? null; $isStatus = in_array($column, ['status','approval_status','vet_status','payment_status','grade','priority','severity'], true); ?>
                        <td>
                            <?php if ($index === 0): ?>
                                <div class="record-primary"><span class="record-icon"><i class="bi <?= e($module['icon']) ?>"></i></span><strong><?= e($formatValue($column, $raw)) ?></strong></div>
                            <?php elseif ($isStatus): ?>
                                <span class="badge rounded-pill text-bg-<?= e(status_class((string) $raw)) ?>"><?= e($formatValue($column, $raw)) ?></span>
                            <?php else: ?>
                                <?= e($formatValue($column, $raw)) ?>
                            <?php endif; ?>
                        </td>
                    <?php endforeach; ?>
                    <td class="text-end"><div class="btn-group btn-group-sm">
                        <?php $workflowEntity = ['purchase_orders'=>'purchase_order','goods_receipts'=>'goods_receipt','bird_receipts'=>'bird_receipt','production_batches'=>'production_batch','sales_orders'=>'sales_order','invoices'=>'invoice','payments'=>'payment','supplier_invoices'=>'supplier_invoice','supplier_payments'=>'supplier_payment','dispatches'=>'dispatch','operating_expenses'=>'operating_expense','partner_transactions'=>'partner_transaction','partner_dividends'=>'partner_dividend','salary_advances'=>'salary_advance','asset_depreciation_entries'=>'asset_depreciation'][$moduleName] ?? null; ?>
                        <?php if ($workflowEntity): ?><a class="btn btn-light border text-danger" href="<?= e(url('workflow', ['entity' => $workflowEntity, 'id' => $row['id']])) ?>" title="Open controlled workflow"><i class="bi bi-signpost-split"></i></a><?php endif; ?>
                        <?php if ($moduleName==='partner_transactions' && in_array($row['status'],['posted','reversed'],true)): ?><a class="btn btn-light border" href="<?= e(url('partner.receipt',['id'=>$row['id']])) ?>" title="Print / share receipt"><i class="bi bi-receipt"></i></a><?php endif; ?>
                        <?php if ($moduleName==='shareholders'): ?><a class="btn btn-light border" href="<?= e(url('partner.welcome',['id'=>$row['id']])) ?>" title="Partner welcome letter"><i class="bi bi-envelope-heart"></i></a><?php endif; ?>
                        <?php if ($moduleName==='employee_appointments'): ?><a class="btn btn-light border" href="<?= e(url('hr.letter',['id'=>$row['id']])) ?>" title="Print employment letter"><i class="bi bi-printer"></i></a><?php endif; ?>
                        <?php $documentType=['employees'=>'employee','employee_appointments'=>'appointment','employee_memos'=>'memo','employee_performance_reviews'=>'performance_review','employee_resignations'=>'resignation','salary_advances'=>'salary_advance','employee_uniform_allocations'=>'uniform','suppliers'=>'supplier','purchase_orders'=>'purchase_order','goods_receipts'=>'goods_receipt','customers'=>'customer','sales_orders'=>'sales_order','invoices'=>'invoice','payments'=>'payment','operating_expenses'=>'expense','company_bank_accounts'=>'bank_account','shareholders'=>'shareholder','partner_transactions'=>'partner_transaction','partner_dividends'=>'partner_dividend','government_loans'=>'government_loan','production_batches'=>'production_batch','production_stage_measurements'=>'stage_measurement','quality_checks'=>'quality_check','inventory_lots'=>'inventory_lot','assets'=>'asset','vehicles'=>'vehicle','dispatches'=>'dispatch','vehicle_gate_logs'=>'vehicle_gate_log','eway_bills'=>'eway_bill','legal_cases'=>'legal_case','roc_filings'=>'roc_filing','company_certificates'=>'company_certificate','office_file_register'=>'office_file','calendar_events'=>'calendar_event'][$moduleName] ?? null; ?>
                        <?php if ($documentType): ?><a class="btn btn-light border" href="<?= e(url('documents',['entity_type'=>$documentType,'entity_id'=>$row['id']])) ?>" title="Documents / evidence photos"><i class="bi bi-paperclip"></i></a><?php endif; ?>
                        <?php if (isset(['customers'=>'customer','suppliers'=>'supplier','products'=>'product'][$moduleName])): ?><a class="btn btn-light border text-danger" href="<?= e(url('entity360',['type'=>['customers'=>'customer','suppliers'=>'supplier','products'=>'product'][$moduleName],'id'=>$row['id']])) ?>" title="Open 360-degree profile"><i class="bi bi-view-stacked"></i></a><?php endif; ?>
                        <?php if ($canManage && empty($module['workflow_only'])): ?><a class="btn btn-light border" href="<?= e(url('module', ['name' => $moduleName, 'edit' => $row['id'], 'q' => $query])) ?>" title="Edit"><i class="bi bi-pencil"></i></a><?php endif; ?>
                        <?php $deleteTimestamp=$row['created_at'] ?? $row['updated_at'] ?? null; $rowDeleteEligible=$canDelete && $deleteTimestamp && strtotime((string)$deleteTimestamp)>=time()-86400 && (!is_array($deleteStatuses) || in_array((string)($row['status'] ?? ''),$deleteStatuses,true)); ?>
                        <?php if ($rowDeleteEligible && empty($module['workflow_only'])): ?><form action="<?= e(url('module.delete')) ?>" method="post" class="d-inline" data-confirm-delete novalidate><?= Csrf::field() ?><input type="hidden" name="_module" value="<?= e($moduleName) ?>"><input type="hidden" name="id" value="<?= e($row['id']) ?>"><button class="btn btn-light border text-danger" type="submit" title="Delete entry added in the last 24 hours"><i class="bi bi-trash"></i></button></form><?php endif; ?>
                        <?php if (in_array($moduleName, ['production_batches','bird_receipts','inventory_lots','sales_orders','invoices','dispatches'], true)): ?>
                            <?php $traceField = match ($moduleName) { 'inventory_lots' => 'lot_number', 'sales_orders' => 'order_number', 'invoices' => 'invoice_number', 'dispatches' => 'dispatch_number', default => 'batch_number' }; ?>
                            <a class="btn btn-light border" href="<?= e(url('traceability', ['q' => $row[$traceField] ?? ''])) ?>" title="Trace"><i class="bi bi-upc-scan"></i></a>
                        <?php endif; ?>
                    </div></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($result['pages'] > 1): ?>
        <nav class="pagination-wrap" aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">
            <?php for ($pageNumber = 1; $pageNumber <= $result['pages']; $pageNumber++): ?><li class="page-item <?= $pageNumber === $result['page'] ? 'active' : '' ?>"><a class="page-link" href="<?= e(url('module', ['name' => $moduleName, 'q' => $query, 'page' => $pageNumber])) ?>"><?= $pageNumber ?></a></li><?php endfor; ?>
        </ul></nav>
    <?php endif; ?>
</div>

<?php if ($canManage && empty($module['workflow_only'])): ?>
<div class="modal fade" id="recordModal" tabindex="-1" aria-labelledby="recordModalLabel" aria-hidden="true" data-auto-open="<?= ($edit || $errors) ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content" action="<?= e(url('module.save')) ?>" method="post" novalidate data-prevent-double-submit>
            <?= Csrf::field() ?><input type="hidden" name="_module" value="<?= e($moduleName) ?>"><input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <div class="modal-header"><div><p class="eyebrow mb-1"><?= $edit ? 'Update record' : 'New record' ?></p><h2 class="modal-title fs-4" id="recordModalLabel"><?= e($module['singular']) ?></h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <?php if ($errors): ?>
                    <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert">
                        <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
                        <div class="flex-grow-1">
                            <strong class="d-block mb-1">Please correct the following <?= count($errors) > 1 ? 'fields' : 'field' ?>:</strong>
                            <ul class="mb-0 ps-3">
                                <?php foreach ($errors as $fKey => $fErr): ?>
                                    <li>
                                        <?php 
                                            $fLabel = $module['fields'][$fKey]['label'] ?? '';
                                            if ($fLabel && stripos($fErr, $fLabel) === false && stripos($fErr, str_replace(['_', 'id'], [' ', ''], (string) $fKey)) === false) {
                                                echo '<strong>' . e($fLabel) . ':</strong> ';
                                            }
                                            echo e($fErr);
                                        ?>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="row g-3" data-module-form="<?= e($moduleName) ?>">
                    <?php $lastSection=null; foreach ($module['fields'] as $name => $field): $type = $field['type'] ?? 'text'; $value = $fieldValue($name, $field); $section=$field['section'] ?? null; ?>
                        <?php if ($section && $section!==$lastSection): $lastSection=$section; ?><div class="col-12 form-section-heading" data-stage-section="<?= e($section) ?>"><strong><?= e($section) ?></strong></div><?php endif; ?>
                        <div class="<?= $type === 'textarea' ? 'col-12' : 'col-md-6 col-xl-4' ?>" data-field-section="<?= e($section ?? '') ?>" <?= !empty($field['show_for']) ? 'data-stage-values="'.e(implode(',',(array)$field['show_for'])).'"' : '' ?>>
                            <label class="form-label" for="field_<?= e($name) ?>"><?= e($field['label']) ?><?= !empty($field['required']) ? ' *' : '' ?></label>
                            <?php if (!empty($field['readonly'])): ?>
                                <input class="form-control" id="field_<?= e($name) ?>" value="<?= e($value !== '' && $value !== null ? $value : 'Calculated automatically') ?>" disabled aria-readonly="true">
                            <?php elseif ($type === 'textarea'): ?>
                                <textarea class="form-control <?= isset($errors[$name]) ? 'is-invalid' : '' ?>" id="field_<?= e($name) ?>" name="<?= e($name) ?>" rows="3" maxlength="<?= e($field['max'] ?? 1500) ?>" <?= !empty($field['required']) ? 'required' : '' ?>><?= e($value) ?></textarea>
                            <?php elseif ($type === 'select'): ?>
                                <select class="form-select <?= isset($errors[$name]) ? 'is-invalid' : '' ?>" id="field_<?= e($name) ?>" name="<?= e($name) ?>" <?= !empty($field['required']) ? 'required' : '' ?>><option value="">Select...</option><?php foreach ($field['options'] as $optionValue => $optionLabel): ?><option value="<?= e($optionValue) ?>" <?= (string) $value === (string) $optionValue ? 'selected' : '' ?>><?= e($optionLabel) ?></option><?php endforeach; ?></select>
                            <?php elseif ($type === 'lookup'): ?>
                                <select class="form-select <?= isset($errors[$name]) ? 'is-invalid' : '' ?>" id="field_<?= e($name) ?>" name="<?= e($name) ?>" <?= !empty($field['required']) ? 'required' : '' ?>><option value="">Select...</option><?php foreach ($lookups[$name] ?? [] as $option): ?><option value="<?= e($option['option_value']) ?>" <?= (string) $value === (string) $option['option_value'] ? 'selected' : '' ?>><?= e($option['option_label']) ?></option><?php endforeach; ?></select>
                            <?php else: ?>
                                <?php $htmlType = in_array($type, ['date','datetime-local','email','number','time'], true) ? $type : ($type === 'decimal' ? 'number' : 'text'); ?>
                                <input class="form-control <?= isset($errors[$name]) ? 'is-invalid' : '' ?>" id="field_<?= e($name) ?>" name="<?= e($name) ?>" type="<?= e($htmlType) ?>" value="<?= e($value) ?>" <?= in_array($type, ['number','decimal'], true) ? 'step="' . ($type === 'decimal' ? '0.001' : '1') . '"' : '' ?> <?= !empty($field['required']) ? 'required' : '' ?> <?= isset($field['max']) ? 'maxlength="' . e($field['max']) . '"' : '' ?>>
                            <?php endif; ?>
                            <?php if (!empty($field['help'])): ?><small class="form-text text-muted"><?= e($field['help']) ?></small><?php endif; ?>
                            <?php if (isset($errors[$name])): ?><div class="invalid-feedback"><?= e($errors[$name]) ?></div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="submit" class="btn btn-danger"><i class="bi bi-check2 me-1"></i>Save <?= e($module['singular']) ?></button></div>
        </form>
    </div>
</div>
<?php endif; ?>
