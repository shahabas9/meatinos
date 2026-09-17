<?php
use MeatinOS\Core\Csrf;

$partnerErrors = $_SESSION['_errors'] ?? [];
$oldPartner = $_SESSION['_old'] ?? [];
$partnerModule = $oldPartner['_module'] ?? '';
$isNewPartnerError = !empty($partnerErrors) && $partnerModule === 'shareholders' && empty($oldPartner['id']);
$isEditPartnerError = !empty($partnerErrors) && $partnerModule === 'shareholders' && !empty($oldPartner['id']);
$isTransactionError = !empty($partnerErrors) && $partnerModule === 'partner_transactions';
$isDividendError = !empty($partnerErrors) && $partnerModule === 'partner_dividends';
$isNomineeError = !empty($partnerErrors) && $partnerModule === 'shareholder_nominees';

if (!empty($partnerErrors)) {
    unset($_SESSION['_errors'], $_SESSION['_old']);
}

$pVal = static fn(string $k, mixed $def = '') => ($isNewPartnerError && array_key_exists($k, $oldPartner)) ? $oldPartner[$k] : $def;
$eVal = static fn(string $k, mixed $fallback = '') => ($isEditPartnerError && array_key_exists($k, $oldPartner)) ? $oldPartner[$k] : ($selectedPartner[$k] ?? $fallback);

$formatDate = static fn(?string $date): string => $date && strtotime($date) ? date('d M Y', strtotime($date)) : '—';
$formatDateTime = static fn(?string $dt): string => $dt && strtotime($dt) ? date('d M Y, g:i A', strtotime($dt)) : '—';
$formatRate = static fn(mixed $rate): string => number_format((float)$rate, 2) . '%';
$currentTab = ($_GET['view'] ?? 'profile') === 'directory' ? 'directory' : 'profile';
?>

<div class="module-toolbar card-panel">
    <div>
        <p class="eyebrow"><i class="bi bi-shield-check me-1"></i>Administration & Governance</p>
        <h2>Partner Management Hub</h2>
        <p>Unified partner administration: master records, equity capital, dividend declaration, transactions, and nominee registry in one place.</p>
    </div>
    <div class="module-actions">
        <div class="btn-group" role="group" aria-label="View switch">
            <a class="btn <?= $currentTab === 'profile' ? 'btn-danger' : 'btn-light border' ?>" href="<?= e(url('partners', array_filter(['id' => $selectedPartner['id'] ?? null, 'view' => 'profile']))) ?>">
                <i class="bi bi-person-badge me-1"></i>Partner Profile
            </a>
            <a class="btn <?= $currentTab === 'directory' ? 'btn-danger' : 'btn-light border' ?>" href="<?= e(url('partners', ['view' => 'directory', 'q' => $query])) ?>">
                <i class="bi bi-table me-1"></i>All Partners Directory (<?= count($allPartners) ?>)
            </a>
        </div>
        <?php if ($canManage): ?>
            <button class="btn btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#partnerModal">
                <i class="bi bi-person-plus-fill me-1"></i>New Partner
            </button>
        <?php endif; ?>
    </div>
</div>

<!-- Partner Selector Bar -->
<div class="card-panel py-3 px-4 mb-4">
    <div class="row g-3 align-items-center">
        <div class="col-md-5 col-lg-4">
            <label class="form-label small text-muted text-uppercase fw-bold mb-1" for="partnerQuickSelect">
                <i class="bi bi-search me-1"></i>Select Partner
            </label>
            <form method="get" action="<?= e(url('partners')) ?>" id="partnerSelectForm">
                <input type="hidden" name="route" value="partners">
                <select class="form-select select2-partner" id="partnerQuickSelect" name="id" onchange="if (window.MeatinOSNav) { MeatinOSNav.navigate('?route=partners' + (this.value ? '&id=' + encodeURIComponent(this.value) : '')); } else { this.form.submit(); }">
                    <option value="">— Select a partner to view complete details —</option>
                    <?php
                    $lastGroup = null;
                    foreach ($allPartners as $partner):
                        $groupName = ($partner['shareholder_type'] ?? '') === 'director' ? 'Directors' : ($partner['director_name'] ? 'Under Director: ' . $partner['director_name'] : 'Partners without Director');
                        if ($groupName !== $lastGroup):
                            if ($lastGroup !== null) echo '</optgroup>';
                            $lastGroup = $groupName;
                            echo '<optgroup label="' . e($groupName) . '">';
                        endif;
                    ?>
                        <option value="<?= e($partner['id']) ?>" <?= ($selectedPartner && (int)$selectedPartner['id'] === (int)$partner['id']) ? 'selected' : '' ?>>
                            <?= e($partner['shareholder_code']) ?> — <?= e($partner['name']) ?> (<?= e(human_status($partner['shareholder_type'])) ?> · <?= money($partner['share_amount']) ?>)
                        </option>
                    <?php endforeach; if ($lastGroup !== null) echo '</optgroup>'; ?>
                </select>
            </form>
        </div>
        <div class="col-md-7 col-lg-8">
            <div class="d-flex flex-wrap gap-2 justify-content-md-end align-items-center">
                <div class="badge-pill-stat">
                    <span class="text-muted">Total Partners:</span>
                    <strong><?= $summary['total_partners'] ?></strong>
                </div>
                <div class="badge-pill-stat">
                    <span class="text-muted">Directors:</span>
                    <strong><?= $summary['directors'] ?></strong>
                </div>
                <div class="badge-pill-stat">
                    <span class="text-muted">Total Capital:</span>
                    <strong class="text-primary"><?= money($summary['total_share_amount']) ?></strong>
                </div>
                <div class="badge-pill-stat">
                    <span class="text-muted">Received:</span>
                    <strong class="text-success"><?= money($summary['total_received_share']) ?></strong>
                </div>
                <div class="badge-pill-stat">
                    <span class="text-muted">Pending:</span>
                    <strong class="text-warning"><?= money($summary['total_pending_share']) ?></strong>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($currentTab === 'directory'): ?>
    <!-- ALL PARTNERS DIRECTORY TABLE -->
    <div class="card-panel table-panel">
        <div class="workflow-section-title px-4 pt-3 pb-2 border-bottom">
            <div>
                <p class="eyebrow">Governance Registry</p>
                <h3>All Registered Partners & Directors</h3>
            </div>
            <form method="get" class="d-flex gap-2" action="<?= e(url('partners')) ?>">
                <input type="hidden" name="route" value="partners">
                <input type="hidden" name="view" value="directory">
                <div class="input-group input-group-sm" style="width: 280px;">
                    <input class="form-control" type="search" name="q" value="<?= e($query) ?>" placeholder="Search partner name, ID, phone...">
                    <button class="btn btn-outline-secondary" type="submit"><i class="bi bi-search"></i></button>
                </div>
            </form>
        </div>
        <div class="table-responsive">
            <table class="table data-table align-middle">
                <thead>
                    <tr>
                        <th>Partner ID</th>
                        <th>Name</th>
                        <th>Classification</th>
                        <th>Assigned Director</th>
                        <th>Contact</th>
                        <th class="text-end">Total Share</th>
                        <th class="text-end">Received</th>
                        <th class="text-end">Pending</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$allPartners): ?>
                        <tr><td colspan="10"><div class="table-empty"><i class="bi bi-people"></i><strong>No partners found</strong><span>Add your first partner or try another search query.</span></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($allPartners as $p): ?>
                            <tr class="<?= ($selectedPartner && (int)$selectedPartner['id'] === (int)$p['id']) ? 'table-active' : '' ?>">
                                <td>
                                    <span class="badge bg-dark-subtle text-dark font-monospace"><?= e($p['shareholder_code']) ?></span>
                                </td>
                                <td>
                                    <strong><?= e($p['name']) ?></strong>
                                    <?php if (!empty($p['designation'])): ?>
                                        <div class="text-muted small"><?= e($p['designation']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill <?= ($p['shareholder_type'] ?? '') === 'director' ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                                        <?= ($p['shareholder_type'] ?? '') === 'director' ? 'Director' : 'Partner under Director' ?>
                                    </span>
                                </td>
                                <td>
                                    <?= ($p['shareholder_type'] ?? '') === 'director' ? '<span class="text-muted fst-italic">Independent Director</span>' : e($p['director_name'] ?: 'Unassigned') ?>
                                </td>
                                <td>
                                    <div><i class="bi bi-telephone text-muted me-1 small"></i><?= e($p['phone'] ?: '—') ?></div>
                                    <?php if (!empty($p['email'])): ?><div class="small text-muted"><i class="bi bi-envelope text-muted me-1 small"></i><?= e($p['email']) ?></div><?php endif; ?>
                                </td>
                                <td class="text-end fw-bold"><?= money($p['share_amount']) ?></td>
                                <td class="text-end text-success fw-bold"><?= money($p['received_share']) ?></td>
                                <td class="text-end text-danger"><?= money($p['pending_share_amount']) ?></td>
                                <td>
                                    <span class="badge rounded-pill text-bg-<?= e(status_class((string)$p['status'])) ?>">
                                        <?= e(human_status((string)$p['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-danger" href="<?= e(url('partners', ['id' => $p['id'], 'view' => 'profile'])) ?>" title="View Complete Partner Profile">
                                        <i class="bi bi-eye-fill me-1"></i>View Profile
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

<?php elseif ($selectedPartner): ?>
    <!-- CONSOLIDATED PARTNER VIEW (ALL 4 SECTIONS) -->

    <!-- Partner Header Card -->
    <div class="card-panel partner-hero mb-4">
        <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="partner-avatar">
                    <i class="bi <?= ($selectedPartner['shareholder_type'] ?? '') === 'director' ? 'bi-person-gear' : 'bi-person-fill' ?>"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2 flex-wrap">
                        <h2 class="mb-0 fs-3 fw-bold"><?= e($selectedPartner['name']) ?></h2>
                        <span class="badge bg-dark font-monospace fs-6"><?= e($selectedPartner['shareholder_code']) ?></span>
                        <span class="badge rounded-pill text-bg-<?= e(status_class((string)$selectedPartner['status'])) ?> px-3 py-1">
                            <?= e(human_status((string)$selectedPartner['status'])) ?>
                        </span>
                        <span class="badge rounded-pill <?= ($selectedPartner['shareholder_type'] ?? '') === 'director' ? 'text-bg-primary' : 'text-bg-info text-dark' ?> px-3 py-1">
                            <?= ($selectedPartner['shareholder_type'] ?? '') === 'director' ? 'Director' : 'Partner under Director' ?>
                        </span>
                    </div>
                    <p class="text-muted mb-0 mt-1">
                        <?php if (!empty($selectedPartner['designation'])): ?>
                            <strong><?= e($selectedPartner['designation']) ?></strong> · 
                        <?php endif; ?>
                        <?= e(($selectedPartner['company_name'] ?? '') ?: 'Meatin Farms and Foods LLP') ?>
                        <?php if (!empty($selectedPartner['director_name']) && ($selectedPartner['shareholder_type'] ?? '') !== 'director'): ?>
                            · Assigned to: <strong class="text-dark"><?= e($selectedPartner['director_name']) ?></strong>
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <div class="btn-group flex-wrap">
                <?php if ($canManage): ?>
                    <button class="btn btn-outline-danger" type="button" data-bs-toggle="modal" data-bs-target="#editPartnerModal">
                        <i class="bi bi-pencil-square me-1"></i>Edit Details
                    </button>
                    <button class="btn btn-success" type="button" data-bs-toggle="modal" data-bs-target="#recordTransactionModal">
                        <i class="bi bi-cash-stack me-1"></i>Record Transaction
                    </button>
                    <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#declareDividendModal">
                        <i class="bi bi-pie-chart-fill me-1"></i>Declare Dividend
                    </button>
                    <button class="btn btn-secondary" type="button" data-bs-toggle="modal" data-bs-target="#addNomineeModal">
                        <i class="bi bi-person-hearts me-1"></i>Add Nominee
                    </button>
                <?php endif; ?>
                <a class="btn btn-light border" href="<?= e(url('partner.welcome', ['id' => $selectedPartner['id']])) ?>" target="_blank" title="Partner Welcome Letter">
                    <i class="bi bi-envelope-paper-heart me-1"></i>Welcome Letter
                </a>
                <a class="btn btn-light border" href="<?= e(url('documents', ['entity_type' => 'shareholder', 'entity_id' => $selectedPartner['id']])) ?>" title="Partner KYC & Documents">
                    <i class="bi bi-paperclip me-1"></i>Documents
                </a>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="row g-3 mt-4">
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card">
                    <span class="stat-label">Total Investment</span>
                    <strong class="stat-value text-dark"><?= money($selectedPartner['share_amount']) ?></strong>
                    <small class="text-muted"><?= number_format((float)$selectedPartner['ownership_percent'], 2) ?>% ownership</small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card">
                    <span class="stat-label">Received Capital</span>
                    <strong class="stat-value text-success"><?= money($selectedPartner['received_share']) ?></strong>
                    <small class="text-success"><?= $selectedPartner['share_amount'] > 0 ? number_format(((float)$selectedPartner['received_share'] / (float)$selectedPartner['share_amount']) * 100, 1) . '% paid' : '100%' ?></small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card">
                    <span class="stat-label">Pending Capital</span>
                    <strong class="stat-value text-danger"><?= money($selectedPartner['pending_share_amount']) ?></strong>
                    <small class="text-muted">Balance due</small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card">
                    <span class="stat-label">Dividend Earned</span>
                    <strong class="stat-value text-primary"><?= money($dividendMetrics['dividend_earned'] ?? 0) ?></strong>
                    <small class="text-muted">Gross declared</small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card">
                    <span class="stat-label">Dividend Paid</span>
                    <strong class="stat-value text-success"><?= money($dividendMetrics['dividend_paid'] ?? 0) ?></strong>
                    <small class="text-muted">Disbursed net</small>
                </div>
            </div>
            <div class="col-sm-6 col-lg-2">
                <div class="stat-card">
                    <span class="stat-label">Pending Dividend</span>
                    <strong class="stat-value text-warning"><?= money($dividendMetrics['pending_dividend'] ?? 0) ?></strong>
                    <small class="text-muted">Declared unpaid</small>
                </div>
            </div>
        </div>
    </div>

    <!-- ==========================================
         SECTION 1: PARTNER BASIC DETAILS
         ========================================== -->
    <section class="card-panel mb-4">
        <div class="workflow-section-title px-4 pt-3 pb-2 border-bottom">
            <div>
                <p class="eyebrow text-uppercase">Section 1</p>
                <h3 class="fs-5 fw-bold"><i class="bi bi-person-vcard text-danger me-2"></i>Partner Basic Details</h3>
            </div>
            <?php if ($canManage): ?>
                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#editPartnerModal">
                    <i class="bi bi-pencil me-1"></i>Edit
                </button>
            <?php endif; ?>
        </div>
        <div class="p-4">
            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="field-display">
                        <span class="field-label">Partner ID</span>
                        <strong class="field-value font-monospace fs-6"><?= e($selectedPartner['shareholder_code']) ?></strong>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="field-display">
                        <span class="field-label">Partner Name</span>
                        <strong class="field-value fs-6 text-dark"><?= e($selectedPartner['name']) ?></strong>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="field-display">
                        <span class="field-label">Partner Category</span>
                        <div class="field-value">
                            <span class="badge <?= ($selectedPartner['shareholder_type'] ?? '') === 'director' ? 'text-bg-primary' : 'text-bg-secondary' ?>">
                                <?= ($selectedPartner['shareholder_type'] ?? '') === 'director' ? 'Director' : 'Partner under Director' ?>
                            </span>
                            <?php if (!empty($selectedPartner['designation'])): ?>
                                <span class="ms-1 text-muted small">(<?= e($selectedPartner['designation']) ?>)</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="field-display">
                        <span class="field-label">Assigned Director</span>
                        <strong class="field-value">
                            <?= ($selectedPartner['shareholder_type'] ?? '') === 'director' ? '<span class="badge bg-light text-dark border">Independent Director</span>' : e($selectedPartner['director_name'] ?: 'None') ?>
                        </strong>
                    </div>
                </div>

                <div class="col-md-6 col-lg-3">
                    <div class="field-display">
                        <span class="field-label">Primary Contact</span>
                        <div class="field-value">
                            <div><i class="bi bi-telephone text-muted me-1"></i><?= e($selectedPartner['phone'] ?: '—') ?></div>
                            <?php if (!empty($selectedPartner['phone_secondary'])): ?>
                                <small class="text-muted"><i class="bi bi-phone text-muted me-1"></i><?= e($selectedPartner['phone_secondary']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="field-display">
                        <span class="field-label">Email Address</span>
                        <div class="field-value">
                            <?= !empty($selectedPartner['email']) ? '<a href="mailto:'.e($selectedPartner['email']).'"><i class="bi bi-envelope me-1"></i>'.e($selectedPartner['email']).'</a>' : '—' ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="field-display">
                        <span class="field-label">Joining Date</span>
                        <strong class="field-value"><?= $formatDate($selectedPartner['joined_on']) ?></strong>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="field-display">
                        <span class="field-label">Current Status</span>
                        <div class="field-value">
                            <span class="badge rounded-pill text-bg-<?= e(status_class((string)$selectedPartner['status'])) ?> px-3">
                                <?= e(human_status((string)$selectedPartner['status'])) ?>
                            </span>
                            <?php if ($selectedPartner['status'] === 'cancelled' && !empty($selectedPartner['cancellation_date'])): ?>
                                <small class="d-block text-danger mt-1">Cancelled on <?= $formatDate($selectedPartner['cancellation_date']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Investment & Share Details sub-card -->
                <div class="col-12">
                    <div class="p-3 bg-light rounded-3 border">
                        <h6 class="text-uppercase small fw-bold text-muted mb-3"><i class="bi bi-wallet2 me-1"></i>Investment / Share Structure</h6>
                        <div class="row g-3">
                            <div class="col-6 col-md-2">
                                <span class="d-block small text-muted">Total Share Amount</span>
                                <strong class="fs-6 text-dark"><?= money($selectedPartner['share_amount']) ?></strong>
                            </div>
                            <div class="col-6 col-md-2">
                                <span class="d-block small text-muted">Share Unit Value</span>
                                <strong class="fs-6 text-dark"><?= money($selectedPartner['share_value']) ?></strong>
                            </div>
                            <div class="col-6 col-md-2">
                                <span class="d-block small text-muted">Ownership Stake</span>
                                <strong class="fs-6 text-primary"><?= number_format((float)$selectedPartner['ownership_percent'], 2) ?>%</strong>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="d-block small text-muted">Received Capital</span>
                                <strong class="fs-6 text-success"><?= money($selectedPartner['received_share']) ?></strong>
                            </div>
                            <div class="col-6 col-md-3">
                                <span class="d-block small text-muted">Pending Capital</span>
                                <strong class="fs-6 text-danger"><?= money($selectedPartner['pending_share_amount']) ?></strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-8">
                    <div class="field-display">
                        <span class="field-label">Residential / Business Address</span>
                        <div class="field-value text-secondary"><?= nl2br(e($selectedPartner['address'] ?: '—')) ?></div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="field-display">
                        <span class="field-label">KYC & Tax Identifiers</span>
                        <div class="field-value small">
                            <div><strong>PAN:</strong> <span class="font-monospace"><?= e($selectedPartner['pan_number'] ?: '—') ?></span></div>
                            <div class="mt-1"><strong>Aadhaar:</strong> <span class="font-monospace"><?= e($selectedPartner['aadhaar_number'] ?: '—') ?></span></div>
                            <div class="mt-1"><strong>Bank Ref:</strong> <?= e($selectedPartner['account_reference'] ?: '—') ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==========================================
         SECTION 2: PARTNER DIVIDEND
         ========================================== -->
    <section class="card-panel table-panel mb-4">
        <div class="workflow-section-title px-4 pt-3 pb-2 border-bottom">
            <div>
                <p class="eyebrow text-uppercase">Section 2</p>
                <h3 class="fs-5 fw-bold"><i class="bi bi-pie-chart-fill text-danger me-2"></i>Partner Dividend</h3>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border"><?= count($dividends) ?> declared dividend(s)</span>
                <?php if ($canManage): ?>
                    <button class="btn btn-sm btn-danger" type="button" data-bs-toggle="modal" data-bs-target="#declareDividendModal">
                        <i class="bi bi-plus-lg me-1"></i>Declare Dividend
                    </button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Dividend KPI Snapshot -->
        <div class="px-4 py-3 bg-light border-bottom">
            <div class="row g-3 text-center text-sm-start">
                <div class="col-6 col-md-2">
                    <span class="small text-muted d-block">Total Investment</span>
                    <strong class="fs-6"><?= money($dividendMetrics['total_investment'] ?? 0) ?></strong>
                </div>
                <div class="col-6 col-md-2">
                    <span class="small text-muted d-block">Latest Dividend Rate</span>
                    <strong class="fs-6 text-primary"><?= $formatRate($dividendMetrics['dividend_rate'] ?? 0) ?></strong>
                </div>
                <div class="col-6 col-md-2">
                    <span class="small text-muted d-block">Dividend Earned</span>
                    <strong class="fs-6 text-dark"><?= money($dividendMetrics['dividend_earned'] ?? 0) ?></strong>
                </div>
                <div class="col-6 col-md-3">
                    <span class="small text-muted d-block">Dividend Paid</span>
                    <strong class="fs-6 text-success"><?= money($dividendMetrics['dividend_paid'] ?? 0) ?></strong>
                </div>
                <div class="col-6 col-md-3">
                    <span class="small text-muted d-block">Pending Dividend</span>
                    <strong class="fs-6 text-warning"><?= money($dividendMetrics['pending_dividend'] ?? 0) ?></strong>
                </div>
            </div>
        </div>

        <!-- Dividend History Table -->
        <div class="table-responsive">
            <table class="table data-table align-middle">
                <thead>
                    <tr>
                        <th>Dividend ID</th>
                        <th>Financial Year</th>
                        <th>Declaration Date</th>
                        <th class="text-end">Share Basis</th>
                        <th class="text-end">Dividend Rate</th>
                        <th class="text-end">Gross (Earned)</th>
                        <th class="text-end">TDS</th>
                        <th class="text-end">Net (Paid)</th>
                        <th>Paid Date</th>
                        <th>Payment Mode / Account</th>
                        <th>Status</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$dividends): ?>
                        <tr><td colspan="12"><div class="table-empty py-4"><i class="bi bi-pie-chart"></i><strong>No dividend history</strong><span>No dividends have been declared for this partner yet.</span></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($dividends as $div): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark-subtle text-dark font-monospace"><?= e($div['dividend_number']) ?></span>
                                </td>
                                <td><span class="badge bg-secondary-subtle text-secondary"><?= e($div['financial_year']) ?></span></td>
                                <td><?= $formatDate($div['declaration_date']) ?></td>
                                <td class="text-end"><?= money($div['share_value_basis']) ?></td>
                                <td class="text-end text-primary fw-bold"><?= $formatRate($div['dividend_rate']) ?></td>
                                <td class="text-end fw-bold"><?= money($div['gross_amount']) ?></td>
                                <td class="text-end text-muted"><?= money($div['tds_amount']) ?></td>
                                <td class="text-end text-success fw-bold"><?= money($div['net_amount']) ?></td>
                                <td><?= $formatDate($div['paid_date']) ?></td>
                                <td>
                                    <small><?= e($div['paying_bank_name'] ?: '—') ?></small>
                                    <?php if (!empty($div['reference_number'])): ?><div class="text-muted small font-monospace">Ref: <?= e($div['reference_number']) ?></div><?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill text-bg-<?= e(status_class((string)$div['status'])) ?>">
                                        <?= e(human_status((string)$div['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-light border text-danger" href="<?= e(url('workflow', ['entity' => 'partner_dividend', 'id' => $div['id']])) ?>" title="Approval Workflow">
                                        <i class="bi bi-signpost-split"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ==========================================
         SECTION 3: PARTNER TRANSACTIONS
         ========================================== -->
    <section class="card-panel table-panel mb-4">
        <div class="workflow-section-title px-4 pt-3 pb-2 border-bottom">
            <div>
                <p class="eyebrow text-uppercase">Section 3</p>
                <h3 class="fs-5 fw-bold"><i class="bi bi-currency-rupee text-danger me-2"></i>Partner Transactions</h3>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border"><?= count($transactions) ?> transaction(s)</span>
                <?php if ($canManage): ?>
                    <button class="btn btn-sm btn-success" type="button" data-bs-toggle="modal" data-bs-target="#recordTransactionModal">
                        <i class="bi bi-plus-lg me-1"></i>Record Transaction
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table data-table align-middle">
                <thead>
                    <tr>
                        <th>Transaction ID</th>
                        <th>Date</th>
                        <th>Transaction Type</th>
                        <th class="text-end text-success">Credit (+)</th>
                        <th class="text-end text-danger">Debit (−)</th>
                        <th class="text-end">Amount</th>
                        <th>Payment Mode</th>
                        <th class="text-end">Remaining Share Balance</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$transactions): ?>
                        <tr><td colspan="10"><div class="table-empty py-4"><i class="bi bi-currency-rupee"></i><strong>No transactions recorded</strong><span>No installment receipts or share adjustments found for this partner.</span></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($transactions as $tx): ?>
                            <tr>
                                <td>
                                    <span class="badge bg-dark-subtle text-dark font-monospace"><?= e($tx['transaction_number']) ?></span>
                                </td>
                                <td><?= $formatDate($tx['transaction_date']) ?></td>
                                <td>
                                    <?php
                                    $typeBadge = match ($tx['transaction_type']) {
                                        'installment' => 'text-bg-success',
                                        'share_addition' => 'text-bg-primary',
                                        'share_deduction' => 'text-bg-warning',
                                        'refund' => 'text-bg-danger',
                                        'cancellation_settlement' => 'text-bg-dark',
                                        default => 'text-bg-secondary'
                                    };
                                    ?>
                                    <span class="badge rounded-pill <?= $typeBadge ?>">
                                        <?= e(human_status((string)$tx['transaction_type'])) ?>
                                    </span>
                                </td>
                                <td class="text-end text-success fw-bold">
                                    <?= $tx['credit'] > 0 ? '+' . money($tx['credit']) : '—' ?>
                                </td>
                                <td class="text-end text-danger fw-bold">
                                    <?= $tx['debit'] > 0 ? '−' . money($tx['debit']) : '—' ?>
                                </td>
                                <td class="text-end fw-bold"><?= money($tx['amount']) ?></td>
                                <td>
                                    <span class="badge bg-light text-dark border"><?= e(human_status((string)$tx['payment_method'])) ?></span>
                                    <?php if (!empty($tx['reference_number'])): ?>
                                        <div class="text-muted small font-monospace">Ref: <?= e($tx['reference_number']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold text-secondary">
                                    <?= money($tx['running_balance']) ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill text-bg-<?= e(status_class((string)$tx['status'])) ?>">
                                        <?= e(human_status((string)$tx['status'])) ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <div class="btn-group btn-group-sm">
                                        <?php if (in_array($tx['status'], ['posted', 'reversed'], true)): ?>
                                            <a class="btn btn-light border" href="<?= e(url('partner.receipt', ['id' => $tx['id']])) ?>" target="_blank" title="Print Receipt">
                                                <i class="bi bi-printer"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a class="btn btn-light border text-danger" href="<?= e(url('workflow', ['entity' => 'partner_transaction', 'id' => $tx['id']])) ?>" title="Workflow">
                                            <i class="bi bi-signpost-split"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ==========================================
         SECTION 4: PARTNER NOMINEE
         ========================================== -->
    <section class="card-panel table-panel mb-4">
        <div class="workflow-section-title px-4 pt-3 pb-2 border-bottom">
            <div>
                <p class="eyebrow text-uppercase">Section 4</p>
                <h3 class="fs-5 fw-bold"><i class="bi bi-person-hearts text-danger me-2"></i>Partner Nominee Details</h3>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border"><?= count($nominees) ?> nominee(s)</span>
                <?php if ($canManage): ?>
                    <button class="btn btn-sm btn-secondary" type="button" data-bs-toggle="modal" data-bs-target="#addNomineeModal">
                        <i class="bi bi-plus-lg me-1"></i>Add Nominee
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table data-table align-middle">
                <thead>
                    <tr>
                        <th>Nominee Name</th>
                        <th>Relationship</th>
                        <th>Contact Details</th>
                        <th>Aadhaar Number</th>
                        <th class="text-end">Nominee Share (%)</th>
                        <th>Minor Status / Guardian</th>
                        <th>Nominee Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$nominees): ?>
                        <tr><td colspan="7"><div class="table-empty py-4"><i class="bi bi-person-hearts"></i><strong>No nominees registered</strong><span>No beneficiary nominee has been assigned to this partner yet.</span></div></td></tr>
                    <?php else: ?>
                        <?php foreach ($nominees as $nom): ?>
                            <tr>
                                <td>
                                    <strong><?= e($nom['nominee_name']) ?></strong>
                                    <?php if (!empty($nom['date_of_birth'])): ?>
                                        <div class="text-muted small">DOB: <?= $formatDate($nom['date_of_birth']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= e($nom['relationship']) ?></span></td>
                                <td>
                                    <?= !empty($nom['phone']) ? '<i class="bi bi-telephone text-muted me-1 small"></i>' . e($nom['phone']) : '—' ?>
                                </td>
                                <td>
                                    <?= !empty($nom['aadhaar_number']) ? '<span class="font-monospace">' . e($nom['aadhaar_number']) . '</span>' : '—' ?>
                                </td>
                                <td class="text-end fw-bold text-primary fs-6">
                                    <?= number_format((float)$nom['allocation_percent'], 2) ?>%
                                </td>
                                <td>
                                    <?php if (!empty($nom['is_minor'])): ?>
                                        <span class="badge text-bg-warning">Minor</span>
                                        <div class="small text-muted mt-1">Guardian: <strong><?= e($nom['guardian_name'] ?: 'Not specified') ?></strong></div>
                                    <?php else: ?>
                                        <span class="badge bg-light text-dark border">Major (18+)</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge rounded-pill text-bg-<?= e(status_class((string)$nom['status'])) ?>">
                                        <?= e(human_status((string)$nom['status'])) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

<?php else: ?>
    <div class="card-panel text-center py-5">
        <i class="bi bi-people display-3 text-muted"></i>
        <h3 class="mt-3">No Partner Selected</h3>
        <p class="text-muted">Select a partner from the dropdown above or switch to the directory to pick a partner.</p>
        <a class="btn btn-danger" href="<?= e(url('partners', ['view' => 'directory'])) ?>">Open Partner Directory</a>
    </div>
<?php endif; ?>

<!-- ==========================================
     INLINE MODALS FOR DIRECT ACTIONS
     ========================================== -->

<!-- Modal 1: Add / Edit Partner Modal -->
<?php if ($canManage): ?>
<div class="modal fade" id="partnerModal" tabindex="-1" aria-labelledby="partnerModalLabel" aria-hidden="true" data-auto-open="<?= $isNewPartnerError ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content" action="<?= e(url('module.save')) ?>" method="post" novalidate data-prevent-double-submit>
            <?= Csrf::field() ?>
            <input type="hidden" name="_module" value="shareholders">
            <input type="hidden" name="_return_to" value="<?= e(url('partners')) ?>">
            <input type="hidden" name="id" value="0">
                <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1">Partner Registry</p>
                        <h4 class="modal-title" id="partnerModalLabel">Register New Partner / Director</h4>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($isNewPartnerError && !empty($partnerErrors)): ?>
                        <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
                            <div class="flex-grow-1">
                                <strong class="d-block mb-1">Please correct the following field<?= count($partnerErrors) > 1 ? 's' : '' ?>:</strong>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($partnerErrors as $fKey => $fErr): ?>
                                        <li><?= e($fErr) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="new_sh_code">Partner ID *</label>
                            <input class="form-control font-monospace <?= ($isNewPartnerError && isset($partnerErrors['shareholder_code'])) ? 'is-invalid' : '' ?>" id="new_sh_code" name="shareholder_code" placeholder="e.g. PTR-0020" value="<?= e($pVal('shareholder_code')) ?>" required>
                            <?php if ($isNewPartnerError && isset($partnerErrors['shareholder_code'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['shareholder_code']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="new_name">Full Name *</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['name'])) ? 'is-invalid' : '' ?>" id="new_name" name="name" value="<?= e($pVal('name')) ?>" required>
                            <?php if ($isNewPartnerError && isset($partnerErrors['name'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_company">Company *</label>
                            <select class="form-select <?= ($isNewPartnerError && isset($partnerErrors['company_id'])) ? 'is-invalid' : '' ?>" id="new_company" name="company_id" required>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= e($c['id']) ?>" <?= (string)$pVal('company_id') === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isNewPartnerError && isset($partnerErrors['company_id'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['company_id']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_type">Classification *</label>
                            <select class="form-select <?= ($isNewPartnerError && isset($partnerErrors['shareholder_type'])) ? 'is-invalid' : '' ?>" id="new_type" name="shareholder_type" required>
                                <option value="partner" <?= $pVal('shareholder_type', 'partner') === 'partner' ? 'selected' : '' ?>>Partner under director</option>
                                <option value="director" <?= $pVal('shareholder_type') === 'director' ? 'selected' : '' ?>>Director</option>
                            </select>
                            <?php if ($isNewPartnerError && isset($partnerErrors['shareholder_type'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['shareholder_type']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_director">Assigned Director</label>
                            <select class="form-select <?= ($isNewPartnerError && isset($partnerErrors['director_id'])) ? 'is-invalid' : '' ?>" id="new_director" name="director_id">
                                <option value="">— None / Independent —</option>
                                <?php foreach ($directors as $d): ?>
                                    <option value="<?= e($d['id']) ?>" <?= (string)$pVal('director_id') === (string)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isNewPartnerError && isset($partnerErrors['director_id'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['director_id']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_joined">Joining Date</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['joined_on'])) ? 'is-invalid' : '' ?>" id="new_joined" name="joined_on" type="date" value="<?= e($pVal('joined_on', date('Y-m-d'))) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['joined_on'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['joined_on']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_sort">Sort Order</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['sort_order'])) ? 'is-invalid' : '' ?>" id="new_sort" name="sort_order" type="number" value="<?= e($pVal('sort_order', 10)) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['sort_order'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['sort_order']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-muted">Capital & Ownership</strong></div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_share_amount">Total Share Amount (INR) *</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['share_amount'])) ? 'is-invalid' : '' ?>" id="new_share_amount" name="share_amount" type="number" step="0.01" value="<?= e($pVal('share_amount')) ?>" required>
                            <?php if ($isNewPartnerError && isset($partnerErrors['share_amount'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['share_amount']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_share_val">Share Unit Value (INR) *</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['share_value'])) ? 'is-invalid' : '' ?>" id="new_share_val" name="share_value" type="number" step="0.01" value="<?= e($pVal('share_value', '100.00')) ?>" required>
                            <?php if ($isNewPartnerError && isset($partnerErrors['share_value'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['share_value']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_ownership">Ownership (%) *</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['ownership_percent'])) ? 'is-invalid' : '' ?>" id="new_ownership" name="ownership_percent" type="number" step="0.0001" value="<?= e($pVal('ownership_percent', '0.00')) ?>" required>
                            <?php if ($isNewPartnerError && isset($partnerErrors['ownership_percent'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['ownership_percent']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="new_received">Received Share Amount (INR) *</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['received_share'])) ? 'is-invalid' : '' ?>" id="new_received" name="received_share" type="number" step="0.01" value="<?= e($pVal('received_share', '0.00')) ?>" required>
                            <?php if ($isNewPartnerError && isset($partnerErrors['received_share'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['received_share']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-muted">Contact & KYC</strong></div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_phone">Primary Phone</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['phone'])) ? 'is-invalid' : '' ?>" id="new_phone" name="phone" value="<?= e($pVal('phone')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['phone'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['phone']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_phone2">Secondary Phone</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['phone_secondary'])) ? 'is-invalid' : '' ?>" id="new_phone2" name="phone_secondary" value="<?= e($pVal('phone_secondary')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['phone_secondary'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['phone_secondary']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_email">Email</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['email'])) ? 'is-invalid' : '' ?>" id="new_email" name="email" type="email" value="<?= e($pVal('email')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['email'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_pan">PAN Number</label>
                            <input class="form-control font-monospace <?= ($isNewPartnerError && isset($partnerErrors['pan_number'])) ? 'is-invalid' : '' ?>" id="new_pan" name="pan_number" maxlength="20" value="<?= e($pVal('pan_number')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['pan_number'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['pan_number']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_aadhaar">Aadhaar Number</label>
                            <input class="form-control font-monospace <?= ($isNewPartnerError && isset($partnerErrors['aadhaar_number'])) ? 'is-invalid' : '' ?>" id="new_aadhaar" name="aadhaar_number" maxlength="20" value="<?= e($pVal('aadhaar_number')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['aadhaar_number'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['aadhaar_number']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_status">Status *</label>
                            <select class="form-select <?= ($isNewPartnerError && isset($partnerErrors['status'])) ? 'is-invalid' : '' ?>" id="new_status" name="status" required>
                                <option value="active" <?= $pVal('status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $pVal('status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="cancelled" <?= $pVal('status') === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <?php if ($isNewPartnerError && isset($partnerErrors['status'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['status']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="new_address">Address</label>
                            <textarea class="form-control <?= ($isNewPartnerError && isset($partnerErrors['address'])) ? 'is-invalid' : '' ?>" id="new_address" name="address" rows="2"><?= e($pVal('address')) ?></textarea>
                            <?php if ($isNewPartnerError && isset($partnerErrors['address'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['address']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12"><hr class="my-1"><div class="d-flex align-items-center justify-content-between"><strong class="small text-uppercase text-danger"><i class="bi bi-person-hearts me-1"></i>Partner Nominee Details</strong><span class="badge text-bg-light border text-muted small">Primary Nominee</span></div></div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_nom_name">Nominee Name</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['nominee_name'])) ? 'is-invalid' : '' ?>" id="new_nom_name" name="nominee_name" placeholder="Full name of designated nominee" value="<?= e($pVal('nominee_name')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['nominee_name'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="new_nom_rel">Relationship</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['nominee_relationship'])) ? 'is-invalid' : '' ?>" id="new_nom_rel" name="nominee_relationship" placeholder="e.g. Spouse, Son, Daughter, Brother" value="<?= e($pVal('nominee_relationship')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['nominee_relationship'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_relationship']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_nom_phone">Phone Number</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['nominee_phone'])) ? 'is-invalid' : '' ?>" id="new_nom_phone" name="nominee_phone" placeholder="Mobile / contact number" value="<?= e($pVal('nominee_phone')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['nominee_phone'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_phone']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_nom_aadhaar">Aadhaar Number</label>
                            <input class="form-control font-monospace <?= ($isNewPartnerError && isset($partnerErrors['nominee_aadhaar'])) ? 'is-invalid' : '' ?>" id="new_nom_aadhaar" name="nominee_aadhaar" maxlength="20" placeholder="12-digit Aadhaar number" value="<?= e($pVal('nominee_aadhaar')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['nominee_aadhaar'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_aadhaar']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_nom_dob">Date of Birth</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['nominee_dob'])) ? 'is-invalid' : '' ?>" id="new_nom_dob" name="nominee_dob" type="date" value="<?= e($pVal('nominee_dob')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['nominee_dob'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_dob']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_nom_allocation">Nominee Share / Allocation (%)</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['nominee_allocation'])) ? 'is-invalid' : '' ?>" id="new_nom_allocation" name="nominee_allocation" type="number" step="0.001" value="<?= e($pVal('nominee_allocation', '100.00')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['nominee_allocation'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_allocation']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_nom_minor">Is Minor?</label>
                            <select class="form-select <?= ($isNewPartnerError && isset($partnerErrors['nominee_minor'])) ? 'is-invalid' : '' ?>" id="new_nom_minor" name="nominee_minor" onchange="toggleNewNomineeGuardian(this.value)">
                                <option value="0" <?= (string)$pVal('nominee_minor', '0') === '0' ? 'selected' : '' ?>>No (Major)</option>
                                <option value="1" <?= (string)$pVal('nominee_minor') === '1' ? 'selected' : '' ?>>Yes (Minor)</option>
                            </select>
                            <?php if ($isNewPartnerError && isset($partnerErrors['nominee_minor'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_minor']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="new_nom_status">Nominee Status</label>
                            <select class="form-select <?= ($isNewPartnerError && isset($partnerErrors['nominee_status'])) ? 'is-invalid' : '' ?>" id="new_nom_status" name="nominee_status">
                                <option value="active" <?= $pVal('nominee_status', 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $pVal('nominee_status') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                            <?php if ($isNewPartnerError && isset($partnerErrors['nominee_status'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_status']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12" id="new_nom_guardian_wrap" style="<?= ((string)$pVal('nominee_minor', '0') === '1' || ($isNewPartnerError && isset($partnerErrors['nominee_guardian']))) ? '' : 'display: none;' ?>">
                            <label class="form-label" for="new_nom_guardian">Guardian Name (Required if Minor)</label>
                            <input class="form-control <?= ($isNewPartnerError && isset($partnerErrors['nominee_guardian'])) ? 'is-invalid' : '' ?>" id="new_nom_guardian" name="nominee_guardian" placeholder="Legal guardian full name" value="<?= e($pVal('nominee_guardian')) ?>">
                            <?php if ($isNewPartnerError && isset($partnerErrors['nominee_guardian'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_guardian']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-check2 me-1"></i>Save Partner</button>
                </div>
            </form>
    </div>
</div>

<?php if ($selectedPartner): ?>
<!-- Modal 2: Edit Selected Partner Modal -->
<div class="modal fade" id="editPartnerModal" tabindex="-1" aria-labelledby="editPartnerModalLabel" aria-hidden="true" data-auto-open="<?= $isEditPartnerError ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <form class="modal-content" action="<?= e(url('module.save')) ?>" method="post" novalidate data-prevent-double-submit>
            <?= Csrf::field() ?>
            <input type="hidden" name="_module" value="shareholders">
            <input type="hidden" name="_return_to" value="<?= e(url('partners', ['id' => $selectedPartner['id']])) ?>">
            <input type="hidden" name="id" value="<?= e($selectedPartner['id']) ?>">
            <div class="modal-header">
                    <div>
                        <p class="eyebrow mb-1">Partner ID: <?= e($selectedPartner['shareholder_code']) ?></p>
                        <h4 class="modal-title" id="editPartnerModalLabel">Edit Partner Details — <?= e($selectedPartner['name']) ?></h4>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($isEditPartnerError && !empty($partnerErrors)): ?>
                        <div class="alert alert-danger d-flex align-items-start gap-2 mb-3" role="alert">
                            <i class="bi bi-exclamation-triangle-fill fs-5 flex-shrink-0 mt-1"></i>
                            <div class="flex-grow-1">
                                <strong class="d-block mb-1">Please correct the following field<?= count($partnerErrors) > 1 ? 's' : '' ?>:</strong>
                                <ul class="mb-0 ps-3">
                                    <?php foreach ($partnerErrors as $fKey => $fErr): ?>
                                        <li><?= e($fErr) ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label" for="edit_sh_code">Partner ID *</label>
                            <input class="form-control font-monospace <?= ($isEditPartnerError && isset($partnerErrors['shareholder_code'])) ? 'is-invalid' : '' ?>" id="edit_sh_code" name="shareholder_code" value="<?= e($eVal('shareholder_code', $selectedPartner['shareholder_code'])) ?>" required>
                            <?php if ($isEditPartnerError && isset($partnerErrors['shareholder_code'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['shareholder_code']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label" for="edit_name">Full Name *</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['name'])) ? 'is-invalid' : '' ?>" id="edit_name" name="name" value="<?= e($eVal('name', $selectedPartner['name'])) ?>" required>
                            <?php if ($isEditPartnerError && isset($partnerErrors['name'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="edit_company">Company *</label>
                            <select class="form-select <?= ($isEditPartnerError && isset($partnerErrors['company_id'])) ? 'is-invalid' : '' ?>" id="edit_company" name="company_id" required>
                                <?php foreach ($companies as $c): ?>
                                    <option value="<?= e($c['id']) ?>" <?= (int)$eVal('company_id', $selectedPartner['company_id']) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isEditPartnerError && isset($partnerErrors['company_id'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['company_id']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="edit_type">Classification *</label>
                            <select class="form-select <?= ($isEditPartnerError && isset($partnerErrors['shareholder_type'])) ? 'is-invalid' : '' ?>" id="edit_type" name="shareholder_type" required>
                                <option value="partner" <?= $eVal('shareholder_type', $selectedPartner['shareholder_type']) === 'partner' ? 'selected' : '' ?>>Partner under director</option>
                                <option value="director" <?= $eVal('shareholder_type', $selectedPartner['shareholder_type']) === 'director' ? 'selected' : '' ?>>Director</option>
                            </select>
                            <?php if ($isEditPartnerError && isset($partnerErrors['shareholder_type'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['shareholder_type']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="edit_director">Assigned Director</label>
                            <select class="form-select <?= ($isEditPartnerError && isset($partnerErrors['director_id'])) ? 'is-invalid' : '' ?>" id="edit_director" name="director_id">
                                <option value="">— None / Independent —</option>
                                <?php foreach ($directors as $d): if ((int)$d['id'] === (int)$selectedPartner['id']) continue; ?>
                                    <option value="<?= e($d['id']) ?>" <?= (int)$eVal('director_id', $selectedPartner['director_id']) === (int)$d['id'] ? 'selected' : '' ?>><?= e($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ($isEditPartnerError && isset($partnerErrors['director_id'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['director_id']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="edit_designation">Designation</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['designation'])) ? 'is-invalid' : '' ?>" id="edit_designation" name="designation" value="<?= e($eVal('designation', $selectedPartner['designation'])) ?>">
                            <?php if ($isEditPartnerError && isset($partnerErrors['designation'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['designation']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="edit_joined">Joining Date</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['joined_on'])) ? 'is-invalid' : '' ?>" id="edit_joined" name="joined_on" type="date" value="<?= e($eVal('joined_on', $selectedPartner['joined_on'])) ?>">
                            <?php if ($isEditPartnerError && isset($partnerErrors['joined_on'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['joined_on']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-muted">Capital & Ownership</strong></div>
                        <div class="col-md-3">
                            <label class="form-label" for="edit_share_amount">Total Share Amount (INR) *</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['share_amount'])) ? 'is-invalid' : '' ?>" id="edit_share_amount" name="share_amount" type="number" step="0.01" value="<?= e($eVal('share_amount', $selectedPartner['share_amount'])) ?>" required>
                            <?php if ($isEditPartnerError && isset($partnerErrors['share_amount'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['share_amount']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="edit_share_val">Share Unit Value (INR) *</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['share_value'])) ? 'is-invalid' : '' ?>" id="edit_share_val" name="share_value" type="number" step="0.01" value="<?= e($eVal('share_value', $selectedPartner['share_value'])) ?>" required>
                            <?php if ($isEditPartnerError && isset($partnerErrors['share_value'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['share_value']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="edit_ownership">Ownership (%) *</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['ownership_percent'])) ? 'is-invalid' : '' ?>" id="edit_ownership" name="ownership_percent" type="number" step="0.0001" value="<?= e($eVal('ownership_percent', $selectedPartner['ownership_percent'])) ?>" required>
                            <?php if ($isEditPartnerError && isset($partnerErrors['ownership_percent'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['ownership_percent']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label" for="edit_received">Received Share Amount (INR) *</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['received_share'])) ? 'is-invalid' : '' ?>" id="edit_received" name="received_share" type="number" step="0.01" value="<?= e($eVal('received_share', $selectedPartner['received_share'])) ?>" required>
                            <?php if ($isEditPartnerError && isset($partnerErrors['received_share'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['received_share']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="col-12"><hr class="my-1"><strong class="small text-uppercase text-muted">Contact & KYC</strong></div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_phone">Primary Phone</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['phone'])) ? 'is-invalid' : '' ?>" id="edit_phone" name="phone" value="<?= e($eVal('phone', $selectedPartner['phone'])) ?>">
                            <?php if ($isEditPartnerError && isset($partnerErrors['phone'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['phone']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_phone2">Secondary Phone</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['phone_secondary'])) ? 'is-invalid' : '' ?>" id="edit_phone2" name="phone_secondary" value="<?= e($eVal('phone_secondary', $selectedPartner['phone_secondary'])) ?>">
                            <?php if ($isEditPartnerError && isset($partnerErrors['phone_secondary'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['phone_secondary']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_email">Email</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['email'])) ? 'is-invalid' : '' ?>" id="edit_email" name="email" type="email" value="<?= e($eVal('email', $selectedPartner['email'])) ?>">
                            <?php if ($isEditPartnerError && isset($partnerErrors['email'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['email']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_pan">PAN Number</label>
                            <input class="form-control font-monospace <?= ($isEditPartnerError && isset($partnerErrors['pan_number'])) ? 'is-invalid' : '' ?>" id="edit_pan" name="pan_number" value="<?= e($eVal('pan_number', $selectedPartner['pan_number'])) ?>" maxlength="20">
                            <?php if ($isEditPartnerError && isset($partnerErrors['pan_number'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['pan_number']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_aadhaar">Aadhaar Number</label>
                            <input class="form-control font-monospace <?= ($isEditPartnerError && isset($partnerErrors['aadhaar_number'])) ? 'is-invalid' : '' ?>" id="edit_aadhaar" name="aadhaar_number" value="<?= e($eVal('aadhaar_number', $selectedPartner['aadhaar_number'])) ?>" maxlength="20">
                            <?php if ($isEditPartnerError && isset($partnerErrors['aadhaar_number'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['aadhaar_number']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_status">Status *</label>
                            <select class="form-select <?= ($isEditPartnerError && isset($partnerErrors['status'])) ? 'is-invalid' : '' ?>" id="edit_status" name="status" required>
                                <option value="active" <?= $eVal('status', $selectedPartner['status']) === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $eVal('status', $selectedPartner['status']) === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                                <option value="cancelled" <?= $eVal('status', $selectedPartner['status']) === 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                            <?php if ($isEditPartnerError && isset($partnerErrors['status'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['status']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12">
                            <label class="form-label" for="edit_address">Address</label>
                            <textarea class="form-control <?= ($isEditPartnerError && isset($partnerErrors['address'])) ? 'is-invalid' : '' ?>" id="edit_address" name="address" rows="2"><?= e($eVal('address', $selectedPartner['address'])) ?></textarea>
                            <?php if ($isEditPartnerError && isset($partnerErrors['address'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['address']) ?></div>
                            <?php endif; ?>
                        </div>

                        <?php $primaryNominee = $nominees[0] ?? null; ?>
                        <input type="hidden" name="nominee_id" value="<?= e($primaryNominee['id'] ?? 0) ?>">
                        <div class="col-12"><hr class="my-1"><div class="d-flex align-items-center justify-content-between"><strong class="small text-uppercase text-danger"><i class="bi bi-person-hearts me-1"></i>Partner Nominee Details</strong><span class="badge text-bg-light border text-muted small">Primary Nominee</span></div></div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit_nom_name">Nominee Name</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['nominee_name'])) ? 'is-invalid' : '' ?>" id="edit_nom_name" name="nominee_name" value="<?= e($eVal('nominee_name', $primaryNominee['nominee_name'] ?? '')) ?>" placeholder="Full name of designated nominee">
                            <?php if ($isEditPartnerError && isset($partnerErrors['nominee_name'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_name']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label" for="edit_nom_rel">Relationship</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['nominee_relationship'])) ? 'is-invalid' : '' ?>" id="edit_nom_rel" name="nominee_relationship" value="<?= e($eVal('nominee_relationship', $primaryNominee['relationship'] ?? '')) ?>" placeholder="e.g. Spouse, Son, Daughter, Brother">
                            <?php if ($isEditPartnerError && isset($partnerErrors['nominee_relationship'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_relationship']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_nom_phone">Phone Number</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['nominee_phone'])) ? 'is-invalid' : '' ?>" id="edit_nom_phone" name="nominee_phone" value="<?= e($eVal('nominee_phone', $primaryNominee['phone'] ?? '')) ?>" placeholder="Mobile / contact number">
                            <?php if ($isEditPartnerError && isset($partnerErrors['nominee_phone'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_phone']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_nom_aadhaar">Aadhaar Number</label>
                            <input class="form-control font-monospace <?= ($isEditPartnerError && isset($partnerErrors['nominee_aadhaar'])) ? 'is-invalid' : '' ?>" id="edit_nom_aadhaar" name="nominee_aadhaar" value="<?= e($eVal('nominee_aadhaar', $primaryNominee['aadhaar_number'] ?? '')) ?>" maxlength="20" placeholder="12-digit Aadhaar number">
                            <?php if ($isEditPartnerError && isset($partnerErrors['nominee_aadhaar'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_aadhaar']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_nom_dob">Date of Birth</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['nominee_dob'])) ? 'is-invalid' : '' ?>" id="edit_nom_dob" name="nominee_dob" type="date" value="<?= e($eVal('nominee_dob', $primaryNominee['date_of_birth'] ?? '')) ?>">
                            <?php if ($isEditPartnerError && isset($partnerErrors['nominee_dob'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_dob']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_nom_allocation">Nominee Share / Allocation (%)</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['nominee_allocation'])) ? 'is-invalid' : '' ?>" id="edit_nom_allocation" name="nominee_allocation" type="number" step="0.001" value="<?= e($eVal('nominee_allocation', $primaryNominee['allocation_percent'] ?? 100.00)) ?>">
                            <?php if ($isEditPartnerError && isset($partnerErrors['nominee_allocation'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_allocation']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_nom_minor">Is Minor?</label>
                            <select class="form-select <?= ($isEditPartnerError && isset($partnerErrors['nominee_minor'])) ? 'is-invalid' : '' ?>" id="edit_nom_minor" name="nominee_minor" onchange="toggleEditNomineeGuardian(this.value)">
                                <option value="0" <?= (string)$eVal('nominee_minor', empty($primaryNominee['is_minor']) ? '0' : '1') === '0' ? 'selected' : '' ?>>No (Major)</option>
                                <option value="1" <?= (string)$eVal('nominee_minor', empty($primaryNominee['is_minor']) ? '0' : '1') === '1' ? 'selected' : '' ?>>Yes (Minor)</option>
                            </select>
                            <?php if ($isEditPartnerError && isset($partnerErrors['nominee_minor'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_minor']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label" for="edit_nom_status">Nominee Status</label>
                            <select class="form-select <?= ($isEditPartnerError && isset($partnerErrors['nominee_status'])) ? 'is-invalid' : '' ?>" id="edit_nom_status" name="nominee_status">
                                <option value="active" <?= $eVal('nominee_status', $primaryNominee['status'] ?? 'active') === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= $eVal('nominee_status', $primaryNominee['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                            <?php if ($isEditPartnerError && isset($partnerErrors['nominee_status'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_status']) ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12" id="edit_nom_guardian_wrap" style="<?= ((string)$eVal('nominee_minor', empty($primaryNominee['is_minor']) ? '0' : '1') === '1' || ($isEditPartnerError && isset($partnerErrors['nominee_guardian']))) ? 'block' : 'none' ?>;">
                            <label class="form-label" for="edit_nom_guardian">Guardian Name (Required if Minor)</label>
                            <input class="form-control <?= ($isEditPartnerError && isset($partnerErrors['nominee_guardian'])) ? 'is-invalid' : '' ?>" id="edit_nom_guardian" name="nominee_guardian" value="<?= e($eVal('nominee_guardian', $primaryNominee['guardian_name'] ?? '')) ?>" placeholder="Legal guardian full name">
                            <?php if ($isEditPartnerError && isset($partnerErrors['nominee_guardian'])): ?>
                                <div class="invalid-feedback"><?= e($partnerErrors['nominee_guardian']) ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger"><i class="bi bi-check2 me-1"></i>Update Partner</button>
                </div>
            </form>
    </div>
</div>

<!-- Modal 3: Record Partner Transaction Modal -->
<div class="modal fade" id="recordTransactionModal" tabindex="-1" aria-labelledby="recordTransactionModalLabel" aria-hidden="true" data-auto-open="<?= $isTransactionError ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" action="<?= e(url('module.save')) ?>" method="post" novalidate data-prevent-double-submit>
            <?= Csrf::field() ?>
            <input type="hidden" name="_module" value="partner_transactions">
            <input type="hidden" name="_return_to" value="<?= e(url('partners', ['id' => $selectedPartner['id']])) ?>">
            <input type="hidden" name="id" value="0">
            <input type="hidden" name="shareholder_id" value="<?= e($selectedPartner['id']) ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Partner: <?= e($selectedPartner['name']) ?> (<?= e($selectedPartner['shareholder_code']) ?>)</p>
                    <h4 class="modal-title text-success" id="recordTransactionModalLabel"><i class="bi bi-cash-stack me-2"></i>Record Partner Transaction</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="alert alert-info py-2 small">
                    <strong>Current Share Status:</strong> Total Commitment: <strong><?= money($selectedPartner['share_amount']) ?></strong> | Received: <strong><?= money($selectedPartner['received_share']) ?></strong> | Remaining Pending: <strong><?= money($selectedPartner['pending_share_amount']) ?></strong>
                </div>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="tx_date">Transaction Date *</label>
                        <input class="form-control" id="tx_date" name="transaction_date" type="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tx_type">Transaction Type *</label>
                        <select class="form-select" id="tx_type" name="transaction_type" required>
                            <option value="installment" selected>Installment Receipt (Credit)</option>
                            <option value="share_addition">Share Addition (Credit)</option>
                            <option value="share_deduction">Share Deduction (Debit)</option>
                            <option value="refund">Refund (Debit)</option>
                            <option value="cancellation_settlement">Cancellation Settlement (Debit)</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tx_amount">Amount (INR) *</label>
                        <input class="form-control" id="tx_amount" name="amount" type="number" step="0.01" placeholder="0.00" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tx_share_qty">Share Quantity</label>
                        <input class="form-control" id="tx_share_qty" name="share_quantity" type="number" step="0.001" value="0.000">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tx_payment_method">Payment Mode *</label>
                        <select class="form-select" id="tx_payment_method" name="payment_method" required>
                            <option value="bank_transfer" selected>Bank Transfer (NEFT / RTGS / IMPS)</option>
                            <option value="upi">UPI</option>
                            <option value="cheque">Cheque</option>
                            <option value="card">Card</option>
                            <option value="cash">Cash</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="tx_bank">Company Bank / Cash Account</label>
                        <select class="form-select" id="tx_bank" name="company_bank_account_id">
                            <option value="">— Select receiving/paying account —</option>
                            <?php foreach ($bankAccounts as $b): ?>
                                <option value="<?= e($b['id']) ?>"><?= e($b['bank_name']) ?> (<?= e($b['masked_account']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label" for="tx_ref">Payment Reference / UTR / Cheque Number</label>
                        <input class="form-control font-monospace" id="tx_ref" name="reference_number" placeholder="e.g. UTR12345678 or Cheque #">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="tx_notes">Transaction Notes</label>
                        <textarea class="form-control" id="tx_notes" name="notes" rows="2" placeholder="Audit remarks or bank receipt note"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-success"><i class="bi bi-check2 me-1"></i>Save Transaction</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 4: Declare Partner Dividend Modal -->
<div class="modal fade" id="declareDividendModal" tabindex="-1" aria-labelledby="declareDividendModalLabel" aria-hidden="true" data-auto-open="<?= $isDividendError ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" action="<?= e(url('module.save')) ?>" method="post" novalidate data-prevent-double-submit id="dividendForm">
            <?= Csrf::field() ?>
            <input type="hidden" name="_module" value="partner_dividends">
            <input type="hidden" name="_return_to" value="<?= e(url('partners', ['id' => $selectedPartner['id']])) ?>">
            <input type="hidden" name="id" value="0">
            <input type="hidden" name="shareholder_id" value="<?= e($selectedPartner['id']) ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Partner: <?= e($selectedPartner['name']) ?></p>
                    <h4 class="modal-title text-primary" id="declareDividendModalLabel"><i class="bi bi-pie-chart-fill me-2"></i>Declare Partner Dividend</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="div_fy">Financial Year *</label>
                        <input class="form-control" id="div_fy" name="financial_year" value="<?= (date('m') >= 4 ? date('Y') . '-' . (date('y') + 1) : (date('Y') - 1) . '-' . date('y')) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="div_date">Declaration Date *</label>
                        <input class="form-control" id="div_date" name="declaration_date" type="date" value="<?= date('Y-m-d') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="div_basis">Share-Value Basis (INR) *</label>
                        <input class="form-control" id="div_basis" name="share_value_basis" type="number" step="0.01" value="<?= e($selectedPartner['share_amount']) ?>" required oninput="calcDividend()">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="div_rate">Dividend Rate (%) *</label>
                        <input class="form-control" id="div_rate" name="dividend_rate" type="number" step="0.001" placeholder="e.g. 5.0" required oninput="calcDividend()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Calculated Gross Dividend</label>
                        <input class="form-control bg-light fw-bold" id="div_gross_preview" value="0.00" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="div_tds">TDS Amount (INR)</label>
                        <input class="form-control" id="div_tds" name="tds_amount" type="number" step="0.01" value="0.00" oninput="calcDividend()">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Calculated Net Dividend</label>
                        <input class="form-control bg-light fw-bold text-success" id="div_net_preview" value="0.00" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="div_bank">Paying Bank Account</label>
                        <select class="form-select" id="div_bank" name="company_bank_account_id">
                            <option value="">— Select company account —</option>
                            <?php foreach ($bankAccounts as $b): ?>
                                <option value="<?= e($b['id']) ?>"><?= e($b['bank_name']) ?> (<?= e($b['masked_account']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="div_ref">Payment Reference</label>
                        <input class="form-control font-monospace" id="div_ref" name="reference_number" placeholder="Payment ref / UTR">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1"></i>Declare Dividend</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal 5: Add Nominee Modal -->
<div class="modal fade" id="addNomineeModal" tabindex="-1" aria-labelledby="addNomineeModalLabel" aria-hidden="true" data-auto-open="<?= $isNomineeError ? 'true' : 'false' ?>">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <form class="modal-content" action="<?= e(url('module.save')) ?>" method="post" novalidate data-prevent-double-submit>
            <?= Csrf::field() ?>
            <input type="hidden" name="_module" value="shareholder_nominees">
            <input type="hidden" name="_return_to" value="<?= e(url('partners', ['id' => $selectedPartner['id']])) ?>">
            <input type="hidden" name="id" value="0">
            <input type="hidden" name="shareholder_id" value="<?= e($selectedPartner['id']) ?>">
            <div class="modal-header">
                <div>
                    <p class="eyebrow mb-1">Partner: <?= e($selectedPartner['name']) ?></p>
                    <h4 class="modal-title text-secondary" id="addNomineeModalLabel"><i class="bi bi-person-hearts me-2"></i>Add Partner Nominee</h4>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label" for="nom_name">Nominee Name *</label>
                        <input class="form-control" id="nom_name" name="nominee_name" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nom_rel">Relationship *</label>
                        <input class="form-control" id="nom_rel" name="relationship" placeholder="e.g. Spouse, Son, Daughter, Brother" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nom_phone">Phone Number</label>
                        <input class="form-control" id="nom_phone" name="phone">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="nom_aadhaar">Aadhaar Number</label>
                        <input class="form-control font-monospace" id="nom_aadhaar" name="aadhaar_number" maxlength="20">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="nom_dob">Date of Birth</label>
                        <input class="form-control" id="nom_dob" name="date_of_birth" type="date">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="nom_allocation">Nominee Share / Allocation (%) *</label>
                        <input class="form-control" id="nom_allocation" name="allocation_percent" type="number" step="0.001" value="100.00" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="nom_minor">Is Minor?</label>
                        <select class="form-select" id="nom_minor" name="is_minor" onchange="toggleGuardian(this.value)">
                            <option value="0" selected>No (Major)</option>
                            <option value="1">Yes (Minor)</option>
                        </select>
                    </div>
                    <div class="col-md-8" id="guardian_wrap" style="display: none;">
                        <label class="form-label" for="nom_guardian">Guardian Name (Required if Minor)</label>
                        <input class="form-control" id="nom_guardian" name="guardian_name">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label" for="nom_status">Nominee Status *</label>
                        <select class="form-select" id="nom_status" name="status" required>
                            <option value="active" selected>Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-secondary"><i class="bi bi-check2 me-1"></i>Save Nominee</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>
<?php endif; ?>

<style>
.badge-pill-stat {
    background: #fff;
    border: 1px solid #e4e7ef;
    border-radius: 20px;
    padding: 6px 14px;
    font-size: 13px;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.02);
}
.partner-hero {
    background: linear-gradient(135deg, #ffffff 0%, #fafbfc 100%);
    border: 1px solid #e4e7ef;
    border-radius: 16px;
    padding: 24px;
    position: relative;
    box-shadow: 0 10px 30px rgba(26,32,48,0.04);
}
.partner-avatar {
    width: 64px;
    height: 64px;
    border-radius: 14px;
    background: #fff0f3;
    color: #d71938;
    display: grid;
    place-items: center;
    font-size: 32px;
    border: 1px solid #f9d2d9;
    flex-shrink: 0;
}
.stat-card {
    background: #fff;
    border: 1px solid #e9ecef;
    border-radius: 12px;
    padding: 14px;
    display: flex;
    flex-direction: column;
    height: 100%;
    box-shadow: 0 2px 6px rgba(0,0,0,0.02);
}
.stat-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #6c757d;
    margin-bottom: 4px;
}
.stat-value {
    font-size: 18px;
    line-height: 1.2;
    margin-bottom: 2px;
}
.field-display {
    background: #fdfdfd;
    padding: 10px 14px;
    border-radius: 10px;
    border: 1px solid #f1f3f5;
    height: 100%;
}
.field-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: #868e96;
    margin-bottom: 4px;
}
.field-value {
    font-size: 14px;
    color: #212529;
}
</style>

<script>
function toggleGuardian(val) {
    var el = document.getElementById('guardian_wrap');
    if (el) el.style.display = val === '1' ? 'block' : 'none';
}
function toggleNewNomineeGuardian(val) {
    var el = document.getElementById('new_nom_guardian_wrap');
    if (el) el.style.display = val === '1' ? 'block' : 'none';
}
function toggleEditNomineeGuardian(val) {
    var el = document.getElementById('edit_nom_guardian_wrap');
    if (el) el.style.display = val === '1' ? 'block' : 'none';
}
function calcDividend() {
    var basis = parseFloat(document.getElementById('div_basis').value) || 0;
    var rate = parseFloat(document.getElementById('div_rate').value) || 0;
    var tds = parseFloat(document.getElementById('div_tds').value) || 0;
    var gross = (basis * rate) / 100;
    var net = Math.max(0, gross - tds);
    document.getElementById('div_gross_preview').value = gross.toFixed(2);
    document.getElementById('div_net_preview').value = net.toFixed(2);
}
</script>
