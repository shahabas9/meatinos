<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;
use MeatinOS\Services\MigrationService;
use Throwable;

final class UpdateController
{
    public function index(): void
    {
        Auth::requirePermission('settings.manage');
        $status = MigrationService::status(Database::connection());
        $checks = [
            ['label' => 'PHP 8.2 or newer', 'passed' => version_compare(PHP_VERSION, '8.2.0', '>=')],
            ['label' => 'PDO MySQL extension', 'passed' => extension_loaded('pdo_mysql')],
            ['label' => 'Migration files readable', 'passed' => is_readable(BASE_PATH . '/database/migrations')],
            ['label' => 'Application log writable', 'passed' => is_dir(BASE_PATH . '/storage/logs') && is_writable(BASE_PATH . '/storage/logs')],
        ];
        $ready = !in_array(false, array_column($checks, 'passed'), true);

        View::render('update/index', [
            'title' => 'System Update',
            'status' => $status,
            'checks' => $checks,
            'ready' => $ready,
        ]);
    }

    public function apply(): void
    {
        Auth::requirePermission('settings.manage');
        Csrf::verify($_POST['_token'] ?? null);
        if (($_POST['backup_confirmed'] ?? '') !== '1') {
            flash('danger', 'Confirm that a current database and file backup exists before updating.');
            redirect('system.update');
        }

        $pdo = Database::connection();
        $before = MigrationService::status($pdo);
        if (!$before['pending']) {
            flash('info', 'The database is already current. No migration was run.');
            redirect('system.update');
        }

        try {
            $completed = MigrationService::apply($pdo);
            AuditService::log(
                'schema_updated',
                'system',
                null,
                count($completed) . ' database migration(s) applied from the cPanel updater.',
                ['current' => $before['current']],
                ['current' => end($completed) ?: $before['current'], 'migrations' => $completed]
            );
            flash('success', count($completed) . ' migration(s) applied successfully. MeatinOS is current.');
        } catch (Throwable $exception) {
            error_log(sprintf("[%s] cPanel updater failed: %s\n", date('c'), $exception->getMessage()), 3, BASE_PATH . '/storage/logs/app.log');
            try {
                AuditService::log('schema_update_failed', 'system', null, 'A cPanel database update failed; see the application log.');
            } catch (Throwable) {
            }
            flash('danger', 'Update stopped: ' . $exception->getMessage());
        }

        redirect('system.update');
    }
}
