<?php
use MeatinOS\Core\Csrf;

$errors = $_SESSION['_errors'] ?? [];
$oldInput = $_SESSION['_old'] ?? [];
unset($_SESSION['_errors'], $_SESSION['_old']);

$formatDate = static fn(?string $date): string => $date && strtotime($date) ? date('d M Y', strtotime($date)) : '—';
$formatDateTime = static fn(?string $dt): string => $dt && strtotime($dt) ? date('d M Y, g:i A', strtotime($dt)) : '—';

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

$empFieldValue = static function (string $name, array $field) use ($edit, $oldInput): string {
    $value = array_key_exists($name, $oldInput) ? $oldInput[$name] : ($edit[$name] ?? ($field['default'] ?? ''));
    if (($field['type'] ?? '') === 'datetime-local' && $value && strtotime((string) $value)) return date('Y-m-d\TH:i', strtotime((string) $value));
    return (string) $value;
};

$benefitFieldValue = static function (string $name, mixed $default = '') use ($editBenefit, $oldInput): string {
    return (string) (array_key_exists($name, $oldInput) ? $oldInput[$name] : ($editBenefit[$name] ?? $default));
};

$benefitTypeBadge = static function (string $type): array {
    return match ($type) {
        'esi' => ['ESI', 'primary', 'bi-shield-check'],
        'pf' => ['Provident Fund', 'info', 'bi-safe2'],
        'insurance' => ['Insurance', 'success', 'bi-heart-pulse'],
        'gratuity' => ['Gratuity', 'secondary', 'bi-award'],
        'bonus' => ['Bonus', 'warning', 'bi-gift'],
        'allowance' => ['Allowance', 'dark', 'bi-wallet2'],
        default => [ucfirst($type), 'light border text-dark', 'bi-tag'],
    };
};

$openBenefitModal = !empty($editBenefit) || (!empty($errors) && isset($oldInput['benefit_type']));
$openEmployeeModal = !empty($edit) || (!empty($errors) && !isset($oldInput['benefit_type']));
?>

<div class="module-toolbar card-panel">
    <div>
        <p class="eyebrow"><i class="bi bi-people-fill me-1"></i>HR & Payroll</p>
        <h2>Employee Management & Statutory Benefits</h2>
        <p>Unified employee administration: workforce directory, employment status, statutory compliance (ESI, PF / UAN), group insurance, and recurring allowances.</p>
    </div>
    <div class="module-actions">
        <div class="btn-group" role="group" aria-label="View switch">
            <a class="btn <?= $currentTab === 'directory' ? 'btn-danger' : 'btn-light border' ?>" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'directory', 'q' => $currentTab === 'directory' ? $query : null])) ?>">
                <i class="bi bi-person-badge me-1"></i>Employee Directory (<?= $summary['total_employees'] ?>)
            </a>
            <a class="btn <?= $currentTab === 'benefits' ? 'btn-danger' : 'btn-light border' ?>" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'benefits', 'employee_id' => $selectedEmployeeId ?: null, 'q' => $currentTab === 'benefits' ? $query : null])) ?>">
                <i class="bi bi-shield-plus me-1"></i>ESI, PF & Benefits (<?= $summary['active_benefits'] ?>)
            </a>
        </div>
        <?php if ($canManage): ?>
            <?php if ($currentTab === 'benefits'): ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#benefitModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Benefit Record
                </button>
            <?php else: ?>
                <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#recordModal">
                    <i class="bi bi-person-plus-fill me-1"></i>Add Employee
                </button>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- KPI Summary Ribbon -->
<div class="card-panel py-3 px-4 mb-4">
    <div class="d-flex flex-wrap gap-3 justify-content-between align-items-center">
        <?php if ($currentTab === 'directory'): ?>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="badge-pill-stat">
                    <span class="text-muted"><i class="bi bi-people me-1"></i>Total Staff:</span>
                    <strong><?= $summary['total_employees'] ?></strong>
                </div>
                <div class="badge-pill-stat">
                    <span class="text-muted"><i class="bi bi-check-circle-fill text-success me-1"></i>Active Staff:</span>
                    <strong class="text-success"><?= $summary['active_employees'] ?></strong>
                </div>
                <div class="badge-pill-stat">
                    <span class="text-muted"><i class="bi bi-shield-check text-primary me-1"></i>ESI Covered:</span>
                    <strong class="text-primary"><?= $summary['esi_enrolled'] ?></strong>
                </div>
                <div class="badge-pill-stat">
                    <span class="text-muted"><i class="bi bi-safe2 text-info me-1"></i>PF / UAN Enrolled:</span>
                    <strong class="text-info"><?= $summary['pf_enrolled'] ?></strong>
                </div>
            </div>
            <div>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'benefits'])) ?>">
                    <i class="bi bi-shield-plus me-1"></i>View Statutory Outgo (<?= money($summary['monthly_total_statutory']) ?>/mo)
                </a>
            </div>
        <?php else: ?>
            <div class="d-flex flex-wrap gap-2 align-items-center">
                <div class="badge-pill-stat">
                    <span class="text-muted"><i class="bi bi-shield-plus me-1"></i>Active Benefits:</span>
                    <strong><?= $summary['active_benefits'] ?></strong>
                </div>
                <div class="badge-pill-stat">
                    <span class="text-muted"><i class="bi bi-building me-1"></i>Employer Monthly:</span>
                    <strong class="text-primary"><?= money($summary['monthly_employer_amount']) ?></strong>
                </div>
                <div class="badge-pill-stat">
                    <span class="text-muted"><i class="bi bi-person me-1"></i>Employee Deductions:</span>
                    <strong class="text-warning"><?= money($summary['monthly_employee_amount']) ?></strong>
                </div>
                <div class="badge-pill-stat">
                    <span class="text-muted"><i class="bi bi-cash-coin me-1"></i>Total Statutory Outgo:</span>
                    <strong class="text-success"><?= money($summary['monthly_total_statutory']) ?></strong>
                </div>
            </div>
            <div>
                <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('payroll')) ?>">
                    <i class="bi bi-calculator me-1"></i>Open Payroll Engine
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if ($currentTab === 'directory'): ?>
    <!-- ==================== TAB 1: EMPLOYEE DIRECTORY ==================== -->
    <div class="card-panel table-panel">
        <div class="d-flex flex-wrap justify-content-between align-items-center p-3 border-bottom gap-2">
            <div>
                <h3 class="h5 mb-0"><i class="bi bi-person-lines-fill me-2 text-danger"></i>Employee Directory</h3>
                <small class="text-muted">Master roster of all active, probation, and exited staff with statutory indicators.</small>
            </div>
            <form method="get" class="d-flex gap-2" action="<?= e(url('module')) ?>">
                <input type="hidden" name="route" value="module">
                <input type="hidden" name="name" value="employees">
                <input type="hidden" name="tab" value="directory">
                <div class="input-group input-group-sm" style="width: 280px;">
                    <input class="form-control" type="search" name="q" value="<?= e($query) ?>" placeholder="Search name, EMP ID, dept...">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                    <?php if ($query !== ''): ?>
                        <a class="btn btn-outline-secondary" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'directory'])) ?>"><i class="bi bi-x-lg"></i></a>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <div class="table-responsive">
            <table class="table align-middle data-table mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Department & Role</th>
                        <th>Shift & Lifecycle</th>
                        <th>Basic Salary</th>
                        <th>Statutory IDs (ESI / PF)</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$result['rows']): ?>
                    <tr>
                        <td colspan="7">
                            <div class="table-empty py-5 text-center">
                                <i class="bi bi-person-badge display-6 text-muted"></i>
                                <h4 class="mt-2">No employees found</h4>
                                <p class="text-muted">Try a different search query or add a new employee profile.</p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($result['rows'] as $row): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="record-icon"><i class="bi bi-person-badge"></i></span>
                                <div>
                                    <strong class="d-block text-dark"><?= e($row['full_name']) ?></strong>
                                    <span class="badge text-bg-light border font-monospace"><?= e($row['employee_number']) ?></span>
                                    <?php if (!empty($row['phone'])): ?>
                                        <small class="text-muted ms-1"><i class="bi bi-telephone me-1"></i><?= e($row['phone']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <div><strong><?= e($row['department'] ?? '—') ?></strong></div>
                            <small class="text-muted"><?= e($row['job_title'] ?? '—') ?></small>
                        </td>
                        <td>
                            <div><span class="badge rounded-pill text-bg-light border"><?= e(ucfirst($row['employment_status'] ?? 'confirmed')) ?></span></div>
                            <small class="text-muted"><?= !empty($row['shift_code']) ? 'Shift ' . e($row['shift_code']) : 'Office' ?> · Joined <?= $formatDate($row['join_date'] ?? null) ?></small>
                        </td>
                        <td>
                            <strong class="text-dark"><?= money($row['basic_salary'] ?? 0) ?></strong>
                            <?php if (!empty($row['overtime_rate'])): ?>
                                <small class="d-block text-muted">OT: <?= money($row['overtime_rate']) ?>/hr</small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="d-flex flex-column gap-1">
                                <?php if (!empty($row['esi_number'])): ?>
                                    <span class="badge text-bg-primary text-start font-monospace" style="font-size: 0.72rem;">
                                        <i class="bi bi-shield-check me-1"></i>ESI: <?= e($row['esi_number']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge text-bg-light text-muted border text-start" style="font-size: 0.70rem;">No ESI</span>
                                <?php endif; ?>

                                <?php if (!empty($row['uan_number'])): ?>
                                    <span class="badge text-bg-info text-dark text-start font-monospace" style="font-size: 0.72rem;">
                                        <i class="bi bi-safe2 me-1"></i>PF: <?= e($row['uan_number']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="badge text-bg-light text-muted border text-start" style="font-size: 0.70rem;">No PF/UAN</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td>
                            <span class="badge rounded-pill text-bg-<?= e(status_class((string) ($row['status'] ?? 'active'))) ?>">
                                <?= e(ucfirst((string) ($row['status'] ?? 'active'))) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <a class="btn btn-light border text-primary" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'benefits', 'employee_id' => $row['id']])) ?>" title="Manage ESI, PF & Benefits">
                                    <i class="bi bi-shield-plus me-1"></i>Benefits
                                </a>
                                <a class="btn btn-light border" href="<?= e(url('documents', ['entity_type' => 'employee', 'entity_id' => $row['id']])) ?>" title="Documents & IDs">
                                    <i class="bi bi-paperclip"></i>
                                </a>
                                <?php if ($canManage): ?>
                                    <a class="btn btn-light border" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'directory', 'edit' => $row['id'], 'q' => $query])) ?>" title="Edit Employee">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                <?php endif; ?>
                                <?php 
                                    $deleteTimestamp = $row['created_at'] ?? $row['updated_at'] ?? null;
                                    $rowDeleteEligible = $canDelete && $deleteTimestamp && strtotime((string) $deleteTimestamp) >= time() - 86400 && (!is_array($deleteStatuses) || in_array((string) ($row['status'] ?? ''), $deleteStatuses, true));
                                    if ($rowDeleteEligible): 
                                ?>
                                    <form action="<?= e(url('module.delete')) ?>" method="post" class="d-inline" data-confirm-delete novalidate>
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="_module" value="employees">
                                        <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                                        <input type="hidden" name="_return_to" value="<?= e(url('module', ['name' => 'employees', 'tab' => 'directory'])) ?>">
                                        <button class="btn btn-light border text-danger" type="submit" title="Delete employee (within 24 hours)"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($result['pages'] > 1): ?>
            <div class="p-3 border-top d-flex justify-content-end">
                <nav aria-label="Employee pagination">
                    <ul class="pagination pagination-sm mb-0">
                        <?php for ($pageNumber = 1; $pageNumber <= $result['pages']; $pageNumber++): ?>
                            <li class="page-item <?= $pageNumber === $result['page'] ? 'active' : '' ?>">
                                <a class="page-link" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'directory', 'q' => $query, 'page' => $pageNumber])) ?>"><?= $pageNumber ?></a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- ==================== TAB 2: ESI, PF & BENEFITS ==================== -->
    <!-- Employee Quick Filter & Search -->
    <div class="card-panel py-3 px-4 mb-4">
        <div class="row g-3 align-items-center">
            <div class="col-md-5 col-lg-4">
                <label class="form-label small text-muted text-uppercase fw-bold mb-1" for="employeeBenefitSelect">
                    <i class="bi bi-filter-circle me-1"></i>Filter by Employee
                </label>
                <form method="get" action="<?= e(url('module')) ?>" id="employeeFilterForm">
                    <input type="hidden" name="route" value="module">
                    <input type="hidden" name="name" value="employees">
                    <input type="hidden" name="tab" value="benefits">
                    <select class="form-select" id="employeeBenefitSelect" name="employee_id" onchange="if (window.MeatinOSNav) { MeatinOSNav.navigate('?route=module&name=employees&tab=benefits' + (this.value ? '&employee_id=' + encodeURIComponent(this.value) : '')); } else { this.form.submit(); }">
                        <option value="">— All Employees (Full Statutory Register) —</option>
                        <?php foreach ($allEmployees as $emp): ?>
                            <option value="<?= e($emp['id']) ?>" <?= $selectedEmployeeId === (int) $emp['id'] ? 'selected' : '' ?>>
                                <?= e($emp['employee_number']) ?> — <?= e($emp['full_name']) ?> (<?= e($emp['department']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="col-md-7 col-lg-8">
                <div class="d-flex flex-wrap gap-2 justify-content-md-end align-items-center pt-md-3">
                    <form method="get" class="d-flex gap-2" action="<?= e(url('module')) ?>">
                        <input type="hidden" name="route" value="module">
                        <input type="hidden" name="name" value="employees">
                        <input type="hidden" name="tab" value="benefits">
                        <?php if ($selectedEmployeeId > 0): ?>
                            <input type="hidden" name="employee_id" value="<?= e($selectedEmployeeId) ?>">
                        <?php endif; ?>
                        <div class="input-group input-group-sm" style="width: 260px;">
                            <input class="form-control" type="search" name="q" value="<?= e($query) ?>" placeholder="Search benefit or policy no...">
                            <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                        </div>
                    </form>
                    <?php if ($selectedEmployeeId > 0): ?>
                        <a class="btn btn-sm btn-outline-secondary" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'benefits'])) ?>">
                            <i class="bi bi-x-circle me-1"></i>Clear Filter
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Selected Employee Statutory Profile Card -->
    <?php if ($selectedEmployee): ?>
        <div class="card-panel p-4 mb-4 border-start border-4 border-danger">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <h3 class="h5 mb-0 text-dark"><?= e($selectedEmployee['full_name']) ?></h3>
                        <span class="badge text-bg-light border font-monospace"><?= e($selectedEmployee['employee_number']) ?></span>
                        <span class="badge rounded-pill text-bg-<?= e(status_class((string) ($selectedEmployee['status'] ?? 'active'))) ?>"><?= e(ucfirst($selectedEmployee['status'] ?? 'active')) ?></span>
                    </div>
                    <p class="text-muted small mb-0">
                        <strong><?= e($selectedEmployee['department']) ?></strong> · <?= e($selectedEmployee['job_title']) ?> · Basic Salary: <strong><?= money($selectedEmployee['basic_salary']) ?></strong>
                    </p>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($canManage): ?>
                        <a class="btn btn-sm btn-light border" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'directory', 'edit' => $selectedEmployee['id']])) ?>">
                            <i class="bi bi-pencil-square me-1"></i>Edit Statutory Numbers
                        </a>
                        <button class="btn btn-sm btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#benefitModal">
                            <i class="bi bi-plus-lg me-1"></i>Add Benefit for <?= e($selectedEmployee['full_name']) ?>
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <hr class="my-3 opacity-25">

            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-light border">
                        <small class="text-muted d-block fw-bold"><i class="bi bi-shield-check text-primary me-1"></i>ESI Number</small>
                        <strong class="font-monospace text-dark"><?= e($selectedEmployee['esi_number'] ?: 'Not Assigned') ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-light border">
                        <small class="text-muted d-block fw-bold"><i class="bi bi-safe2 text-info me-1"></i>PF / UAN Number</small>
                        <strong class="font-monospace text-dark"><?= e($selectedEmployee['uan_number'] ?: 'Not Assigned') ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-light border">
                        <small class="text-muted d-block fw-bold"><i class="bi bi-heart-pulse text-success me-1"></i>Insurance Policy</small>
                        <strong class="font-monospace text-dark"><?= e($selectedEmployee['insurance_number'] ?: 'Not Assigned') ?></strong>
                    </div>
                </div>
                <div class="col-6 col-md-3">
                    <div class="p-2 rounded bg-light border">
                        <small class="text-muted d-block fw-bold"><i class="bi bi-credit-card-2-front text-secondary me-1"></i>PAN / Aadhaar</small>
                        <span class="font-monospace text-dark small">
                            <?= e($selectedEmployee['pan_number'] ?: '—') ?> / <?= e($selectedEmployee['aadhaar_number'] ?: '—') ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Benefits Register Table -->
    <div class="card-panel table-panel">
        <div class="d-flex justify-content-between align-items-center p-3 border-bottom">
            <div>
                <h3 class="h5 mb-0"><i class="bi bi-shield-shaded me-2 text-danger"></i>ESI, PF & Benefits Register</h3>
                <small class="text-muted">
                    <?= $selectedEmployee ? 'Active and historical statutory benefits for ' . e($selectedEmployee['full_name']) : 'Statutory benefits and deduction policies across all company personnel' ?>
                </small>
            </div>
            <?php if ($canManage): ?>
                <button class="btn btn-sm btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#benefitModal">
                    <i class="bi bi-plus-lg me-1"></i>Add Benefit Record
                </button>
            <?php endif; ?>
        </div>

        <div class="table-responsive">
            <table class="table align-middle data-table mb-0">
                <thead>
                    <tr>
                        <th>Employee</th>
                        <th>Benefit Type</th>
                        <th>Policy / Ref Number</th>
                        <th>Employer Contribution</th>
                        <th>Employee Deduction</th>
                        <th>Total Monthly</th>
                        <th>Effective Period</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (!$benefits): ?>
                    <tr>
                        <td colspan="9">
                            <div class="table-empty py-5 text-center">
                                <i class="bi bi-shield-plus display-6 text-muted"></i>
                                <h4 class="mt-2">No benefit records found</h4>
                                <p class="text-muted">
                                    <?= $selectedEmployee ? 'No benefits recorded for ' . e($selectedEmployee['full_name']) . '. Click "Add Benefit Record" above to add ESI, PF, or Insurance.' : 'No statutory benefit records match your criteria.' ?>
                                </p>
                            </div>
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($benefits as $benefit): ?>
                    <?php [$typeName, $typeClass, $typeIcon] = $benefitTypeBadge((string) ($benefit['benefit_type'] ?? '')); ?>
                    <tr>
                        <td>
                            <div>
                                <a class="fw-bold text-dark text-decoration-none" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'benefits', 'employee_id' => $benefit['employee_id']])) ?>">
                                    <?= e($benefit['employee_name'] ?? ('Employee #' . $benefit['employee_id'])) ?>
                                </a>
                                <div class="small text-muted font-monospace"><?= e($benefit['employee_number'] ?? '') ?> · <?= e($benefit['department'] ?? '') ?></div>
                            </div>
                        </td>
                        <td>
                            <span class="badge text-bg-<?= e($typeClass) ?>">
                                <i class="bi <?= e($typeIcon) ?> me-1"></i><?= e($typeName) ?>
                            </span>
                        </td>
                        <td>
                            <strong class="font-monospace text-dark"><?= e($benefit['reference_number'] ?: '—') ?></strong>
                            <?php if (!empty($benefit['notes'])): ?>
                                <small class="d-block text-muted text-truncate" style="max-width: 220px;" title="<?= e($benefit['notes']) ?>">
                                    <?= e($benefit['notes']) ?>
                                </small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <strong class="text-primary"><?= money($benefit['employer_amount'] ?? 0) ?></strong>
                            <small class="d-block text-muted">Company share</small>
                        </td>
                        <td>
                            <strong class="text-warning"><?= money($benefit['employee_amount'] ?? 0) ?></strong>
                            <small class="d-block text-muted">Payroll deduction</small>
                        </td>
                        <td>
                            <strong class="text-success"><?= money(((float) $benefit['employer_amount']) + ((float) $benefit['employee_amount'])) ?></strong>
                            <small class="d-block text-muted">Total outgo</small>
                        </td>
                        <td>
                            <div><?= $formatDate($benefit['effective_from'] ?? null) ?></div>
                            <small class="text-muted"><?= !empty($benefit['effective_to']) ? 'to ' . $formatDate($benefit['effective_to']) : 'Ongoing / Current' ?></small>
                        </td>
                        <td>
                            <span class="badge rounded-pill text-bg-<?= e(status_class((string) ($benefit['status'] ?? 'active'))) ?>">
                                <?= e(ucfirst((string) ($benefit['status'] ?? 'active'))) ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <div class="btn-group btn-group-sm">
                                <?php if ($canManage): ?>
                                    <a class="btn btn-light border" href="<?= e(url('module', ['name' => 'employees', 'tab' => 'benefits', 'employee_id' => $selectedEmployeeId ?: null, 'edit_benefit' => $benefit['id']])) ?>" title="Edit Benefit">
                                        <i class="bi bi-pencil"></i>
                                    </a>
                                    <form action="<?= e(url('module.delete')) ?>" method="post" class="d-inline" data-confirm-delete novalidate>
                                        <?= Csrf::field() ?>
                                        <input type="hidden" name="_module" value="employee_benefits">
                                        <input type="hidden" name="id" value="<?= e($benefit['id']) ?>">
                                        <input type="hidden" name="_return_to" value="<?= e(url('module', array_filter(['name' => 'employees', 'tab' => 'benefits', 'employee_id' => $selectedEmployeeId ?: null]))) ?>">
                                        <button class="btn btn-light border text-danger" type="submit" title="Delete Benefit Record"><i class="bi bi-trash"></i></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<!-- ==================== MODAL 1: ADD / EDIT EMPLOYEE ==================== -->
<?php if ($canManage): ?>
<div class="modal fade" id="recordModal" tabindex="-1" aria-labelledby="recordModalLabel" aria-hidden="true" data-auto-open="<?= $openEmployeeModal ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content" action="<?= e(url('module.save')) ?>" method="post" novalidate data-prevent-double-submit>
            <?= Csrf::field() ?>
            <input type="hidden" name="_module" value="employees">
            <input type="hidden" name="id" value="<?= e($edit['id'] ?? 0) ?>">
            <input type="hidden" name="_return_to" value="<?= e(url('module', array_filter(['name' => 'employees', 'tab' => $currentTab, 'employee_id' => $selectedEmployeeId ?: null]))) ?>">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1"><?= $edit ? 'Update Employee Master' : 'New Employee Profile' ?></p>
                        <h2 class="modal-title fs-4" id="recordModalLabel"><?= $edit ? e($edit['full_name']) . ' (' . e($edit['employee_number']) . ')' : 'Add New Employee' ?></h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($errors && !$openBenefitModal): ?>
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

                    <h5 class="fw-bold border-bottom pb-2 mb-3 text-danger"><i class="bi bi-person-lines-fill me-2"></i>1. Personal & Contact Information</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="field_employee_number">Employee Number</label>
                            <input class="form-control <?= isset($errors['employee_number']) ? 'is-invalid' : '' ?>" id="field_employee_number" value="<?= e($edit['employee_number'] ?? 'EMP (Auto Generated)') ?>" disabled>
                            <?php if (isset($errors['employee_number'])): ?><div class="invalid-feedback"><?= e($errors['employee_number']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-5">
                            <label class="form-label" for="field_full_name">Full Name *</label>
                            <input class="form-control <?= isset($errors['full_name']) ? 'is-invalid' : '' ?>" id="field_full_name" name="full_name" value="<?= e($empFieldValue('full_name', ['default' => ''])) ?>" required maxlength="150">
                            <?php if (isset($errors['full_name'])): ?><div class="invalid-feedback"><?= e($errors['full_name']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="field_phone">Phone Number</label>
                            <input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" id="field_phone" name="phone" value="<?= e($empFieldValue('phone', ['default' => ''])) ?>" maxlength="30">
                            <?php if (isset($errors['phone'])): ?><div class="invalid-feedback"><?= e($errors['phone']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="field_email">Email Address</label>
                            <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" id="field_email" name="email" type="email" value="<?= e($empFieldValue('email', ['default' => ''])) ?>" maxlength="190">
                            <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= e($errors['email']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="field_date_of_birth">Date of Birth</label>
                            <input class="form-control <?= isset($errors['date_of_birth']) ? 'is-invalid' : '' ?>" id="field_date_of_birth" name="date_of_birth" type="date" value="<?= e($empFieldValue('date_of_birth', ['default' => ''])) ?>">
                            <?php if (isset($errors['date_of_birth'])): ?><div class="invalid-feedback"><?= e($errors['date_of_birth']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="field_emergency_contact">Emergency Contact</label>
                            <input class="form-control <?= isset($errors['emergency_contact']) ? 'is-invalid' : '' ?>" id="field_emergency_contact" name="emergency_contact" value="<?= e($empFieldValue('emergency_contact', ['default' => ''])) ?>" maxlength="60">
                            <?php if (isset($errors['emergency_contact'])): ?><div class="invalid-feedback"><?= e($errors['emergency_contact']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="field_address">Residential Address</label>
                            <textarea class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" id="field_address" name="address" rows="2" maxlength="1500"><?= e($empFieldValue('address', ['default' => ''])) ?></textarea>
                            <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= e($errors['address']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="field_alternate_address">Alternate / Labour Address</label>
                            <textarea class="form-control <?= isset($errors['alternate_address']) ? 'is-invalid' : '' ?>" id="field_alternate_address" name="alternate_address" rows="2" maxlength="1500"><?= e($empFieldValue('alternate_address', ['default' => ''])) ?></textarea>
                            <?php if (isset($errors['alternate_address'])): ?><div class="invalid-feedback"><?= e($errors['alternate_address']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <h5 class="fw-bold border-bottom pb-2 mb-3 text-danger"><i class="bi bi-briefcase me-2"></i>2. Employment & Salary</h5>
                    <div class="row g-3 mb-4">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="field_department">Department *</label>
                            <input class="form-control <?= isset($errors['department']) ? 'is-invalid' : '' ?>" id="field_department" name="department" value="<?= e($empFieldValue('department', ['default' => ''])) ?>" required maxlength="100">
                            <?php if (isset($errors['department'])): ?><div class="invalid-feedback"><?= e($errors['department']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="field_job_title">Job Title *</label>
                            <input class="form-control <?= isset($errors['job_title']) ? 'is-invalid' : '' ?>" id="field_job_title" name="job_title" value="<?= e($empFieldValue('job_title', ['default' => ''])) ?>" required maxlength="120">
                            <?php if (isset($errors['job_title'])): ?><div class="invalid-feedback"><?= e($errors['job_title']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="field_manager_id">Reports To</label>
                            <select class="form-select <?= isset($errors['manager_id']) ? 'is-invalid' : '' ?>" id="field_manager_id" name="manager_id">
                                <option value="">None / Top Level</option>
                                <?php foreach ($lookups['manager_id'] ?? [] as $option): ?>
                                    <option value="<?= e($option['option_value']) ?>" <?= (string) $empFieldValue('manager_id', ['default' => '']) === (string) $option['option_value'] ? 'selected' : '' ?>>
                                        <?= e($option['option_label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['manager_id'])): ?><div class="invalid-feedback"><?= e($errors['manager_id']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="field_join_date">Join Date *</label>
                            <input class="form-control <?= isset($errors['join_date']) ? 'is-invalid' : '' ?>" id="field_join_date" name="join_date" type="date" value="<?= e($empFieldValue('join_date', ['default' => date('Y-m-d')])) ?>" required>
                            <?php if (isset($errors['join_date'])): ?><div class="invalid-feedback"><?= e($errors['join_date']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="field_resignation_date">Exit Date</label>
                            <input class="form-control <?= isset($errors['resignation_date']) ? 'is-invalid' : '' ?>" id="field_resignation_date" name="resignation_date" type="date" value="<?= e($empFieldValue('resignation_date', ['default' => ''])) ?>">
                            <?php if (isset($errors['resignation_date'])): ?><div class="invalid-feedback"><?= e($errors['resignation_date']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="field_employment_status">Employment Lifecycle *</label>
                            <select class="form-select <?= isset($errors['employment_status']) ? 'is-invalid' : '' ?>" id="field_employment_status" name="employment_status" required>
                                <?php foreach (['probation' => 'Probation', 'confirmed' => 'Confirmed', 'notice' => 'Notice period', 'resigned' => 'Resigned', 'terminated' => 'Terminated'] as $key => $lbl): ?>
                                    <option value="<?= e($key) ?>" <?= (string) $empFieldValue('employment_status', ['default' => 'confirmed']) === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['employment_status'])): ?><div class="invalid-feedback"><?= e($errors['employment_status']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-3">
                            <label class="form-label" for="field_shift_code">Shift</label>
                            <select class="form-select <?= isset($errors['shift_code']) ? 'is-invalid' : '' ?>" id="field_shift_code" name="shift_code">
                                <option value="">Default</option>
                                <?php foreach (['A' => 'Shift A', 'B' => 'Shift B', 'C' => 'Shift C', 'office' => 'Office'] as $key => $lbl): ?>
                                    <option value="<?= e($key) ?>" <?= (string) $empFieldValue('shift_code', ['default' => 'office']) === $key ? 'selected' : '' ?>><?= e($lbl) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['shift_code'])): ?><div class="invalid-feedback"><?= e($errors['shift_code']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="field_basic_salary">Basic Salary (INR) *</label>
                            <input class="form-control <?= isset($errors['basic_salary']) ? 'is-invalid' : '' ?>" id="field_basic_salary" name="basic_salary" type="number" step="0.01" value="<?= e($empFieldValue('basic_salary', ['default' => '0.00'])) ?>" required>
                            <?php if (isset($errors['basic_salary'])): ?><div class="invalid-feedback"><?= e($errors['basic_salary']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="field_overtime_rate">Overtime Rate / Hour (INR)</label>
                            <input class="form-control <?= isset($errors['overtime_rate']) ? 'is-invalid' : '' ?>" id="field_overtime_rate" name="overtime_rate" type="number" step="0.01" value="<?= e($empFieldValue('overtime_rate', ['default' => '0.00'])) ?>">
                            <?php if (isset($errors['overtime_rate'])): ?><div class="invalid-feedback"><?= e($errors['overtime_rate']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label" for="field_status">System Status *</label>
                            <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" id="field_status" name="status" required>
                                <option value="active" <?= (string) $empFieldValue('status', ['default' => 'active']) === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= (string) $empFieldValue('status', ['default' => 'active']) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                            <?php if (isset($errors['status'])): ?><div class="invalid-feedback"><?= e($errors['status']) ?></div><?php endif; ?>
                        </div>
                    </div>

                    <h5 class="fw-bold border-bottom pb-2 mb-3 text-primary"><i class="bi bi-shield-check me-2"></i>3. Statutory Numbers & Government IDs (ESI, PF, PAN, Aadhaar)</h5>
                    <div class="row g-3">
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label fw-bold text-primary" for="field_esi_number">
                                <i class="bi bi-shield-plus me-1"></i>ESI Number
                            </label>
                            <input class="form-control font-monospace <?= isset($errors['esi_number']) ? 'is-invalid' : '' ?>" id="field_esi_number" name="esi_number" value="<?= e($empFieldValue('esi_number', ['default' => ''])) ?>" placeholder="e.g. 31001234560001001" maxlength="30">
                            <small class="text-muted">Employee State Insurance registration number</small>
                            <?php if (isset($errors['esi_number'])): ?><div class="invalid-feedback"><?= e($errors['esi_number']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label fw-bold text-info" for="field_uan_number">
                                <i class="bi bi-safe2 me-1"></i>PF / UAN Number
                            </label>
                            <input class="form-control font-monospace <?= isset($errors['uan_number']) ? 'is-invalid' : '' ?>" id="field_uan_number" name="uan_number" value="<?= e($empFieldValue('uan_number', ['default' => ''])) ?>" placeholder="e.g. 100912345678" maxlength="30">
                            <small class="text-muted">Universal Account Number for Employee Provident Fund</small>
                            <?php if (isset($errors['uan_number'])): ?><div class="invalid-feedback"><?= e($errors['uan_number']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-4">
                            <label class="form-label fw-bold text-success" for="field_insurance_number">
                                <i class="bi bi-heart-pulse me-1"></i>Group Insurance Number
                            </label>
                            <input class="form-control font-monospace <?= isset($errors['insurance_number']) ? 'is-invalid' : '' ?>" id="field_insurance_number" name="insurance_number" value="<?= e($empFieldValue('insurance_number', ['default' => ''])) ?>" placeholder="e.g. POL-2026-MED-981" maxlength="60">
                            <small class="text-muted">Medical / Life insurance policy certificate number</small>
                            <?php if (isset($errors['insurance_number'])): ?><div class="invalid-feedback"><?= e($errors['insurance_number']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-6">
                            <label class="form-label" for="field_pan_number">Income Tax PAN Number</label>
                            <input class="form-control font-monospace text-uppercase <?= isset($errors['pan_number']) ? 'is-invalid' : '' ?>" id="field_pan_number" name="pan_number" value="<?= e($empFieldValue('pan_number', ['default' => ''])) ?>" placeholder="e.g. ABCDE1234F" maxlength="20">
                            <?php if (isset($errors['pan_number'])): ?><div class="invalid-feedback"><?= e($errors['pan_number']) ?></div><?php endif; ?>
                        </div>
                        <div class="col-md-6 col-lg-6">
                            <label class="form-label" for="field_aadhaar_number">Aadhaar Number</label>
                            <input class="form-control font-monospace <?= isset($errors['aadhaar_number']) ? 'is-invalid' : '' ?>" id="field_aadhaar_number" name="aadhaar_number" value="<?= e($empFieldValue('aadhaar_number', ['default' => ''])) ?>" placeholder="e.g. 1234 5678 9012" maxlength="20">
                            <?php if (isset($errors['aadhaar_number'])): ?><div class="invalid-feedback"><?= e($errors['aadhaar_number']) ?></div><?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-check2 me-1"></i>Save Employee Profile</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ==================== MODAL 2: ADD / EDIT BENEFIT ==================== -->
<div class="modal fade" id="benefitModal" tabindex="-1" aria-labelledby="benefitModalLabel" aria-hidden="true" data-auto-open="<?= $openBenefitModal ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" action="<?= e(url('module.save')) ?>" method="post" novalidate data-prevent-double-submit>
            <?= Csrf::field() ?>
            <input type="hidden" name="_module" value="employee_benefits">
            <input type="hidden" name="id" value="<?= e($editBenefit['id'] ?? 0) ?>">
            <input type="hidden" name="_return_to" value="<?= e(url('module', array_filter(['name' => 'employees', 'tab' => 'benefits', 'employee_id' => $selectedEmployeeId ?: null]))) ?>">
            <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1"><?= $editBenefit ? 'Update Benefit Record' : 'New Statutory Benefit' ?></p>
                        <h2 class="modal-title fs-4" id="benefitModalLabel"><?= $editBenefit ? 'Edit Benefit #' . e($editBenefit['id']) : 'Enroll Employee in ESI, PF or Benefit' ?></h2>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($errors && $openBenefitModal): ?>
                        <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
                            <div class="flex-grow-1">
                                <strong class="d-block mb-1">Please correct the following <?= count($errors) > 1 ? 'fields' : 'field' ?>:</strong>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($errors as $fKey => $fErr): ?>
                                        <li>
                                            <?php 
                                                $benefitFields = ['employee_id' => 'Employee', 'benefit_type' => 'Benefit Type', 'employee_amount' => 'Employee Contribution', 'employer_amount' => 'Employer Contribution', 'status' => 'Status', 'effective_from' => 'Effective From'];
                                                $bLabel = $benefitFields[$fKey] ?? ucwords(str_replace('_', ' ', (string) $fKey));
                                                if ($bLabel && stripos($fErr, $bLabel) === false) {
                                                    echo '<strong>' . e($bLabel) . ':</strong> ';
                                                }
                                                echo e($fErr);
                                            ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-bold" for="benefit_employee_id">Employee *</label>
                            <select class="form-select <?= isset($errors['employee_id']) ? 'is-invalid' : '' ?>" id="benefit_employee_id" name="employee_id" required>
                                <option value="">— Select Employee —</option>
                                <?php foreach ($allEmployees as $emp): ?>
                                    <?php 
                                        $empSelected = (string) $benefitFieldValue('employee_id', $selectedEmployeeId ?: '') === (string) $emp['id'];
                                    ?>
                                    <option value="<?= e($emp['id']) ?>" <?= $empSelected ? 'selected' : '' ?>>
                                        <?= e($emp['employee_number']) ?> — <?= e($emp['full_name']) ?> (<?= e($emp['department']) ?> · Basic: <?= money($emp['basic_salary']) ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="benefit_type">Benefit / Statutory Type *</label>
                            <select class="form-select <?= isset($errors['benefit_type']) ? 'is-invalid' : '' ?>" id="benefit_type" name="benefit_type" required>
                                <option value="">— Select Type —</option>
                                <?php 
                                    $benefitTypes = [
                                        'esi' => 'ESI (Employee State Insurance)',
                                        'pf' => 'Provident Fund (PF / EPF)',
                                        'insurance' => 'Group Medical / Life Insurance',
                                        'gratuity' => 'Gratuity Provision',
                                        'bonus' => 'Statutory Bonus',
                                        'allowance' => 'Recurring Allowance',
                                        'other' => 'Other Statutory Benefit'
                                    ];
                                    foreach ($benefitTypes as $bKey => $bLabel):
                                ?>
                                    <option value="<?= e($bKey) ?>" <?= (string) $benefitFieldValue('benefit_type', 'esi') === $bKey ? 'selected' : '' ?>>
                                        <?= e($bLabel) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="benefit_reference_number">Reference / Policy / IP Number</label>
                            <input class="form-control font-monospace <?= isset($errors['reference_number']) ? 'is-invalid' : '' ?>" id="benefit_reference_number" name="reference_number" value="<?= e($benefitFieldValue('reference_number')) ?>" placeholder="e.g. ESI-REG-87291 or UAN-1002" maxlength="80">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-primary" for="benefit_employer_amount">Employer Monthly Contribution (INR) *</label>
                            <input class="form-control <?= isset($errors['employer_amount']) ? 'is-invalid' : '' ?>" id="benefit_employer_amount" name="employer_amount" type="number" step="0.01" value="<?= e($benefitFieldValue('employer_amount', '0.00')) ?>" required>
                            <small class="text-muted">Paid by company; excluded from employee gross take-home.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold text-warning" for="benefit_employee_amount">Employee Monthly Deduction (INR) *</label>
                            <input class="form-control <?= isset($errors['employee_amount']) ? 'is-invalid' : '' ?>" id="benefit_employee_amount" name="employee_amount" type="number" step="0.01" value="<?= e($benefitFieldValue('employee_amount', '0.00')) ?>" required>
                            <small class="text-muted">Deducted from salary during monthly payroll generation.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="benefit_effective_from">Effective From Date *</label>
                            <input class="form-control <?= isset($errors['effective_from']) ? 'is-invalid' : '' ?>" id="benefit_effective_from" name="effective_from" type="date" value="<?= e($benefitFieldValue('effective_from', date('Y-m-01'))) ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="benefit_effective_to">Effective To Date (Optional)</label>
                            <input class="form-control <?= isset($errors['effective_to']) ? 'is-invalid' : '' ?>" id="benefit_effective_to" name="effective_to" type="date" value="<?= e($benefitFieldValue('effective_to')) ?>">
                            <small class="text-muted">Leave empty if ongoing.</small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold" for="benefit_status">Status *</label>
                            <select class="form-select <?= isset($errors['status']) ? 'is-invalid' : '' ?>" id="benefit_status" name="status" required>
                                <option value="active" <?= (string) $benefitFieldValue('status', 'active') === 'active' ? 'selected' : '' ?>>Active (Apply to Payroll)</option>
                                <option value="inactive" <?= (string) $benefitFieldValue('status', 'active') === 'inactive' ? 'selected' : '' ?>>Inactive (Paused)</option>
                                <option value="closed" <?= (string) $benefitFieldValue('status', 'active') === 'closed' ? 'selected' : '' ?>>Closed (Historical)</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="benefit_notes">Notes & Compliance Details</label>
                            <textarea class="form-control <?= isset($errors['notes']) ? 'is-invalid' : '' ?>" id="benefit_notes" name="notes" rows="2" maxlength="1000" placeholder="Optional audit notes or scheme details"><?= e($benefitFieldValue('notes')) ?></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-check2 me-1"></i>Save Benefit Record</button>
                </div>
            </form>
    </div>
</div>
<?php endif; ?>
