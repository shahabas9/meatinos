<?php

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'MeatinOS\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, strlen($prefix)));
    $path = BASE_PATH . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . $relative . '.php';
    if (is_file($path)) {
        require $path;
    }
});

require BASE_PATH . '/app/Support.php';

load_env(BASE_PATH . '/.env');
$GLOBALS['config'] = [
    'app' => require BASE_PATH . '/config/app.php',
    'database' => require BASE_PATH . '/config/database.php',
    'modules' => require BASE_PATH . '/config/modules.php',
];

date_default_timezone_set((string) config('app.timezone', 'Asia/Kolkata'));

if (PHP_SAPI !== 'cli' && session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (bool) config('app.force_https', false) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name((string) config('app.session.name', 'meatinos_session'));
    session_set_cookie_params([
        'lifetime' => (int) config('app.session.lifetime', 120) * 60,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();
}

if (PHP_SAPI !== 'cli') {
    header_remove('X-Powered-By');
    header('Content-Type: text/html; charset=UTF-8');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' 'unsafe-inline'; script-src 'self'; font-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    if ((bool) config('app.force_https', false) || (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

set_exception_handler(static function (Throwable $exception): void {
    $log = sprintf("[%s] %s in %s:%d\n%s\n", date('c'), $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString());
    error_log($log, 3, BASE_PATH . '/storage/logs/app.log');
    $exceptionCode = (int) $exception->getCode();
    $status = $exceptionCode >= 400 && $exceptionCode <= 599 ? $exceptionCode : 500;
    http_response_code($status);
    if (config('app.debug', false)) {
        echo '<pre>' . htmlspecialchars($log, ENT_QUOTES, 'UTF-8') . '</pre>';
        return;
    }
    if (in_array($status, [403, 404, 422], true)) {
        echo htmlspecialchars($exception->getMessage(), ENT_QUOTES, 'UTF-8');
        return;
    }
    echo 'An unexpected error occurred. Reference: ' . date('YmdHis');
});
