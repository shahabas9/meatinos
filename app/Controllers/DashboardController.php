<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;
use MeatinOS\Services\DashboardService;
use MeatinOS\Services\NumberingService;

final class DashboardController
{
    public function index(): void
    {
        Auth::requirePermission('dashboard.view');
        $user = Auth::user();
        if (($user['role_slug'] ?? '') === 'customer') {
            $data = (new DashboardService())->customerData((int) ($user['customer_id'] ?? 0));
            View::render('customer-dashboard', array_merge($data, ['title' => 'Customer Portal', 'user' => $user]));
            return;
        }
        if (($user['role_slug'] ?? '') === 'driver') {
            $data = (new DashboardService())->driverData((int) ($user['employee_id'] ?? 0));
            View::render('driver-dashboard', array_merge($data, ['title' => 'Driver Portal', 'user' => $user]));
            return;
        }
        $data = (new DashboardService())->data();
        View::render('dashboard', array_merge($data, ['title' => 'Dashboard', 'user' => Auth::user()]));
    }

    public function createComplaint(): void
    {
        Auth::requireLogin();
        $user = Auth::user();
        if (($user['role_slug'] ?? '') !== 'customer' || empty($user['customer_id'])) throw new \RuntimeException('Only customer portal users can use this form.', 403);
        Csrf::verify($_POST['_token'] ?? null);
        $category = (string) ($_POST['category'] ?? '');
        $details = trim((string) ($_POST['details'] ?? ''));
        $orderId = (int) ($_POST['sales_order_id'] ?? 0);
        $allowedCategories = ['quality','temperature','weight','packaging','delivery','billing','other'];
        if (!in_array($category, $allowedCategories, true) || mb_strlen($details) < 10 || mb_strlen($details) > 3000) {
            flash('danger', 'Choose a complaint category and enter at least 10 characters of detail.');
            redirect('dashboard', ['complaint' => 'new']);
        }
        $pdo = Database::connection();
        if ($orderId > 0) {
            $statement = $pdo->prepare('SELECT COUNT(*) FROM sales_orders WHERE id=? AND customer_id=?');
            $statement->execute([$orderId, $user['customer_id']]);
            if (!(int) $statement->fetchColumn()) throw new \RuntimeException('The selected order is not available for this customer.', 422);
        }
        try {
            $pdo->beginTransaction();
            $number = NumberingService::next('customer_complaint', null, 'CMP');
            $statement = $pdo->prepare("INSERT INTO customer_complaints (complaint_number,customer_id,sales_order_id,complaint_date,category,details,submission_channel,submitted_by_user_id,status) VALUES (?,?,?,CURDATE(),?,?,'customer_portal',?,'open')");
            $statement->execute([$number, $user['customer_id'], $orderId ?: null, $category, $details, $user['id']]);
            $id = (int) $pdo->lastInsertId();
            AuditService::log('created', 'customer_complaints', $id, 'Customer submitted a complaint through the portal.', null, ['complaint_number'=>$number,'category'=>$category,'sales_order_id'=>$orderId ?: null]);
            $pdo->commit();
            flash('success', 'Complaint ' . $number . ' submitted to the Quality team.');
        } catch (\Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        redirect('dashboard');
    }
}
