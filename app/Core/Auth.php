<?php

declare(strict_types=1);

namespace MeatinOS\Core;

use DateTimeImmutable;
use MeatinOS\Services\AuditService;
use PDO;

final class Auth
{
    private static ?array $user = null;
    private static ?array $permissions = null;

    public static function attempt(string $email, string $password): bool
    {
        $pdo = Database::connection();
        $email = mb_strtolower(trim($email));
        $ip = request_ip();
        $rate = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_address = ? AND successful = 0 AND attempted_at >= (NOW() - INTERVAL 15 MINUTE)");
        $rate->execute([$email, $ip]);
        $failedCount = (int) $rate->fetchColumn();
        if ($failedCount >= 5) {
            return false;
        }

        $statement = $pdo->prepare('SELECT u.*, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.email = ? LIMIT 1');
        $statement->execute([$email]);
        $user = $statement->fetch();
        $valid = $user && $user['status'] === 'active' && (!$user['locked_until'] || strtotime((string) $user['locked_until']) < time()) && password_verify($password, (string) $user['password_hash']);

        $pdo->prepare('INSERT INTO login_attempts (email, ip_address, successful) VALUES (?, ?, ?)')->execute([$email, $ip, $valid ? 1 : 0]);
        if (!$valid) {
            if ($user && $failedCount + 1 >= 5) {
                $pdo->prepare("UPDATE users SET locked_until = (NOW() + INTERVAL 15 MINUTE) WHERE id = ?")->execute([$user['id']]);
            }
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['last_activity'] = time();
        $pdo->prepare('UPDATE users SET last_login_at = NOW(), locked_until = NULL WHERE id = ?')->execute([$user['id']]);
        self::$user = $user;
        self::$permissions = null;
        AuditService::log('login', 'authentication', (int) $user['id'], 'User signed in.');
        return true;
    }

    public static function check(): bool
    {
        if (empty($_SESSION['user_id'])) {
            return false;
        }
        $lifetime = (int) config('app.session.lifetime', 120) * 60;
        if (!empty($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > $lifetime) {
            self::logout();
            return false;
        }
        $_SESSION['last_activity'] = time();
        return self::user() !== null;
    }

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $statement = Database::connection()->prepare('SELECT u.id, u.role_id, u.employee_id, u.customer_id, u.name, u.email, u.status, u.must_change_password, u.last_login_at, r.name AS role_name, r.slug AS role_slug FROM users u JOIN roles r ON r.id = u.role_id WHERE u.id = ? AND u.status = \'active\' LIMIT 1');
        $statement->execute([$_SESSION['user_id']]);
        self::$user = $statement->fetch() ?: null;
        return self::$user;
    }

    public static function can(string $permission): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        if (self::hasRole('super_admin')) {
            return true;
        }
        if (self::$permissions === null) {
            $pdo = Database::connection();
            $statement = $pdo->prepare('SELECT DISTINCT p.slug FROM permissions p JOIN role_permissions rp ON rp.permission_id=p.id JOIN user_roles ur ON ur.role_id=rp.role_id WHERE ur.user_id=?');
            $statement->execute([$user['id']]);
            self::$permissions = array_fill_keys($statement->fetchAll(PDO::FETCH_COLUMN), true);
            $exceptions = $pdo->prepare('SELECT p.slug,up.effect FROM user_permissions up JOIN permissions p ON p.id=up.permission_id WHERE up.user_id=?');
            $exceptions->execute([$user['id']]);
            foreach ($exceptions->fetchAll() as $exception) {
                if ($exception['effect'] === 'deny') {
                    unset(self::$permissions[$exception['slug']]);
                } else {
                    self::$permissions[$exception['slug']] = true;
                }
            }
        }
        return isset(self::$permissions[$permission]);
    }

    public static function hasRole(string $role): bool
    {
        $user = self::user();
        if (!$user) return false;
        $statement = Database::connection()->prepare('SELECT COUNT(*) FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? AND r.slug=?');
        $statement->execute([$user['id'], $role]);
        return (int) $statement->fetchColumn() > 0;
    }

    /** @return list<string> */
    public static function roleSlugs(): array
    {
        $user = self::user();
        if (!$user) return [];
        $statement = Database::connection()->prepare('SELECT r.slug FROM user_roles ur JOIN roles r ON r.id=ur.role_id WHERE ur.user_id=? ORDER BY ur.is_primary DESC,r.name');
        $statement->execute([$user['id']]);
        return $statement->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function requireLogin(): void
    {
        if (!self::check()) {
            flash('warning', 'Please sign in to continue.');
            redirect('login');
        }
    }

    public static function requirePermission(string $permission): void
    {
        self::requireLogin();
        if (!self::can($permission)) {
            http_response_code(403);
            View::render('errors/403', ['title' => 'Access denied']);
            exit;
        }
    }

    public static function changePassword(string $current, string $new): bool
    {
        $user = self::user();
        if (!$user) {
            return false;
        }
        $statement = Database::connection()->prepare('SELECT password_hash FROM users WHERE id = ?');
        $statement->execute([$user['id']]);
        if (!password_verify($current, (string) $statement->fetchColumn())) {
            return false;
        }
        $hash = password_hash($new, PASSWORD_ARGON2ID);
        Database::connection()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?')->execute([$hash, $user['id']]);
        AuditService::log('password_changed', 'authentication', (int) $user['id'], 'User changed their password.');
        return true;
    }

    public static function logout(): void
    {
        if (!empty($_SESSION['user_id'])) {
            try {
                AuditService::log('logout', 'authentication', (int) $_SESSION['user_id'], 'User signed out.');
            } catch (\Throwable) {
            }
        }
        self::$user = null;
        self::$permissions = null;
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
