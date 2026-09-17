<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use MeatinOS\Controllers\AuthController;
use MeatinOS\Controllers\AiController;
use MeatinOS\Controllers\DashboardController;
use MeatinOS\Controllers\DocumentController;
use MeatinOS\Controllers\DriverController;
use MeatinOS\Controllers\GovernanceController;
use MeatinOS\Controllers\ModuleController;
use MeatinOS\Controllers\PayrollController;
use MeatinOS\Controllers\ProductionController;
use MeatinOS\Controllers\RateCardController;
use MeatinOS\Controllers\ReportsController;
use MeatinOS\Controllers\SettingsController;
use MeatinOS\Controllers\UpdateController;
use MeatinOS\Controllers\UserController;
use MeatinOS\Controllers\WorkflowController;
use MeatinOS\Controllers\WorkforceController;
use MeatinOS\Core\Auth;
use MeatinOS\Services\EmployeeActivityService;

$route = preg_replace('/[^a-z0-9._-]/i', '', (string) ($_GET['route'] ?? 'dashboard'));
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$method = $method === 'HEAD' ? 'GET' : $method;

if (Auth::check() && !empty(Auth::user()['must_change_password']) && !in_array($route, ['password', 'password.change', 'logout'], true)) {
    flash('warning', 'Change your temporary password before continuing.');
    redirect('password');
}

if (Auth::check()) {
    EmployeeActivityService::touch($route);
}

$routes = [
    'GET' => [
        'login' => [AuthController::class, 'loginForm'],
        'dashboard' => [DashboardController::class, 'index'],
        'module' => [ModuleController::class, 'index'],
        'reports' => [ReportsController::class, 'index'],
        'traceability' => [ReportsController::class, 'traceability'],
        'search' => [ReportsController::class, 'globalSearch'],
        'entity360' => [ReportsController::class, 'entity360'],
        'intelligence' => [ReportsController::class, 'intelligence'],
        'commercial' => [ReportsController::class, 'commercial'],
        'rate-card.bulk' => [RateCardController::class, 'bulk'],
        'rate-card.export' => [RateCardController::class, 'export'],
        'production.wastage' => [ProductionController::class, 'wastage'],
        'production.wastage.export' => [ProductionController::class, 'exportWastage'],
        'reports.scheduled' => [ReportsController::class, 'scheduled'],
        'reports.scheduled.download' => [ReportsController::class, 'downloadScheduled'],
        'reports.export' => [ReportsController::class, 'export'],
        'audit' => [ReportsController::class, 'audit'],
        'users' => [UserController::class, 'index'],
        'settings' => [SettingsController::class, 'index'],
        'system.update' => [UpdateController::class, 'index'],
        'ai' => [AiController::class, 'index'],
        'password' => [AuthController::class, 'passwordForm'],
        'payroll' => [PayrollController::class, 'index'],
        'workflow' => [WorkflowController::class, 'show'],
        'documents' => [DocumentController::class, 'index'],
        'documents.download' => [DocumentController::class, 'download'],
        'partner.receipt' => [GovernanceController::class, 'partnerReceipt'],
        'partner.welcome' => [GovernanceController::class, 'partnerWelcome'],
        'partners' => [GovernanceController::class, 'partners'],
        'hr.letter' => [GovernanceController::class, 'employmentLetter'],
        'workforce.tasks' => [WorkforceController::class, 'tasks'],
        'sales.tracking' => [WorkforceController::class, 'salesTracking'],
    ],
    'POST' => [
        'login' => [AuthController::class, 'login'],
        'logout' => [AuthController::class, 'logout'],
        'module.save' => [ModuleController::class, 'save'],
        'module.delete' => [ModuleController::class, 'delete'],
        'rate-card.bulk.save' => [RateCardController::class, 'saveBulk'],
        'customer.complaint.create' => [DashboardController::class, 'createComplaint'],
        'users.save' => [UserController::class, 'save'],
        'settings.save' => [SettingsController::class, 'save'],
        'settings.ai.save' => [SettingsController::class, 'saveAi'],
        'settings.ai.test' => [SettingsController::class, 'testAi'],
        'settings.ai.remove' => [SettingsController::class, 'removeAiKey'],
        'system.update.apply' => [UpdateController::class, 'apply'],
        'ai.ask' => [AiController::class, 'ask'],
        'ai.feedback' => [AiController::class, 'feedback'],
        'ai.insight.status' => [AiController::class, 'insightStatus'],
        'password.change' => [AuthController::class, 'changePassword'],
        'payroll.generate' => [PayrollController::class, 'generate'],
        'payroll.status' => [PayrollController::class, 'status'],
        'driver.dispatch.update' => [DriverController::class, 'updateDispatch'],
        'workflow.action' => [WorkflowController::class, 'action'],
        'workflow.line.save' => [WorkflowController::class, 'saveLine'],
        'workflow.line.delete' => [WorkflowController::class, 'deleteLine'],
        'workflow.check.save' => [WorkflowController::class, 'saveCheck'],
        'documents.upload' => [DocumentController::class, 'upload'],
        'workforce.tasks.save' => [WorkforceController::class, 'saveTask'],
        'workforce.tasks.action' => [WorkforceController::class, 'taskAction'],
        'sales.tracking.start' => [WorkforceController::class, 'startTracking'],
        'sales.tracking.ping' => [WorkforceController::class, 'pingTracking'],
        'sales.tracking.stop' => [WorkforceController::class, 'stopTracking'],
    ],
];

if (!isset($routes[$method][$route])) {
    http_response_code(404);
    echo 'Page not found.';
    exit;
}

[$class, $action] = $routes[$method][$route];
(new $class())->{$action}();
