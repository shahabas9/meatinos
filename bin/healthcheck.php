<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use MeatinOS\Core\Database;

$result = ['status' => 'ok', 'checked_at' => date(DATE_ATOM), 'checks' => []];
try {
    $pdo = Database::connection();
    $result['checks']['database'] = $pdo->query('SELECT 1')->fetchColumn() === 1 ? 'ok' : 'failed';
    $result['checks']['schema_version'] = (int) $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()")->fetchColumn();
    $result['checks']['open_critical_alerts'] = (int) $pdo->query("SELECT COUNT(*) FROM alerts WHERE status='open' AND severity='critical'")->fetchColumn();
    $ai = $pdo->query('SELECT enabled,encrypted_api_key IS NOT NULL configured FROM ai_settings WHERE id=1')->fetch();
    $result['checks']['ai'] = $ai && $ai['enabled'] && $ai['configured'] ? 'enabled' : 'disabled';
} catch (Throwable $exception) {
    $result['status'] = 'failed';
    $result['checks']['database'] = 'failed';
    $result['error'] = $exception->getMessage();
}
$logPath = dirname(__DIR__) . '/storage/logs/app.log';
$logWritable = is_file($logPath)
    ? is_writable($logPath)
    : @file_put_contents($logPath, '', FILE_APPEND) !== false;
$result['checks']['log_writable'] = $logWritable;
if (!$logWritable) $result['status'] = 'failed';
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
exit($result['status'] === 'ok' ? 0 : 1);
