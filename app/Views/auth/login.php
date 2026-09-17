<?php use MeatinOS\Core\Csrf; $flashes = pull_flashes(); ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Sign in to MeatinOS">
    <title>Sign in | MeatinOS</title>
    <link rel="icon" type="image/png" href="<?= e(asset('img/meatin-logo.png')) ?>">
    <link rel="stylesheet" href="vendor/bootstrap/css/bootstrap.min.css">
    <link rel="stylesheet" href="vendor/bootstrap-icons/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
</head>
<body class="login-body">
<main class="login-shell">
    <section class="login-brand">
        <div class="login-brand-content">
            <img src="<?= e(asset('img/meatin-logo.png')) ?>" alt="Meatin">
            <p class="eyebrow">Farm to customer intelligence</p>
            <h1>Every batch. Every handoff. Fully traceable.</h1>
            <p>One secure operating system for production, quality, cold storage, sales, finance, people, maintenance, hygiene, fleet, and IoT.</p>
            <div class="login-feature-grid">
                <span><i class="bi bi-shield-check"></i> HACCP-ready controls</span>
                <span><i class="bi bi-snow2"></i> Cold-chain visibility</span>
                <span><i class="bi bi-upc-scan"></i> Lot traceability</span>
                <span><i class="bi bi-graph-up-arrow"></i> Live management KPIs</span>
            </div>
        </div>
    </section>
    <section class="login-form-panel">
        <div class="login-form-wrap">
            <div class="d-lg-none text-center mb-4"><img class="login-mobile-logo" src="<?= e(asset('img/meatin-logo.png')) ?>" alt="Meatin"></div>
            <p class="eyebrow text-danger">MeatinOS secure access</p>
            <h2>Welcome back</h2>
            <p class="text-secondary mb-4">Sign in with your assigned Meatin account.</p>
            <?php foreach ($flashes as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
            <form action="<?= e(url('login')) ?>" method="post" class="needs-validation" novalidate>
                <?= Csrf::field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email address</label>
                    <div class="input-group input-group-lg"><span class="input-group-text"><i class="bi bi-envelope"></i></span><input class="form-control" id="email" name="email" type="email" value="<?= e(old('email')) ?>" autocomplete="username" required></div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group input-group-lg"><span class="input-group-text"><i class="bi bi-lock"></i></span><input class="form-control" id="password" name="password" type="password" autocomplete="current-password" required><button class="btn btn-outline-secondary password-toggle" type="button" aria-label="Show password"><i class="bi bi-eye"></i></button></div>
                </div>
                <button class="btn btn-danger btn-lg w-100 mt-2" type="submit">Sign in to MeatinOS <i class="bi bi-arrow-right ms-2"></i></button>
            </form>
            <p class="login-footnote"><i class="bi bi-shield-lock"></i> Protected by session security, rate limiting, role permissions, and audit logging.</p>
        </div>
    </section>
</main>
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
