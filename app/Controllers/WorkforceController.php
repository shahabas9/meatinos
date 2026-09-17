<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;
use MeatinOS\Services\EmployeeActivityService;
use MeatinOS\Services\NumberingService;
use PDO;
use Throwable;

final class WorkforceController
{
    public function tasks(): void
    {
        Auth::requireLogin();
        $pdo = Database::connection();
        $employeeId = $this->optionalEmployeeId();
        $taskDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($_GET['date'] ?? '')) ? (string) $_GET['date'] : date('Y-m-d');
        $canSeeAll = Auth::can('hr.manage');
        if ($employeeId < 1 && !$canSeeAll) {
            throw new \RuntimeException('Your login is not linked to an employee record.', 422);
        }
        $canAssign = $canSeeAll || ($employeeId > 0 && $this->hasDirectReports($pdo, $employeeId));
        $sql = "SELECT t.*,e.employee_number,e.full_name,m.full_name manager_name,a.name assigned_by_name FROM employee_tasks t JOIN employees e ON e.id=t.employee_id LEFT JOIN employees m ON m.id=t.manager_id LEFT JOIN users a ON a.id=t.assigned_by_user_id WHERE t.task_date=?";
        $params = [$taskDate];
        if (!$canSeeAll) {
            $sql .= ' AND ? IN (t.employee_id,COALESCE(t.manager_id,0),COALESCE(t.assigned_by_employee_id,0))';
            $params[] = $employeeId;
        }
        $sql .= " ORDER BY FIELD(t.status,'in_progress','assigned','pending','completed','cancelled'),FIELD(t.priority,'urgent','high','normal','low'),COALESCE(t.due_at,'9999-12-31'),t.id DESC LIMIT 500";
        $tasks = $this->all($pdo, $sql, $params);
        $employeeSql = $canSeeAll
            ? "SELECT id,employee_number,full_name,department FROM employees WHERE status='active' ORDER BY department,full_name"
            : "SELECT id,employee_number,full_name,department FROM employees WHERE status='active' AND manager_id=? ORDER BY full_name";
        $employees = $canSeeAll ? $pdo->query($employeeSql)->fetchAll() : $this->all($pdo, $employeeSql, [$employeeId]);
        $counts = ['assigned'=>0,'in_progress'=>0,'pending'=>0,'completed'=>0];
        foreach ($tasks as $task) {
            if (isset($counts[$task['status']])) {
                $counts[$task['status']]++;
            }
        }
        $activity = $employeeId > 0 ? $this->one($pdo, 'SELECT * FROM employee_activity_status WHERE employee_id=?', [$employeeId]) : null;
        View::render('workforce/tasks', compact('tasks','employees','taskDate','counts','canAssign','canSeeAll','activity','employeeId') + ['title'=>'Daily Employee Tasks']);
    }

    public function saveTask(): void
    {
        Auth::requireLogin();
        Csrf::verify($_POST['_token'] ?? null);
        $pdo = Database::connection();
        $currentEmployeeId = $this->optionalEmployeeId();
        if ($currentEmployeeId < 1 && !Auth::can('hr.manage')) {
            throw new \RuntimeException('Your login is not linked to an employee record.', 422);
        }
        try {
            $employeeId = (int) ($_POST['employee_id'] ?? 0);
            $employee = $this->one($pdo, "SELECT id,manager_id,plant_id,status FROM employees WHERE id=?", [$employeeId]);
            $this->expect($employee && $employee['status'] === 'active', 'Select an active employee.');
            $canAssign = Auth::can('hr.manage') || (int) ($employee['manager_id'] ?? 0) === $currentEmployeeId;
            $this->expect($canAssign, 'You may assign tasks only to your direct reports.');
            $taskDate = trim((string) ($_POST['task_date'] ?? ''));
            $this->expect((bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $taskDate), 'Select a valid task date.');
            $title = mb_substr(trim((string) ($_POST['title'] ?? '')), 0, 180);
            $this->expect($title !== '', 'Enter a task title.');
            $description = mb_substr(trim((string) ($_POST['description'] ?? '')), 0, 5000);
            $priority = (string) ($_POST['priority'] ?? 'normal');
            $this->expect(in_array($priority, ['low','normal','high','urgent'], true), 'Select a valid priority.');
            $dueAt = trim((string) ($_POST['due_at'] ?? ''));
            if ($dueAt !== '') {
                $dueAt = str_replace('T', ' ', $dueAt) . (strlen($dueAt) === 16 ? ':00' : '');
                $this->expect(strtotime($dueAt) !== false, 'Enter a valid due time.');
            }
            $taskNumber = NumberingService::next('employee_task', (int) ($employee['plant_id'] ?? 0) ?: null, 'TASK');
            $statement = $pdo->prepare("INSERT INTO employee_tasks (task_number,employee_id,manager_id,assigned_by_user_id,assigned_by_employee_id,task_date,title,description,priority,due_at,status) VALUES (?,?,?,?,?,?,?,?,?,?,'assigned')");
            $statement->execute([$taskNumber,$employeeId,$employee['manager_id'] ?: null,Auth::user()['id'],$currentEmployeeId ?: null,$taskDate,$title,$description ?: null,$priority,$dueAt ?: null]);
            AuditService::log('created', 'employee_tasks', (int) $pdo->lastInsertId(), 'Daily employee task assigned.', null, ['task_number'=>$taskNumber,'employee_id'=>$employeeId,'task_date'=>$taskDate,'priority'=>$priority]);
            flash('success', 'Daily task assigned successfully.');
        } catch (Throwable $exception) {
            if ((int) $exception->getCode() === 422) flash('danger', $exception->getMessage());
            else throw $exception;
        }
        redirect('workforce.tasks', ['date'=>(string) ($_POST['task_date'] ?? date('Y-m-d'))]);
    }

    public function taskAction(): void
    {
        Auth::requireLogin();
        Csrf::verify($_POST['_token'] ?? null);
        $pdo = Database::connection();
        $employeeId = $this->optionalEmployeeId();
        if ($employeeId < 1 && !Auth::can('hr.manage')) {
            throw new \RuntimeException('Your login is not linked to an employee record.', 422);
        }
        $taskId = (int) ($_POST['id'] ?? 0);
        try {
            $task = $this->one($pdo, 'SELECT * FROM employee_tasks WHERE id=?', [$taskId]);
            $this->expect($task !== null, 'Task not found.');
            $isAssignee = (int) $task['employee_id'] === $employeeId;
            $isManager = (int) ($task['manager_id'] ?? 0) === $employeeId || Auth::can('hr.manage');
            $this->expect($isAssignee || $isManager, 'You cannot update this task.');
            $action = (string) ($_POST['task_action'] ?? '');
            $status = match ($action) {
                'start' => 'in_progress',
                'complete' => 'completed',
                'pending' => 'pending',
                'reopen' => 'assigned',
                'cancel' => 'cancelled',
                default => throw new \RuntimeException('Select a valid task action.', 422),
            };
            if (in_array($action, ['reopen','cancel'], true)) {
                $this->expect($isManager, 'Only the manager can reopen or cancel this task.');
            }
            $notes = mb_substr(trim((string) ($_POST['completion_notes'] ?? '')), 0, 1500);
            $completedAt = $status === 'completed' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare('UPDATE employee_tasks SET status=?,completed_at=?,completion_notes=? WHERE id=?')
                ->execute([$status,$completedAt,$notes ?: null,$taskId]);
            AuditService::log('task_status_updated', 'employee_tasks', $taskId, 'Daily task status updated.', ['status'=>$task['status']], ['status'=>$status,'completion_notes'=>$notes]);
            flash('success', 'Task status updated to ' . human_status($status) . '.');
        } catch (Throwable $exception) {
            if ((int) $exception->getCode() === 422) flash('danger', $exception->getMessage());
            else throw $exception;
        }
        redirect('workforce.tasks', ['date'=>(string) ($_POST['task_date'] ?? date('Y-m-d'))]);
    }

    public function salesTracking(): void
    {
        Auth::requireLogin();
        $pdo = Database::connection();
        $employeeId = $this->optionalEmployeeId();
        $employee = $employeeId > 0 ? $this->one($pdo, 'SELECT * FROM employees WHERE id=?', [$employeeId]) : null;
        $isSalesperson = strtolower((string) ($employee['department'] ?? '')) === 'sales';
        $canMonitor = $this->canMonitorSalesTeam();
        if (!$isSalesperson && !$canMonitor) {
            http_response_code(403);
            View::render('errors/403', ['title'=>'Access denied']);
            return;
        }
        $teamSql = "SELECT e.id,e.employee_number,e.full_name,e.phone,s.tracking_number,s.location_name,s.started_at,s.ended_at,s.last_latitude,s.last_longitude,s.last_ping_at,s.distance_km,s.status,c.name customer_name,r.name route_name FROM employees e LEFT JOIN salesman_tracking_sessions s ON s.id=(SELECT sx.id FROM salesman_tracking_sessions sx WHERE sx.employee_id=e.id ORDER BY sx.last_ping_at DESC,sx.id DESC LIMIT 1) LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN route_masters r ON r.id=s.route_master_id WHERE e.status='active' AND LOWER(e.department)='sales'";
        $params = [];
        if (!$canMonitor) {
            $teamSql .= ' AND e.id=?';
            $params[] = $employeeId;
        }
        $teamSql .= ' ORDER BY e.full_name';
        $team = $this->all($pdo, $teamSql, $params);
        $historySql = "SELECT s.*,e.full_name,c.name customer_name,r.name route_name,TIMESTAMPDIFF(MINUTE,s.started_at,COALESCE(s.ended_at,NOW())) duration_minutes FROM salesman_tracking_sessions s JOIN employees e ON e.id=s.employee_id LEFT JOIN customers c ON c.id=s.customer_id LEFT JOIN route_masters r ON r.id=s.route_master_id";
        $historyParams = [];
        if (!$canMonitor) {
            $historySql .= ' WHERE s.employee_id=?';
            $historyParams[] = $employeeId;
        }
        $historySql .= ' ORDER BY s.started_at DESC,s.id DESC LIMIT 100';
        $history = $this->all($pdo, $historySql, $historyParams);
        $active = $isSalesperson ? $this->one($pdo, "SELECT * FROM salesman_tracking_sessions WHERE employee_id=? AND status='active' ORDER BY id DESC LIMIT 1", [$employeeId]) : null;
        $customers = $pdo->query("SELECT id,code,name FROM customers WHERE status='active' ORDER BY name")->fetchAll();
        $routes = $pdo->query("SELECT id,route_code,name FROM route_masters WHERE status='active' ORDER BY name")->fetchAll();
        View::render('workforce/sales-tracking', compact('team','history','active','customers','routes','employee','isSalesperson','canMonitor') + ['title'=>'Salesman Tracking']);
    }

    public function startTracking(): void
    {
        Auth::requireLogin();
        Csrf::verify($_POST['_token'] ?? null);
        $pdo = Database::connection();
        $employeeId = $this->employeeId();
        try {
            $this->assertSalesperson($pdo, $employeeId);
            $this->expect(!$this->one($pdo, "SELECT id FROM salesman_tracking_sessions WHERE employee_id=? AND status='active'", [$employeeId]), 'Complete the active visit before starting another.');
            [$latitude,$longitude,$accuracy] = $this->coordinates($_POST);
            $purpose = mb_substr(trim((string) ($_POST['visit_purpose'] ?? '')), 0, 180);
            $location = mb_substr(trim((string) ($_POST['location_name'] ?? '')), 0, 200);
            $notes = mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 1500);
            $this->expect($purpose !== '' && $location !== '', 'Enter the visit purpose and location name.');
            $customerId = (int) ($_POST['customer_id'] ?? 0) ?: null;
            $routeId = (int) ($_POST['route_master_id'] ?? 0) ?: null;
            if ($customerId) $this->expect((bool) $this->one($pdo, "SELECT id FROM customers WHERE id=? AND status='active'", [$customerId]), 'Select an active customer.');
            if ($routeId) $this->expect((bool) $this->one($pdo, "SELECT id FROM route_masters WHERE id=? AND status='active'", [$routeId]), 'Select an active route.');
            $trackingNumber = NumberingService::next('salesman_tracking', null, 'TRK');
            $pdo->beginTransaction();
            $pdo->prepare("INSERT INTO salesman_tracking_sessions (tracking_number,employee_id,customer_id,route_master_id,visit_purpose,location_name,started_at,start_latitude,start_longitude,last_latitude,last_longitude,last_ping_at,distance_km,status,notes) VALUES (?,?,?,?,?,?,NOW(),?,?,?,?,NOW(),0,'active',?)")
                ->execute([$trackingNumber,$employeeId,$customerId,$routeId,$purpose,$location,$latitude,$longitude,$latitude,$longitude,$notes ?: null]);
            $sessionId = (int) $pdo->lastInsertId();
            $this->insertPoint($pdo, $sessionId, $employeeId, $latitude, $longitude, $accuracy);
            EmployeeActivityService::markLocationPing($pdo, $employeeId);
            AuditService::log('tracking_started', 'salesman_tracking', $sessionId, 'Sales field visit tracking started.', null, ['tracking_number'=>$trackingNumber,'customer_id'=>$customerId,'route_master_id'=>$routeId,'location_name'=>$location]);
            $pdo->commit();
            flash('success', 'Live salesman tracking started. Keep this page open during the visit.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((int) $exception->getCode() === 422) flash('danger', $exception->getMessage());
            else throw $exception;
        }
        redirect('sales.tracking');
    }

    public function pingTracking(): void
    {
        Auth::requireLogin();
        Csrf::verify($_POST['_token'] ?? null);
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $pdo = Database::connection();
            $employeeId = $this->employeeId();
            [$latitude,$longitude,$accuracy] = $this->coordinates($_POST);
            $session = $this->one($pdo, "SELECT * FROM salesman_tracking_sessions WHERE employee_id=? AND status='active' ORDER BY id DESC LIMIT 1", [$employeeId]);
            $this->expect($session !== null, 'No active tracking session was found.');
            $distance = $this->distanceKm((float) $session['last_latitude'], (float) $session['last_longitude'], $latitude, $longitude);
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE salesman_tracking_sessions SET last_latitude=?,last_longitude=?,last_ping_at=NOW(),distance_km=distance_km+? WHERE id=?')
                ->execute([$latitude,$longitude,$distance,$session['id']]);
            $this->insertPoint($pdo, (int) $session['id'], $employeeId, $latitude, $longitude, $accuracy);
            EmployeeActivityService::markLocationPing($pdo, $employeeId);
            $pdo->commit();
            echo json_encode(['ok'=>true,'recorded_at'=>date(DATE_ATOM),'distance_added_km'=>round($distance,3)]);
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            http_response_code((int) $exception->getCode() === 422 ? 422 : 500);
            echo json_encode(['ok'=>false,'message'=>(int) $exception->getCode() === 422 ? $exception->getMessage() : 'Unable to record location.']);
        }
        exit;
    }

    public function stopTracking(): void
    {
        Auth::requireLogin();
        Csrf::verify($_POST['_token'] ?? null);
        $pdo = Database::connection();
        $employeeId = $this->employeeId();
        try {
            [$latitude,$longitude,$accuracy] = $this->coordinates($_POST);
            $session = $this->one($pdo, "SELECT * FROM salesman_tracking_sessions WHERE employee_id=? AND status='active' ORDER BY id DESC LIMIT 1", [$employeeId]);
            $this->expect($session !== null, 'No active tracking session was found.');
            $distance = $this->distanceKm((float) $session['last_latitude'], (float) $session['last_longitude'], $latitude, $longitude);
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE salesman_tracking_sessions SET ended_at=NOW(),end_latitude=?,end_longitude=?,last_latitude=?,last_longitude=?,last_ping_at=NOW(),distance_km=distance_km+?,status='completed' WHERE id=?")
                ->execute([$latitude,$longitude,$latitude,$longitude,$distance,$session['id']]);
            $this->insertPoint($pdo, (int) $session['id'], $employeeId, $latitude, $longitude, $accuracy);
            EmployeeActivityService::markLocationPing($pdo, $employeeId);
            AuditService::log('tracking_completed', 'salesman_tracking', (int) $session['id'], 'Sales field visit tracking completed.', ['status'=>'active'], ['status'=>'completed']);
            $pdo->commit();
            flash('success', 'Visit completed and travel time, route, and location history were saved.');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((int) $exception->getCode() === 422) flash('danger', $exception->getMessage());
            else throw $exception;
        }
        redirect('sales.tracking');
    }

    private function employeeId(): int
    {
        $employeeId = (int) (Auth::user()['employee_id'] ?? 0);
        if ($employeeId < 1) {
            throw new \RuntimeException('Your login is not linked to an employee record.', 422);
        }
        return $employeeId;
    }

    private function optionalEmployeeId(): int
    {
        return (int) (Auth::user()['employee_id'] ?? 0);
    }

    private function hasDirectReports(PDO $pdo, int $employeeId): bool
    {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE manager_id=? AND status='active'");
        $statement->execute([$employeeId]);
        return (int) $statement->fetchColumn() > 0;
    }

    private function canMonitorSalesTeam(): bool
    {
        foreach (['super_admin','managing_director','general_manager','sales_manager','hr_manager'] as $role) {
            if (Auth::hasRole($role)) return true;
        }
        return false;
    }

    private function assertSalesperson(PDO $pdo, int $employeeId): void
    {
        $statement = $pdo->prepare("SELECT COUNT(*) FROM employees WHERE id=? AND status='active' AND LOWER(department)='sales'");
        $statement->execute([$employeeId]);
        $this->expect((int) $statement->fetchColumn() === 1, 'Only an active Sales employee can start field tracking.');
    }

    /** @return array{0:float,1:float,2:?float} */
    private function coordinates(array $input): array
    {
        $latitude = filter_var($input['latitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $longitude = filter_var($input['longitude'] ?? null, FILTER_VALIDATE_FLOAT);
        $accuracy = ($input['accuracy_meters'] ?? '') === '' ? null : filter_var($input['accuracy_meters'], FILTER_VALIDATE_FLOAT);
        $this->expect($latitude !== false && $longitude !== false && $latitude >= -90 && $latitude <= 90 && $longitude >= -180 && $longitude <= 180, 'Capture a valid GPS location before continuing.');
        $this->expect($accuracy === null || ($accuracy !== false && $accuracy >= 0), 'The GPS accuracy value is invalid.');
        return [(float) $latitude,(float) $longitude,$accuracy === null ? null : (float) $accuracy];
    }

    private function insertPoint(PDO $pdo, int $sessionId, int $employeeId, float $latitude, float $longitude, ?float $accuracy): void
    {
        $pdo->prepare('INSERT INTO salesman_location_points (tracking_session_id,employee_id,latitude,longitude,accuracy_meters,recorded_at) VALUES (?,?,?,?,?,NOW())')
            ->execute([$sessionId,$employeeId,$latitude,$longitude,$accuracy]);
    }

    private function distanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earth = 6371.0088;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $earth * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a)));
    }

    private function expect(bool $condition, string $message): void
    {
        if (!$condition) throw new \RuntimeException($message, 422);
    }

    /** @return list<array<string,mixed>> */
    private function all(PDO $pdo, string $sql, array $params = []): array
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }

    /** @return array<string,mixed>|null */
    private function one(PDO $pdo, string $sql, array $params = []): ?array
    {
        $statement = $pdo->prepare($sql);
        $statement->execute($params);
        return $statement->fetch() ?: null;
    }
}
