<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use PDO;
use Throwable;

final class ProductionInstaller
{
    private const ROLE_NAMES = [
        'super_admin' => 'Super Admin', 'managing_director' => 'Managing Director', 'finance_manager' => 'Finance Manager',
        'general_manager' => 'General Manager', 'plant_manager' => 'Plant Manager', 'production_manager' => 'Production Manager',
        'quality_manager' => 'Quality Manager', 'store_manager' => 'Store Manager', 'logistics_manager' => 'Logistics Manager',
        'sales_manager' => 'Sales Manager', 'hr_manager' => 'HR Manager', 'purchase_manager' => 'Purchase Manager',
        'maintenance_manager' => 'Maintenance Manager', 'hygiene_supervisor' => 'Hygiene Supervisor', 'driver' => 'Driver', 'customer' => 'Customer',
        'plant_supervisor' => 'Plant Supervisor', 'production_supervisor' => 'Production Supervisor', 'ev_supervisor' => 'EV Supervisor',
        'quality_inspector' => 'Quality Inspector', 'store_in_charge' => 'Store In Charge', 'dispatch_supervisor' => 'Dispatch Supervisor',
        'sales_staff' => 'Sales Staff', 'accountant' => 'Accountant', 'hr_staff' => 'HR Staff', 'purchase_staff' => 'Purchase Staff',
        'maintenance_technician' => 'Maintenance Technician', 'administrator' => 'Administrator', 'assistant_administrator' => 'Assistant Administrator',
    ];

    private const PERMISSION_MATRIX = [
        'super_admin' => ['*'],
        'managing_director' => ['dashboard.*','production.view','purchase.view','inventory.view','crm.view','sales.view','quality.view','logistics.view','finance.view','hr.view','maintenance.view','hygiene.view','iot.view','reports.*','audit.view'],
        'finance_manager' => ['dashboard.view','finance.*','sales.view','purchase.view','reports.view'],
        'general_manager' => ['dashboard.*','production.*','purchase.view','inventory.*','crm.view','sales.view','quality.*','logistics.*','finance.view','hr.view','maintenance.*','hygiene.*','iot.view','reports.*'],
        'plant_manager' => ['dashboard.*','production.*','purchase.view','inventory.*','quality.*','logistics.view','hr.view','maintenance.*','hygiene.*','iot.*','reports.*'],
        'production_manager' => ['dashboard.view','production.*','inventory.view','quality.view','maintenance.view','hygiene.view','iot.view','reports.view'],
        'quality_manager' => ['dashboard.view','production.view','inventory.view','quality.*','hygiene.*','iot.view','reports.view'],
        'store_manager' => ['dashboard.view','production.view','inventory.*','sales.view','logistics.view','iot.view','reports.view'],
        'logistics_manager' => ['dashboard.view','inventory.view','sales.view','logistics.*','iot.view','reports.view'],
        'sales_manager' => ['dashboard.view','crm.*','sales.*','inventory.view','logistics.view','finance.view','reports.view'],
        'hr_manager' => ['dashboard.view','hr.*','reports.view'],
        'purchase_manager' => ['dashboard.view','purchase.*','inventory.view','quality.view','reports.view'],
        'maintenance_manager' => ['dashboard.view','maintenance.*','inventory.view','iot.*','reports.view'],
        'hygiene_supervisor' => ['dashboard.view','hygiene.*','quality.view','inventory.view','iot.view','reports.view'],
        'driver' => ['dashboard.view','logistics.view'],
        'customer' => ['dashboard.view','sales.view','logistics.view'],
    ];

    public static function validate(array $input): array
    {
        $data = [
            'app_name' => trim((string) ($input['app_name'] ?? 'MeatinOS')),
            'app_url' => rtrim(trim((string) ($input['app_url'] ?? '')), '/'),
            'timezone' => trim((string) ($input['timezone'] ?? 'Asia/Kolkata')),
            'db_host' => trim((string) ($input['db_host'] ?? 'localhost')),
            'db_port' => (int) ($input['db_port'] ?? 3306),
            'db_name' => trim((string) ($input['db_name'] ?? '')),
            'db_user' => trim((string) ($input['db_user'] ?? '')),
            'db_password' => (string) ($input['db_password'] ?? ''),
            'admin_name' => trim((string) ($input['admin_name'] ?? '')),
            'admin_email' => mb_strtolower(trim((string) ($input['admin_email'] ?? ''))),
            'admin_password' => (string) ($input['admin_password'] ?? ''),
            'accounts_name' => trim((string) ($input['accounts_name'] ?? '')),
            'accounts_email' => mb_strtolower(trim((string) ($input['accounts_email'] ?? ''))),
            'accounts_password' => (string) ($input['accounts_password'] ?? ''),
            'hr_name' => trim((string) ($input['hr_name'] ?? '')),
            'hr_email' => mb_strtolower(trim((string) ($input['hr_email'] ?? ''))),
            'hr_password' => (string) ($input['hr_password'] ?? ''),
        ];
        $errors = [];
        if ($data['app_name'] === '' || mb_strlen($data['app_name']) > 100) $errors['app_name'] = 'Enter an application name up to 100 characters.';
        if (!filter_var($data['app_url'], FILTER_VALIDATE_URL) || !str_starts_with($data['app_url'], 'https://')) $errors['app_url'] = 'Enter the final HTTPS URL, for example https://erp.example.com.';
        if (!in_array($data['timezone'], timezone_identifiers_list(), true)) $errors['timezone'] = 'Enter a valid PHP timezone.';
        if ($data['db_host'] === '' || preg_match('/[\r\n]/', $data['db_host'])) $errors['db_host'] = 'Enter the cPanel database host.';
        if ($data['db_port'] < 1 || $data['db_port'] > 65535) $errors['db_port'] = 'Enter a valid database port.';
        if (!preg_match('/^[A-Za-z0-9_]+$/', $data['db_name'])) $errors['db_name'] = 'Database name may contain only letters, numbers, and underscores.';
        if ($data['db_user'] === '' || preg_match('/[\r\n]/', $data['db_user'])) $errors['db_user'] = 'Enter the cPanel database user.';
        if ($data['db_password'] === '' || preg_match('/[\r\n]/', $data['db_password']) || $data['db_password'] !== trim($data['db_password']) || preg_match('/^[\'\"]|[\'\"]$/', $data['db_password'])) {
            $errors['db_password'] = 'Enter the database password without leading/trailing spaces or quote characters.';
        }
        foreach (['admin', 'accounts', 'hr'] as $profile) {
            if ($data[$profile . '_name'] === '' || mb_strlen($data[$profile . '_name']) > 150) $errors[$profile . '_name'] = 'Enter a name up to 150 characters.';
            if (!filter_var($data[$profile . '_email'], FILTER_VALIDATE_EMAIL)) $errors[$profile . '_email'] = 'Enter a valid email address.';
            if (!self::strongPassword($data[$profile . '_password'])) $errors[$profile . '_password'] = 'Use 12+ characters with uppercase, lowercase, number, and symbol.';
        }
        if (count(array_unique([$data['admin_email'], $data['accounts_email'], $data['hr_email']])) !== 3) $errors['admin_email'] = 'Admin, Accounts, and HR must use different email addresses.';
        return [$data, $errors];
    }

    public static function install(PDO $pdo, array $data): void
    {
        $tableCount = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE()')->fetchColumn();
        if ($tableCount !== 0) {
            throw new \RuntimeException('The selected database is not empty. Create a new empty cPanel database to protect existing data.');
        }
        $schema = file_get_contents(BASE_PATH . '/database/schema.sql');
        if ($schema === false || trim($schema) === '') {
            throw new \RuntimeException('The database schema is missing from this package.');
        }
        $pdo->exec($schema);
        $pdo->beginTransaction();
        try {
            $roleIds = [];
            foreach (self::ROLE_NAMES as $slug => $name) {
                $roleIds[$slug] = self::insert($pdo, 'roles', ['name' => $name, 'slug' => $slug, 'description' => $name . ' access profile']);
            }
            $permissionIds = [];
            foreach (['dashboard','production','purchase','inventory','crm','sales','quality','logistics','finance','hr','maintenance','hygiene','iot','reports','settings','users','audit'] as $area) {
                foreach (['view', 'manage'] as $action) {
                    $slug = $area . '.' . $action;
                    $permissionIds[$slug] = self::insert($pdo, 'permissions', ['name' => ucfirst($area) . ' ' . $action, 'slug' => $slug]);
                }
            }
            foreach (self::PERMISSION_MATRIX as $role => $patterns) {
                foreach ($permissionIds as $permission => $permissionId) {
                    if (self::permissionAllowed($permission, $patterns)) {
                        self::insert($pdo, 'role_permissions', ['role_id' => $roleIds[$role], 'permission_id' => $permissionId]);
                    }
                }
            }
            $adminEmployee = self::insert($pdo, 'employees', [
                'employee_number' => 'EMP-0001', 'full_name' => $data['admin_name'], 'department' => 'Management',
                'job_title' => 'System Administrator', 'manager_id' => null, 'email' => $data['admin_email'],
                'join_date' => date('Y-m-d'), 'shift_code' => 'office', 'basic_salary' => 0, 'overtime_rate' => 0,
                'biometric_reference' => null, 'status' => 'active',
            ]);
            self::insert($pdo, 'users', [
                'role_id' => $roleIds['super_admin'], 'employee_id' => $adminEmployee, 'customer_id' => null,
                'name' => $data['admin_name'], 'email' => $data['admin_email'],
                'password_hash' => password_hash($data['admin_password'], PASSWORD_ARGON2ID), 'status' => 'active', 'must_change_password' => 0,
            ]);
            self::departmentUser($pdo, $roleIds['finance_manager'], 'EMP-0015', $data['accounts_name'], 'Finance', 'Finance Manager', $data['accounts_email'], $data['accounts_password']);
            self::departmentUser($pdo, $roleIds['hr_manager'], 'EMP-0016', $data['hr_name'], 'HR', 'HR Manager', $data['hr_email'], $data['hr_password']);

            foreach ([
                ['1000','Cash and Bank','asset'],['1100','Accounts Receivable','asset'],['1200','Inventory','asset'],['1300','Inventory Clearing','asset'],
                ['1400','Input GST Credit','asset'],['2000','Accounts Payable','liability'],['2050','Goods Received Not Invoiced','liability'],
                ['2100','Output GST Payable','liability'],['3000','Owner Equity','equity'],['4000','Poultry Sales Revenue','revenue'],
                ['5000','Live Bird Cost','expense'],['5100','Production Expense','expense'],['5200','Cost of Goods Sold','expense'],
            ] as $account) {
                self::insert($pdo, 'chart_of_accounts', ['account_code' => $account[0], 'account_name' => $account[1], 'account_type' => $account[2], 'parent_id' => null, 'status' => 'active']);
            }
            $adminId = (int) $pdo->query("SELECT id FROM users WHERE email=" . $pdo->quote($data['admin_email']))->fetchColumn();
            foreach (['company_name' => 'Meatin Farms and Foods LLP', 'country'=>'India', 'currency' => 'INR', 'timezone' => $data['timezone'], 'gst_rate_percent'=>'5', 'lot_strategy' => 'FEFO', 'temperature_alert_delay_minutes' => '5', 'storage_door_alert_minutes' => '2'] as $key => $value) {
                self::insert($pdo, 'settings', ['setting_key' => $key, 'setting_value' => $value, 'updated_by' => $adminId]);
            }
            $pdo->commit();
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
        MigrationService::apply($pdo);
        self::synchronizeUserRoles($pdo);
        self::verify($pdo);
    }

    public static function environment(array $data): string
    {
        $values = [
            'APP_NAME' => $data['app_name'], 'APP_ENV' => 'production', 'APP_DEBUG' => 'false',
            'APP_URL' => $data['app_url'], 'APP_FORCE_HTTPS' => 'true', 'APP_TIMEZONE' => $data['timezone'],
            'APP_KEY' => 'base64:' . base64_encode(random_bytes(32)),
            'SESSION_NAME' => 'meatinos_session', 'SESSION_LIFETIME' => '120',
            'DB_HOST' => $data['db_host'], 'DB_PORT' => (string) $data['db_port'], 'DB_DATABASE' => $data['db_name'],
            'DB_USERNAME' => $data['db_user'], 'DB_PASSWORD' => $data['db_password'],
        ];
        $lines = [];
        foreach ($values as $key => $value) {
            if (preg_match('/[\r\n]/', (string) $value)) throw new \RuntimeException('Environment values cannot contain line breaks.');
            $lines[] = $key . '=' . $value;
        }
        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    public static function writeEnvironment(string $contents): void
    {
        self::stageEnvironment($contents);
        self::activateEnvironment();
    }

    public static function stageEnvironment(string $contents): void
    {
        $temporary = BASE_PATH . '/.env.installing';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) throw new \RuntimeException('Cannot write .env. Grant the application directory temporary write permission and retry.');
        @chmod($temporary, 0600);
    }

    public static function activateEnvironment(): void
    {
        $target = BASE_PATH . '/.env';
        $temporary = BASE_PATH . '/.env.installing';
        if (!is_file($temporary) || !rename($temporary, $target)) {
            @unlink($temporary);
            throw new \RuntimeException('Cannot activate .env. Check ownership and directory permissions.');
        }
        @chmod($target, 0600);
    }

    public static function discardStagedEnvironment(): void
    {
        @unlink(BASE_PATH . '/.env.installing');
    }

    private static function departmentUser(PDO $pdo, int $roleId, string $number, string $name, string $department, string $title, string $email, string $password): void
    {
        $employeeId = self::insert($pdo, 'employees', [
            'employee_number' => $number, 'full_name' => $name, 'department' => $department, 'job_title' => $title,
            'manager_id' => 1, 'email' => $email, 'join_date' => date('Y-m-d'), 'shift_code' => 'office',
            'basic_salary' => 0, 'overtime_rate' => 0, 'biometric_reference' => null, 'status' => 'active',
        ]);
        self::insert($pdo, 'users', [
            'role_id' => $roleId, 'employee_id' => $employeeId, 'customer_id' => null, 'name' => $name, 'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_ARGON2ID), 'status' => 'active', 'must_change_password' => 1,
        ]);
    }

    private static function verify(PDO $pdo): void
    {
        $checks = [
            (int) $pdo->query('SELECT COUNT(*) FROM roles')->fetchColumn() === 29,
            (int) $pdo->query('SELECT COUNT(*) FROM permissions')->fetchColumn() >= 187,
            (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn() === 3,
            (int) $pdo->query('SELECT COUNT(*) FROM chart_of_accounts')->fetchColumn() === 19,
            (int) $pdo->query('SELECT COUNT(*) FROM user_roles')->fetchColumn() === 3,
        ];
        if (in_array(false, $checks, true)) throw new \RuntimeException('Installation verification failed. Restore the empty database and retry.');
    }

    private static function synchronizeUserRoles(PDO $pdo): void
    {
        $pdo->exec('INSERT IGNORE INTO user_roles (user_id,role_id,is_primary) SELECT id,role_id,1 FROM users');
    }

    private static function permissionAllowed(string $permission, array $patterns): bool
    {
        if (in_array('*', $patterns, true)) return true;
        foreach ($patterns as $pattern) {
            if ($pattern === $permission || (str_ends_with($pattern, '.*') && str_starts_with($permission, substr($pattern, 0, -1)))) return true;
        }
        return false;
    }

    private static function strongPassword(string $password): bool
    {
        return strlen($password) >= 12 && preg_match('/[A-Z]/', $password) && preg_match('/[a-z]/', $password) && preg_match('/\d/', $password) && preg_match('/[^A-Za-z0-9]/', $password);
    }

    private static function insert(PDO $pdo, string $table, array $row): int
    {
        if (!preg_match('/^[a-z_]+$/', $table)) throw new \InvalidArgumentException('Unsafe installer table.');
        $columns = array_keys($row);
        $sql = "INSERT INTO `{$table}` (`" . implode('`,`', $columns) . '`) VALUES (' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $statement = $pdo->prepare($sql);
        $statement->execute(array_values($row));
        return (int) $pdo->lastInsertId();
    }
}
