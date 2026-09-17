<aside class="sidebar d-none d-lg-flex">
    <div class="brand-panel">
        <img src="<?= e(asset('img/meatin-logo.png')) ?>" alt="Meatin">
        <div><strong>MeatinOS</strong><span>ERP & Plant Intelligence</span></div>
    </div>
    <?php require BASE_PATH . '/app/Views/partials/sidebar-nav.php'; ?>
    <div class="sidebar-profile">
        <span class="avatar avatar-light"><?= e(mb_strtoupper(mb_substr((string) $user['name'], 0, 1))) ?></span>
        <div class="min-w-0"><strong class="text-truncate d-block"><?= e($user['name']) ?></strong><span><?= e($user['role_name']) ?></span><small><i class="bi bi-circle-fill"></i> Online</small></div>
    </div>
</aside>

