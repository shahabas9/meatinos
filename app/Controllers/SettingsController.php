<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;
use MeatinOS\Services\AiSettingsService;
use MeatinOS\Services\OpenAIService;
use PDO;

final class SettingsController
{
    public function index(): void
    {
        Auth::requirePermission('settings.view');
        $values = Database::connection()->query('SELECT setting_key,setting_value FROM settings ORDER BY setting_key')->fetchAll(PDO::FETCH_KEY_PAIR);
        View::render('settings/index', ['title' => 'System Settings', 'settings' => $values, 'canManage' => Auth::can('settings.manage'), 'aiSettings'=>(new AiSettingsService())->get(), 'canManageAi'=>Auth::can('ai.manage')]);
    }

    public function save(): void
    {
        Auth::requirePermission('settings.manage');
        Csrf::verify($_POST['_token'] ?? null);
        $allowed = ['company_name', 'currency', 'timezone', 'country', 'gst_rate_percent', 'lot_strategy', 'temperature_alert_delay_minutes', 'storage_door_alert_minutes'];
        $pdo = Database::connection();
        $statement = $pdo->prepare('INSERT INTO settings (setting_key,setting_value,updated_by) VALUES (?,?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)');
        foreach ($allowed as $key) {
            $value = match ($key) {
                'currency' => 'INR', 'timezone' => 'Asia/Kolkata', 'country' => 'India',
                'gst_rate_percent' => (string) min(100,max(0,(float) ($_POST[$key] ?? 5))),
                'storage_door_alert_minutes' => (string) min(60,max(0,(float) ($_POST[$key] ?? 2))),
                default => trim((string) ($_POST[$key] ?? '')),
            };
            $statement->execute([$key, $value, Auth::user()['id']]);
        }
        AuditService::log('updated', 'settings', null, 'System settings updated.');
        flash('success', 'System settings updated.');
        redirect('settings');
    }

    public function saveAi(): void
    {
        Auth::requirePermission('ai.manage');
        Csrf::verify($_POST['_token'] ?? null);
        try {
            (new AiSettingsService())->save($_POST,(int) Auth::user()['id']);
            AuditService::log('updated','ai_settings',1,'AI configuration updated; secret value was not logged.');
            flash('success','AI settings saved securely.');
        } catch (\Throwable $exception) {
            flash('danger',$exception->getMessage());
        }
        redirect('settings');
    }

    public function testAi(): void
    {
        Auth::requirePermission('ai.manage');
        Csrf::verify($_POST['_token'] ?? null);
        try {
            $service = new AiSettingsService();
            $settings = $service->get();
            $key = $service->apiKey(false);
            if ($key === null) throw new \RuntimeException('Add and save an OpenAI API key before testing.');
            $result = (new OpenAIService())->respond($key,(string) $settings['model'],'Reply with exactly: Connection OK','Connection test',20);
            AuditService::log('connection_tested','ai_settings',1,'OpenAI connection test succeeded.',null,['model'=>$settings['model'],'request_id'=>$result['request_id']]);
            flash('success','OpenAI connection successful.');
        } catch (\Throwable $exception) {
            AuditService::log('connection_test_failed','ai_settings',1,'OpenAI connection test failed.');
            flash('danger',$exception->getMessage());
        }
        redirect('settings');
    }

    public function removeAiKey(): void
    {
        Auth::requirePermission('ai.manage');
        Csrf::verify($_POST['_token'] ?? null);
        (new AiSettingsService())->removeKey((int) Auth::user()['id']);
        AuditService::log('secret_removed','ai_settings',1,'OpenAI API key removed.');
        flash('success','OpenAI API key removed and AI disabled.');
        redirect('settings');
    }
}
