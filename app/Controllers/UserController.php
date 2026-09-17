<?php

declare(strict_types=1);

namespace MeatinOS\Controllers;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Csrf;
use MeatinOS\Core\Database;
use MeatinOS\Core\View;
use MeatinOS\Services\AuditService;
use PDO;
use PDOException;
use Throwable;

final class UserController
{
    public function index(): void
    {
        Auth::requirePermission('users.view');
        $pdo = Database::connection();
        $users = $pdo->query("SELECT u.id,u.name,u.email,u.status,u.must_change_password,u.last_login_at,u.created_at,r.name role_name,r.slug role_slug,e.full_name employee_name,c.name customer_name,GROUP_CONCAT(ar.name ORDER BY ur.is_primary DESC,ar.name SEPARATOR ', ') role_names FROM users u JOIN roles r ON r.id=u.role_id LEFT JOIN user_roles ur ON ur.user_id=u.id LEFT JOIN roles ar ON ar.id=ur.role_id LEFT JOIN employees e ON e.id=u.employee_id LEFT JOIN customers c ON c.id=u.customer_id GROUP BY u.id ORDER BY u.name")->fetchAll();
        $roles = $pdo->query('SELECT id,name,slug FROM roles ORDER BY name')->fetchAll();
        $employees = $pdo->query("SELECT id,full_name FROM employees WHERE status='active' ORDER BY full_name")->fetchAll();
        $customers = $pdo->query("SELECT id,name FROM customers WHERE status='active' ORDER BY name")->fetchAll();
        $edit = null;
        if (!empty($_GET['edit']) && Auth::can('users.manage')) {
            $statement = $pdo->prepare('SELECT id,role_id,employee_id,customer_id,name,email,status,must_change_password FROM users WHERE id=?');
            $statement->execute([(int) $_GET['edit']]);
            $edit = $statement->fetch() ?: null;
            if ($edit) {
                $assigned = $pdo->prepare('SELECT role_id FROM user_roles WHERE user_id=? ORDER BY is_primary DESC,role_id');
                $assigned->execute([$edit['id']]);
                $edit['role_ids'] = array_map('intval', $assigned->fetchAll(PDO::FETCH_COLUMN));
            }
        }
        View::render('users/index', compact('users', 'roles', 'employees', 'customers', 'edit') + ['title' => 'Users & Permissions', 'canManage' => Auth::can('users.manage')]);
    }

    public function save(): void
    {
        Auth::requirePermission('users.manage');
        Csrf::verify($_POST['_token'] ?? null);
        $id = (int) ($_POST['id'] ?? 0);
        $name = trim((string) ($_POST['name'] ?? ''));
        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $roleId = (int) ($_POST['role_id'] ?? 0);
        $roleIds = array_values(array_unique(array_filter(array_map('intval', (array) ($_POST['role_ids'] ?? [])))));
        if ($roleId > 0 && !in_array($roleId, $roleIds, true)) $roleIds[] = $roleId;
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive', 'locked'], true) ? $_POST['status'] : 'inactive';
        $employeeId = !empty($_POST['employee_id']) ? (int) $_POST['employee_id'] : null;
        $customerId = !empty($_POST['customer_id']) ? (int) $_POST['customer_id'] : null;
        $password = (string) ($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $roleId < 1 || !$roleIds) {
            flash('danger', 'Name, valid email, primary role, and at least one assigned role are required.');
            redirect('users', ['edit' => $id]);
        }
        $pdo = Database::connection();
        $roleStatement = $pdo->prepare('SELECT slug FROM roles WHERE id=?');
        $roleStatement->execute([$roleId]);
        $roleSlug = (string) $roleStatement->fetchColumn();
        if ($roleSlug === '') {
            flash('danger', 'Select a valid role.');
            redirect('users', ['edit' => $id]);
        }
        $roleCheck = $pdo->prepare('SELECT COUNT(*) FROM roles WHERE id IN (' . implode(',', array_fill(0, count($roleIds), '?')) . ')');
        $roleCheck->execute($roleIds);
        if ((int) $roleCheck->fetchColumn() !== count($roleIds)) {
            flash('danger', 'One or more assigned roles are invalid.');
            redirect('users', ['edit' => $id]);
        }
        if ($roleSlug === 'customer' && !$customerId) {
            flash('danger', 'Customer portal users must be linked to a customer profile.');
            redirect('users', ['edit' => $id]);
        }
        if ($roleSlug === 'driver' && !$employeeId) {
            flash('danger', 'Driver users must be linked to an active employee profile.');
            redirect('users', ['edit' => $id]);
        }
        if ($employeeId) {
            $employeeSql = "SELECT COUNT(*) FROM employees WHERE id=? AND status='active'";
            if ($roleSlug === 'driver') $employeeSql .= " AND department='Logistics'";
            $employeeStatement = $pdo->prepare($employeeSql);
            $employeeStatement->execute([$employeeId]);
            if ((int) $employeeStatement->fetchColumn() !== 1) {
                flash('danger', 'Select a valid active employee profile.');
                redirect('users', ['edit' => $id]);
            }
        }
        if ($customerId) {
            $customerStatement = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE id=? AND status='active'");
            $customerStatement->execute([$customerId]);
            if ((int) $customerStatement->fetchColumn() !== 1) {
                flash('danger', 'Select a valid active customer profile.');
                redirect('users', ['edit' => $id]);
            }
        }
        if ($roleSlug !== 'customer') {
            $customerId = null;
        }
        if ($id === (int) (Auth::user()['id'] ?? 0) && $status !== 'active') {
            flash('danger', 'You cannot deactivate or lock your own signed-in account.');
            redirect('users', ['edit' => $id]);
        }
        if (($id === 0 || $password !== '') && (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password))) {
            flash('danger', 'New user passwords require 12 characters with upper-case, lower-case, number, and symbol.');
            redirect('users', ['edit' => $id]);
        }
        $pdo->beginTransaction();
        try {
            if ($id) {
                $oldStatement = $pdo->prepare('SELECT id,role_id,employee_id,customer_id,name,email,status FROM users WHERE id=?');
                $oldStatement->execute([$id]);
                $old = $oldStatement->fetch();
                if (!$old) {
                    throw new \RuntimeException('User not found.', 404);
                }
                $superAdminRole = (int) $pdo->query("SELECT id FROM roles WHERE slug='super_admin'")->fetchColumn();
                $wasSuperStatement = $pdo->prepare('SELECT COUNT(*) FROM user_roles WHERE user_id=? AND role_id=?');
                $wasSuperStatement->execute([$id, $superAdminRole]);
                if ((int) $wasSuperStatement->fetchColumn() > 0 && (!in_array($superAdminRole, $roleIds, true) || $status !== 'active')) {
                        $otherAdmins = $pdo->prepare("SELECT COUNT(DISTINCT u.id) FROM users u JOIN user_roles ur ON ur.user_id=u.id JOIN roles r ON r.id=ur.role_id WHERE r.slug='super_admin' AND u.status='active' AND u.id<>?");
                        $otherAdmins->execute([$id]);
                        if ((int) $otherAdmins->fetchColumn() === 0) {
                            $pdo->rollBack();
                            flash('danger', 'At least one active Super Admin account must remain.');
                            redirect('users', ['edit' => $id]);
                        }
                }
                $sql = 'UPDATE users SET role_id=?,employee_id=?,customer_id=?,name=?,email=?,status=?';
                $params = [$roleId, $employeeId, $customerId, $name, $email, $status];
                if ($password !== '') {
                    $sql .= ',password_hash=?,must_change_password=1';
                    $params[] = password_hash($password, PASSWORD_ARGON2ID);
                }
                $sql .= ' WHERE id=?';
                $params[] = $id;
                $pdo->prepare($sql)->execute($params);
                AuditService::log('updated', 'users', $id, 'User access updated.', $old, ['name' => $name, 'email' => $email, 'role_id' => $roleId, 'role_ids' => $roleIds, 'status' => $status]);
            } else {
                $statement = $pdo->prepare('INSERT INTO users (role_id,employee_id,customer_id,name,email,password_hash,status,must_change_password) VALUES (?,?,?,?,?,?,?,1)');
                $statement->execute([$roleId, $employeeId, $customerId, $name, $email, password_hash($password, PASSWORD_ARGON2ID), $status]);
                $id = (int) $pdo->lastInsertId();
                AuditService::log('created', 'users', $id, 'User account created.', null, ['name' => $name, 'email' => $email, 'role_id' => $roleId, 'status' => $status]);
            }
            $pdo->prepare('DELETE FROM user_roles WHERE user_id=?')->execute([$id]);
            $assign = $pdo->prepare('INSERT INTO user_roles (user_id,role_id,is_primary) VALUES (?,?,?)');
            foreach ($roleIds as $assignedRoleId) $assign->execute([$id, $assignedRoleId, $assignedRoleId === $roleId ? 1 : 0]);
            $pdo->commit();
            flash('success', 'User access saved successfully.');
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            if ((string) $exception->getCode() === '23000') {
                flash('danger', 'That email address is already in use.');
            } else {
                throw $exception;
            }
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        redirect('users');
    }
}
