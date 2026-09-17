<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use MeatinOS\Core\Database;

$checks = [];
$check = static function (string $name, bool $ok, string $detail) use (&$checks): void {
    $checks[] = ['name' => $name, 'ok' => $ok, 'detail' => $detail];
};

$check('PHP runtime', version_compare(PHP_VERSION, '8.2.0', '>='), 'PHP ' . PHP_VERSION . ' (8.2+ required)');
foreach (['pdo_mysql', 'mbstring', 'openssl', 'json', 'fileinfo', 'curl'] as $extension) {
    $check('Extension ' . $extension, extension_loaded($extension), extension_loaded($extension) ? 'loaded' : 'missing');
}
$check('Production environment', config('app.env') === 'production', 'APP_ENV=' . config('app.env'));
$check('Debug disabled', config('app.debug') === false, 'APP_DEBUG=' . (config('app.debug') ? 'true' : 'false'));
$appUrl = (string) config('app.url', '');
$publicUrl = str_starts_with($appUrl, 'https://') && !preg_match('/localhost|127\.0\.0\.1/i', $appUrl);
$check('Public HTTPS URL', $publicUrl, $appUrl ?: 'APP_URL is empty');
$check('Secure-cookie mode', (bool) config('app.force_https', false), 'APP_FORCE_HTTPS=' . (config('app.force_https', false) ? 'true' : 'false'));
$applicationKey = (string) config('app.key', '');
$check('Application encryption key', strlen($applicationKey) >= 32 && !str_contains($applicationKey,'installer-generates'), $applicationKey !== '' ? 'configured' : 'missing');
$db = config('database');
$dbUser = (string) ($db['username'] ?? '');
$dbPassword = (string) ($db['password'] ?? '');
$check('Least-privilege database user', $dbUser !== '' && !in_array(strtolower($dbUser), ['root', 'admin'], true), 'DB_USERNAME=' . ($dbUser ?: '(empty)'));
$strongDbSecret = strlen($dbPassword) >= 16 && !preg_match('/replace|password|changeme/i', $dbPassword);
$check('Database secret configured', $strongDbSecret, $strongDbSecret ? 'secret length accepted' : 'use a random secret of at least 16 characters');
$logPath = BASE_PATH . '/storage/logs/app.log';
$logWritable = is_file($logPath) ? is_writable($logPath) : @file_put_contents($logPath, '', FILE_APPEND) !== false;
$check('Application log writable', $logWritable, $logPath);

try {
    $pdo = Database::connection();
    $check('Database connection', $pdo->query('SELECT 1')->fetchColumn() === 1, 'connected');
    $requiredTables = ['schema_migrations', 'goods_receipt_items', 'supplier_invoices', 'supplier_payments', 'stock_movements', 'journal_entries', 'audit_logs', 'ai_settings', 'ai_conversations', 'ai_messages', 'ai_insights', 'ai_feedback'];
    foreach ($requiredTables as $table) {
        $statement = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
        $statement->execute([$table]);
        $check('Table ' . $table, (int) $statement->fetchColumn() === 1, 'required production schema');
    }
    $migrationFiles = count(glob(BASE_PATH . '/database/migrations/*.sql') ?: []);
    $appliedMigrations = (int) $pdo->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
    $check('Database migrations', $appliedMigrations === $migrationFiles, "{$appliedMigrations}/{$migrationFiles} applied");
    $demoAccounts = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE status='active' AND email IN ('admin@meatin.local','customer@meatin.local','driver@meatin.local','accounts@meatin.local','hr@meatin.local')")->fetchColumn();
    $check('Demo accounts removed', $demoAccounts === 0, $demoAccounts === 0 ? 'none active' : "{$demoAccounts} known demo accounts remain active");
    $unbalanced = (int) $pdo->query("SELECT COUNT(*) FROM (SELECT je.id FROM journal_entries je JOIN journal_lines jl ON jl.journal_entry_id=je.id WHERE je.status='posted' GROUP BY je.id HAVING ABS(SUM(jl.debit)-SUM(jl.credit))>0.009) x")->fetchColumn();
    $check('Posted journals balanced', $unbalanced === 0, $unbalanced === 0 ? 'balanced' : "{$unbalanced} unbalanced entries");
    $controlAccounts = (int) $pdo->query("SELECT COUNT(*) FROM chart_of_accounts WHERE status='active' AND account_code IN ('1000','1100','1200','1400','2000','2050','2100','4000','5200')")->fetchColumn();
    $check('Financial control accounts', $controlAccounts === 9, "{$controlAccounts}/9 active");
    $indiaDefaults = (int) $pdo->query("SELECT COUNT(*) FROM settings WHERE (setting_key='country' AND setting_value='India') OR (setting_key='currency' AND setting_value='INR') OR (setting_key='timezone' AND setting_value='Asia/Kolkata')")->fetchColumn();
    $check('India operating defaults', $indiaDefaults === 3, "{$indiaDefaults}/3 active");
    $aiInvalid = (int) $pdo->query("SELECT COUNT(*) FROM ai_settings WHERE enabled=1 AND encrypted_api_key IS NULL")->fetchColumn();
    $check('AI configuration integrity', $aiInvalid === 0, $aiInvalid === 0 ? 'disabled or encrypted key configured' : 'AI enabled without encrypted key');
} catch (Throwable $exception) {
    $check('Database connection', false, $exception->getMessage());
}

$failed = count(array_filter($checks, static fn (array $item): bool => !$item['ok']));
foreach ($checks as $item) {
    echo ($item['ok'] ? '[PASS] ' : '[FAIL] ') . $item['name'] . ': ' . $item['detail'] . PHP_EOL;
}
echo PHP_EOL . ($failed === 0 ? 'Production preflight passed.' : "Production preflight blocked by {$failed} check(s).") . PHP_EOL;
exit($failed === 0 ? 0 : 1);
