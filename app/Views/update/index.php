<?php

use MeatinOS\Core\Csrf;

$pendingCount = count($status['pending']);
$appliedCount = count($status['applied']);
$isCurrent = $pendingCount === 0;
?>
<section class="update-hero card-panel">
    <div>
        <p class="eyebrow">cPanel release manager</p>
        <h2><?= $isCurrent ? 'MeatinOS is up to date' : e($pendingCount . ' database update' . ($pendingCount === 1 ? '' : 's') . ' ready') ?></h2>
        <p>Apply only pending, ordered SQL migrations after the new release files have been uploaded.</p>
    </div>
    <span class="status-pill <?= $isCurrent ? 'success' : 'warning' ?>">
        <i class="bi <?= $isCurrent ? 'bi-check-circle-fill' : 'bi-arrow-up-circle-fill' ?>"></i>
        <?= $isCurrent ? 'Current' : e($pendingCount . ' pending') ?>
    </span>
</section>

<div class="update-summary-grid">
    <article class="card-panel update-summary-card"><span>Installed migration</span><strong><?= e($status['current'] ?? 'Base schema') ?></strong></article>
    <article class="card-panel update-summary-card"><span>Bundled migration</span><strong><?= e($status['latest'] ?? 'None') ?></strong></article>
    <article class="card-panel update-summary-card"><span>Migration history</span><strong><?= e($appliedCount) ?> / <?= e($status['total']) ?></strong></article>
</div>

<?php if ($status['unknown_applied']): ?>
    <div class="alert alert-warning app-alert" role="alert"><strong>Package mismatch:</strong> the database contains migration records that are not present in this release. Upload the correct or newer release before continuing.</div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-xl-8">
        <section class="card-panel update-panel">
            <div class="panel-heading">
                <div><h2>Pending migrations</h2><p>Files are executed once, in filename order, and recorded in the schema history.</p></div>
            </div>
            <?php if (!$status['pending']): ?>
                <div class="update-empty"><i class="bi bi-shield-check"></i><div><strong>No database changes are pending</strong><span>All <?= e($status['total']) ?> bundled migrations are recorded as applied.</span></div></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table data-table align-middle mb-0">
                        <thead><tr><th>Migration</th><th>Size</th><th>SHA-256</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php foreach ($status['pending'] as $migration): ?>
                            <tr>
                                <td><strong><?= e($migration['name']) ?></strong></td>
                                <td><?= e(number_format($migration['size'] / 1024, 1)) ?> KB</td>
                                <td><code class="migration-hash" title="<?= e($migration['sha256']) ?>"><?= e(substr($migration['sha256'], 0, 16)) ?>…</code></td>
                                <td><span class="status-pill warning">Pending</span></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
    <div class="col-xl-4">
        <aside class="card-panel update-panel">
            <div class="panel-heading"><div><h2>Pre-update checks</h2><p>The database connection is active.</p></div></div>
            <div class="update-checks">
                <?php foreach ($checks as $check): ?>
                    <span class="<?= $check['passed'] ? 'passed' : 'failed' ?>"><i class="bi <?= $check['passed'] ? 'bi-check-circle-fill' : 'bi-x-circle-fill' ?>"></i><?= e($check['label']) ?></span>
                <?php endforeach; ?>
            </div>
            <?php if ($status['pending']): ?>
                <form method="post" action="<?= e(url('system.update.apply')) ?>" class="update-form">
                    <?= Csrf::field() ?>
                    <label class="form-check update-confirm">
                        <input class="form-check-input" type="checkbox" name="backup_confirmed" value="1" required>
                        <span class="form-check-label">I have a current database and application-file backup.</span>
                    </label>
                    <button class="btn btn-primary w-100" type="submit" <?= !$ready || $status['unknown_applied'] ? 'disabled' : '' ?>><i class="bi bi-database-up me-2"></i>Apply <?= e($pendingCount) ?> migration<?= $pendingCount === 1 ? '' : 's' ?></button>
                </form>
            <?php endif; ?>
        </aside>
        <aside class="card-panel update-note mt-4">
            <i class="bi bi-info-circle"></i>
            <div><strong>Before you continue</strong><p>Use cPanel File Manager to upload the new release while preserving <code>.env</code> and <code>storage/installed.lock</code>. Run this updater during a maintenance window. MySQL schema changes can auto-commit; restore the verified backup if an update fails.</p></div>
        </aside>
    </div>
</div>
