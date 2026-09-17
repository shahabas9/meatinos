<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use DateTimeImmutable;
use MeatinOS\Core\Auth;
use MeatinOS\Core\Database;
use PDO;
use Throwable;

final class EmployeeActivityService
{
    public static function touch(string $route): void
    {
        $employeeId = (int) (Auth::user()['employee_id'] ?? 0);
        if ($employeeId < 1) {
            return;
        }
        $route = mb_substr(preg_replace('/[^a-z0-9._-]/i', '', $route) ?: 'unknown', 0, 120);
        try {
            Database::connection()->prepare("INSERT INTO employee_activity_status (employee_id,last_activity_at,last_route,last_ip) VALUES (?,NOW(),?,?) ON DUPLICATE KEY UPDATE last_activity_at=NOW(),last_route=VALUES(last_route),last_ip=VALUES(last_ip)")
                ->execute([$employeeId, $route, request_ip()]);
        } catch (Throwable) {
            // Activity tracking is non-blocking while an update migration is pending.
        }
    }

    public static function markLocationPing(PDO $pdo, int $employeeId): void
    {
        $pdo->prepare("INSERT INTO employee_activity_status (employee_id,last_activity_at,last_route,last_ip,last_location_ping_at) VALUES (?,NOW(),'sales.tracking',?,NOW()) ON DUPLICATE KEY UPDATE last_activity_at=NOW(),last_route='sales.tracking',last_ip=VALUES(last_ip),last_location_ping_at=NOW()")
            ->execute([$employeeId, request_ip()]);
    }

    public static function generateInactivityReminders(PDO $pdo): int
    {
        $settings = [];
        $rows = $pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('office_hours_start','office_hours_end','employee_inactivity_minutes')")->fetchAll();
        foreach ($rows as $row) {
            $settings[(string) $row['setting_key']] = (string) $row['setting_value'];
        }
        $start = preg_match('/^\d{2}:\d{2}$/', $settings['office_hours_start'] ?? '') ? $settings['office_hours_start'] : '09:00';
        $end = preg_match('/^\d{2}:\d{2}$/', $settings['office_hours_end'] ?? '') ? $settings['office_hours_end'] : '18:00';
        $minutes = max(5, min(240, (int) ($settings['employee_inactivity_minutes'] ?? 30)));
        $now = new DateTimeImmutable('now');
        if ((int) $now->format('N') === 7 || $now->format('H:i') < $start || $now->format('H:i') > $end) {
            return 0;
        }

        $todayStart = new DateTimeImmutable($now->format('Y-m-d') . ' ' . $start . ':00');
        if ($now->getTimestamp() - $todayStart->getTimestamp() < $minutes * 60) {
            return 0;
        }
        $employees = $pdo->query("SELECT e.id,e.full_name,eas.last_activity_at,eas.last_reminder_at,
            (SELECT MAX(t.updated_at) FROM employee_tasks t WHERE t.employee_id=e.id AND t.task_date=CURDATE()) task_activity_at,
            (SELECT MAX(a.check_in) FROM attendance a WHERE a.employee_id=e.id AND a.attendance_date=CURDATE() AND a.status NOT IN ('absent','leave')) attendance_activity_at,
            (SELECT MAX(s.last_ping_at) FROM salesman_tracking_sessions s WHERE s.employee_id=e.id AND DATE(s.started_at)=CURDATE()) location_activity_at
            FROM employees e
            JOIN users u ON u.employee_id=e.id AND u.status='active'
            LEFT JOIN employee_activity_status eas ON eas.employee_id=e.id
            WHERE e.status='active' AND e.shift_code='office'
              AND NOT EXISTS (SELECT 1 FROM attendance ax WHERE ax.employee_id=e.id AND ax.attendance_date=CURDATE() AND ax.status IN ('absent','leave'))
            ORDER BY e.id")->fetchAll();

        $created = 0;
        $cutoff = $now->getTimestamp() - $minutes * 60;
        foreach ($employees as $employee) {
            $last = $todayStart->getTimestamp();
            foreach (['last_activity_at','task_activity_at','attendance_activity_at','location_activity_at'] as $field) {
                $timestamp = !empty($employee[$field]) ? strtotime((string) $employee[$field]) : false;
                if ($timestamp !== false) {
                    $last = max($last, $timestamp);
                }
            }
            if ($last > $cutoff) {
                continue;
            }
            $lastReminder = !empty($employee['last_reminder_at']) ? strtotime((string) $employee['last_reminder_at']) : false;
            if ($lastReminder !== false && $lastReminder > $cutoff) {
                continue;
            }
            $reference = (string) $employee['id'];
            $existing = $pdo->prepare("SELECT COUNT(*) FROM alerts WHERE source_type='employee_inactivity' AND source_reference=? AND status IN ('open','acknowledged')");
            $existing->execute([$reference]);
            if ((int) $existing->fetchColumn() > 0) {
                continue;
            }
            $inactiveMinutes = max($minutes, (int) floor(($now->getTimestamp() - $last) / 60));
            $pdo->prepare("INSERT INTO alerts (severity,title,message,source_type,source_reference,triggered_at,status) VALUES ('warning','Employee inactivity reminder',?,'employee_inactivity',?,NOW(),'open')")
                ->execute([(string) $employee['full_name'] . ' has no recorded system activity, task update, check-in, or location ping for ' . $inactiveMinutes . ' minutes during office hours.', $reference]);
            $pdo->prepare("INSERT INTO employee_activity_status (employee_id,last_reminder_at,reminder_count) VALUES (?,NOW(),1) ON DUPLICATE KEY UPDATE last_reminder_at=NOW(),reminder_count=reminder_count+1")
                ->execute([(int) $employee['id']]);
            $created++;
        }
        return $created;
    }
}
