<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use MeatinOS\Core\Auth;
use MeatinOS\Core\Database;
use PDO;

final class AiInsightService
{
    private const MODULE_PERMISSIONS = ['inventory'=>'inventory.view','iot'=>'iot.view','finance'=>'finance.view','maintenance'=>'maintenance.view','quality'=>'quality.view','production'=>'production.view'];

    public function refresh(): array
    {
        $candidates = [];
        if (Auth::can('inventory.view')) {
            $row = $this->row("SELECT COUNT(*) count,COALESCE(SUM(available_quantity),0) quantity FROM inventory_lots WHERE available_quantity>0 AND status IN ('available','allocated') AND expiry_date BETWEEN CURDATE() AND CURDATE()+INTERVAL 3 DAY");
            if ((int) $row['count'] > 0) $candidates[] = $this->insight('inventory-expiry-3d','expiry','inventory','critical','FEFO expiry risk',"{$row['count']} lots / {$row['quantity']} units expire within 3 days.",'Review allocations and prioritize compliant dispatch or approved disposition.',$row);
        }
        if (Auth::can('iot.view')) {
            $row = $this->row("SELECT SUM(status='critical') critical,SUM(status='offline') offline FROM sensors");
            if ((int) $row['critical'] + (int) $row['offline'] > 0) $candidates[] = $this->insight('iot-sensor-exceptions','temperature','iot','critical','Cold-chain sensor exception',"{$row['critical']} critical and {$row['offline']} offline sensors need review.",'Verify product temperature with a calibrated device and follow the quality-hold SOP.',$row);
        }
        if (Auth::can('finance.view')) {
            $row = $this->row("SELECT COUNT(*) count,COALESCE(SUM(balance_amount),0) amount FROM invoices WHERE due_date<CURDATE() AND balance_amount>0 AND status IN ('issued','partial','overdue')");
            if ((int) $row['count'] > 0) $candidates[] = $this->insight('finance-overdue-ar','receivables','finance','warning','Overdue customer receivables',"{$row['count']} invoices totaling " . money($row['amount']) . ' are overdue.','Review customer credit exposure and assign collection follow-up before new approvals.',$row);
        }
        if (Auth::can('maintenance.view')) {
            $row = $this->row("SELECT COUNT(*) count,COALESCE(SUM(downtime_minutes),0) downtime FROM maintenance_tickets WHERE priority IN ('critical','high') AND status NOT IN ('completed','cancelled')");
            if ((int) $row['count'] > 0) $candidates[] = $this->insight('maintenance-priority-open','maintenance','maintenance','warning','High-priority maintenance backlog',"{$row['count']} high/critical tickets remain open.",'Confirm isolation, ownership, spare availability and recovery target.',$row);
        }
        if (Auth::can('quality.view')) {
            $row = $this->row("SELECT COUNT(*) count FROM quality_holds WHERE status='open'");
            if ((int) $row['count'] > 0) $candidates[] = $this->insight('quality-open-holds','quality_hold','quality','critical','Open quality holds',"{$row['count']} quality holds are awaiting disposition.",'Keep affected lots blocked until an authorized quality manager records disposition.',$row);
        }
        if (Auth::can('production.view')) {
            $expected = (float) ($this->row("SELECT COALESCE((SELECT setting_value FROM settings WHERE setting_key='expected_yield_percent'),70) expected")['expected'] ?? 70);
            $row = $this->row("SELECT COUNT(*) count,COALESCE(AVG(yield_percent),0) average_yield FROM production_batches WHERE production_date>=CURDATE()-INTERVAL 7 DAY AND status='completed' AND yield_percent>0 AND yield_percent<" . (float) $expected);
            if ((int) $row['count'] > 0) $candidates[] = $this->insight('production-low-yield-7d','yield','production','warning','Yield below target',"{$row['count']} completed batches were below the {$expected}% target.",'Review input weight, rejects, line loss and batch-level yield records.',$row);
        }
        $this->persist($candidates);
        return $this->visible();
    }

    public function visible(): array
    {
        $allowed = array_keys(array_filter(self::MODULE_PERMISSIONS, static fn (string $permission): bool => Auth::can($permission)));
        if (!$allowed) return [];
        $marks = implode(',', array_fill(0, count($allowed), '?'));
        $statement = Database::connection()->prepare("SELECT * FROM ai_insights WHERE module IN ({$marks}) AND status='open' ORDER BY FIELD(severity,'critical','warning','info'),generated_at DESC LIMIT 20");
        $statement->execute($allowed);
        return $statement->fetchAll(PDO::FETCH_ASSOC);
    }

    public function canManageModule(string $module): bool
    {
        return isset(self::MODULE_PERMISSIONS[$module]) && Auth::can(self::MODULE_PERMISSIONS[$module]);
    }

    private function insight(string $key,string $type,string $module,string $severity,string $title,string $summary,string $action,array $evidence): array
    {
        return compact('key','type','module','severity','title','summary','action','evidence');
    }

    private function persist(array $insights): void
    {
        $statement = Database::connection()->prepare("INSERT INTO ai_insights (insight_key,insight_type,module,severity,title,summary,recommended_action,evidence_json,source) VALUES (?,?,?,?,?,?,?,?, 'rule') ON DUPLICATE KEY UPDATE severity=VALUES(severity),title=VALUES(title),summary=VALUES(summary),recommended_action=VALUES(recommended_action),evidence_json=VALUES(evidence_json),generated_at=CURRENT_TIMESTAMP");
        foreach ($insights as $item) $statement->execute([$item['key'],$item['type'],$item['module'],$item['severity'],$item['title'],$item['summary'],$item['action'],json_encode($item['evidence'],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
    }

    private function row(string $sql): array
    {
        return Database::connection()->query($sql)->fetch(PDO::FETCH_ASSOC) ?: [];
    }
}
