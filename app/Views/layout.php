<?php

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;

$currentRoute = (string) ($_GET['route'] ?? 'dashboard');
$currentModule = (string) ($_GET['name'] ?? '');
$user = Auth::user();
$searchModule = $currentModule ?: match ($user['role_slug'] ?? '') {
    'customer' => 'sales_orders',
    'driver' => 'dispatches',
    default => 'production_batches',
};
$flashes = pull_flashes();
$openAlertCount = Auth::can('iot.view') ? (int) Database::connection()->query("SELECT COUNT(*) FROM alerts WHERE status='open'")->fetchColumn() : 0;
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="MeatinOS - poultry processing ERP, MES, WMS, CRM and operational intelligence platform">
    <title><?= e($title ?? 'MeatinOS') ?> | MeatinOS</title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/meatin-logo.png')) ?>">
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body>
<div class="app-shell">
    <?php require BASE_PATH . '/app/Views/partials/sidebar.php'; ?>
    <div class="app-main">
        <header class="topbar">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <button class="btn btn-icon d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-label="Open navigation"><i class="bi bi-list"></i></button>
                <div class="min-w-0">
                    <h1 class="page-title text-truncate"><?= e($title ?? 'Dashboard') ?></h1>
                    <div class="page-subtitle d-none d-sm-block">Welcome back, <?= e(explode(' ', (string) $user['name'])[0] ?? 'User') ?>. Here is what is happening at Meatin.</div>
                </div>
            </div>
            <div class="topbar-actions">
                <form class="global-search d-none d-md-flex <?= !empty($_GET['q']) ? 'has-query' : '' ?>" action="" method="get" role="search" novalidate>
                    <input type="hidden" name="route" value="search">
                    <i class="bi bi-search"></i>
                    <input type="search" name="q" value="<?= e((string) ($_GET['q'] ?? '')) ?>" placeholder="Search batches, orders, people..." aria-label="Global search" minlength="2">
                    <button class="search-clear" type="button" data-clear-search aria-label="Clear global search"><i class="bi bi-x-lg"></i></button>
                    <button class="global-search-submit" type="submit" aria-label="Run global search"><i class="bi bi-arrow-right"></i></button>
                </form>
                <?php if (Auth::can('iot.view')): ?>
                    <a class="btn btn-icon position-relative" href="<?= e(url('module', ['name' => 'alerts'])) ?>" aria-label="Alerts"><i class="bi bi-bell"></i><?php if ($openAlertCount): ?><span class="notification-dot"><?= e(min(99, $openAlertCount)) ?></span><?php endif; ?></a>
                <?php endif; ?>
                <button class="btn btn-icon" id="themeToggle" type="button" aria-label="Toggle dark mode"><i class="bi bi-moon-stars"></i></button>
                <div class="dropdown">
                    <button class="user-chip dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar"><?= e(mb_strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span>
                        <span class="d-none d-xl-block text-start"><strong><?= e($user['name']) ?></strong><small><?= e($user['role_name']) ?></small></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                        <li><a class="dropdown-item" href="<?= e(url('password')) ?>"><i class="bi bi-shield-lock me-2"></i>Security</a></li>
                        <?php if (Auth::can('users.view')): ?><li><a class="dropdown-item" href="<?= e(url('users')) ?>"><i class="bi bi-people me-2"></i>Users</a></li><?php endif; ?>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <form action="<?= e(url('logout')) ?>" method="post">
                                <?= Csrf::field() ?>
                                <button class="dropdown-item text-danger" type="submit"><i class="bi bi-box-arrow-right me-2"></i>Sign out</button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <main class="content-area">
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> alert-dismissible fade show app-alert" role="alert">
                    <?= e($flash['message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endforeach; ?>
            <?= $content ?>
        </main>
    </div>
</div>

<div class="offcanvas offcanvas-start" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header sidebar-mobile-header">
        <img src="<?= e(asset('img/meatin-logo.png')) ?>" alt="Meatin">
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body p-0"><?php require BASE_PATH . '/app/Views/partials/sidebar-nav.php'; ?></div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header"><h2 class="modal-title fs-5" id="deleteConfirmTitle">Delete this entry?</h2><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">This cannot be undone. The deletion will remain in the audit trail.</div>
            <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button type="button" class="btn btn-danger" data-confirm-delete-action>Delete entry</button></div>
        </div>
    </div>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/chartjs/chart.umd.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
