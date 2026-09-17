<?php use MeatinOS\Core\Csrf; ?>
<div class="settings-hero card-panel">
    <div><p class="eyebrow">Administration</p><h2>System Settings</h2><p>India operating defaults and secure integrations for MeatinOS.</p></div>
    <span class="badge rounded-pill text-bg-light"><i class="bi bi-geo-alt me-1"></i>India · INR · Asia/Kolkata</span>
</div>

<div class="row g-4">
    <div class="col-xl-7"><article class="card-panel settings-card">
        <div class="panel-heading"><div><p class="eyebrow">Business defaults</p><h2>India Operations</h2><p>These values drive currency, tax labels, FEFO and operational timing.</p></div><i class="bi bi-buildings"></i></div>
        <form method="post" action="<?= e(url('settings.save')) ?>" class="row g-3"><?= Csrf::field() ?>
            <div class="col-md-8"><label class="form-label">Company name</label><input class="form-control" name="company_name" value="<?= e($settings['company_name'] ?? 'Meatin Farms and Foods LLP') ?>" <?= $canManage ? '' : 'disabled' ?>></div>
            <div class="col-md-4"><label class="form-label">Country</label><input class="form-control" name="country" value="India" readonly></div>
            <div class="col-md-4"><label class="form-label">Currency</label><input class="form-control" name="currency" value="INR" readonly><div class="form-text">Indian rupee (₹)</div></div>
            <div class="col-md-5"><label class="form-label">Timezone</label><input class="form-control" name="timezone" value="Asia/Kolkata" readonly></div>
            <div class="col-md-3"><label class="form-label">GST rate (%)</label><input class="form-control" type="number" min="0" max="100" step="0.01" name="gst_rate_percent" value="<?= e($settings['gst_rate_percent'] ?? 5) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
            <div class="col-md-6"><label class="form-label">Lot allocation</label><select class="form-select" name="lot_strategy" <?= $canManage ? '' : 'disabled' ?>><option value="FEFO" <?= ($settings['lot_strategy'] ?? '') === 'FEFO' ? 'selected' : '' ?>>FEFO — earliest expiry first</option><option value="FIFO" <?= ($settings['lot_strategy'] ?? '') === 'FIFO' ? 'selected' : '' ?>>FIFO — oldest receipt first</option></select></div>
            <div class="col-md-6"><label class="form-label">Temperature alert delay (minutes)</label><input class="form-control" type="number" min="0" max="120" name="temperature_alert_delay_minutes" value="<?= e($settings['temperature_alert_delay_minutes'] ?? 5) ?>" <?= $canManage ? '' : 'disabled' ?>></div>
            <div class="col-md-6"><label class="form-label">Storage door alert threshold (minutes)</label><input class="form-control" type="number" min="0" max="60" step="0.5" name="storage_door_alert_minutes" value="<?= e($settings['storage_door_alert_minutes'] ?? 2) ?>" <?= $canManage ? '' : 'disabled' ?>><div class="form-text">Create an alert when a recorded door-open duration reaches this limit.</div></div>
            <?php if ($canManage): ?><div class="col-12"><button class="btn btn-danger" type="submit"><i class="bi bi-check2 me-2"></i>Save operating settings</button></div><?php endif; ?>
        </form>
    </article></div>
    <div class="col-xl-5"><article class="card-panel settings-card ai-config-card" id="ai-settings">
        <div class="ai-config-head"><span class="ai-orb"><i class="bi bi-stars"></i></span><div><p class="eyebrow">Optional integration</p><h2>OpenAI for Meatin AI</h2><p>The key is encrypted at rest and is never displayed again.</p></div><span class="status-pill <?= $aiSettings['enabled'] && $aiSettings['configured'] ? 'success' : 'secondary' ?>"><?= $aiSettings['enabled'] && $aiSettings['configured'] ? 'Enabled' : 'Disabled' ?></span></div>
        <?php if ($canManageAi): ?>
        <form method="post" action="<?= e(url('settings.ai.save')) ?>" class="row g-3 mt-1" autocomplete="off"><?= Csrf::field() ?>
            <div class="col-12"><label class="form-label">OpenAI API key</label><div class="input-group"><span class="input-group-text"><i class="bi bi-key"></i></span><input class="form-control" type="password" name="api_key" placeholder="<?= $aiSettings['configured'] ? 'Configured · ending ' . e($aiSettings['key_last4']) . ' · leave blank to keep' : 'Paste key here; it will not be shown again' ?>" autocomplete="new-password" spellcheck="false"></div><div class="form-text">Not included in the cPanel package. Enter it only after deployment.</div></div>
            <div class="col-md-7"><label class="form-label">Model</label><input class="form-control" name="model" value="<?= e($aiSettings['model'] ?? 'gpt-5.6') ?>" required></div>
            <div class="col-md-5"><label class="form-label">Max output tokens</label><input class="form-control" type="number" min="100" max="8000" name="max_output_tokens" value="<?= e($aiSettings['max_output_tokens'] ?? 900) ?>"></div>
            <div class="col-md-7"><label class="form-label">Monthly control budget (₹)</label><input class="form-control" type="number" min="0" max="10000000" step="100" name="monthly_budget_inr" value="<?= e($aiSettings['monthly_budget_inr'] ?? 5000) ?>"></div>
            <div class="col-md-5"><label class="form-label">Audit retention (days)</label><input class="form-control" type="number" min="7" max="365" name="retention_days" value="<?= e($aiSettings['retention_days'] ?? 90) ?>"></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" id="aiEnabled" name="enabled" value="1" <?= $aiSettings['enabled'] ? 'checked' : '' ?>><label class="form-check-label" for="aiEnabled">Enable role-aware AI workspace</label></div></div>
            <div class="col-12"><button class="btn btn-danger" type="submit"><i class="bi bi-shield-lock me-2"></i>Save securely</button></div>
        </form>
        <div class="d-flex flex-wrap gap-2 mt-3"><form method="post" action="<?= e(url('settings.ai.test')) ?>"><?= Csrf::field() ?><button class="btn btn-outline-secondary btn-sm" type="submit" <?= $aiSettings['configured'] ? '' : 'disabled' ?>><i class="bi bi-plug me-1"></i>Test connection</button></form><?php if ($aiSettings['configured']): ?><form method="post" action="<?= e(url('settings.ai.remove')) ?>" onsubmit="return confirm('Remove the stored API key and disable AI?')"><?= Csrf::field() ?><button class="btn btn-outline-danger btn-sm" type="submit"><i class="bi bi-trash3 me-1"></i>Remove key</button></form><?php endif; ?></div>
        <?php else: ?><div class="alert alert-secondary mb-0 mt-3"><i class="bi bi-lock me-2"></i>Only an administrator with AI configuration permission can manage this integration.</div><?php endif; ?>
        <div class="ai-boundary"><i class="bi bi-shield-check"></i><span><strong>Advisory boundary</strong>AI reads only data the signed-in user may access. It cannot post, approve, delete, pay, dispatch, change inventory or update payroll.</span></div>
    </article></div>
</div>

<article class="card-panel settings-card mt-4"><div class="panel-heading mb-0"><div><h2>Production Readiness</h2><p>Deployment controls to complete on the cPanel host</p></div></div><div class="readiness-grid"><span><i class="bi bi-check-circle-fill"></i> HTTPS and secure cookies</span><span><i class="bi bi-check-circle-fill"></i> Dedicated least-privilege MySQL user</span><span><i class="bi bi-check-circle-fill"></i> Daily encrypted database backups</span><span><i class="bi bi-check-circle-fill"></i> Scheduled background worker</span><span><i class="bi bi-check-circle-fill"></i> APP_KEY generated by installer</span><span><i class="bi bi-check-circle-fill"></i> API key stored encrypted</span></div></article>
