<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use MeatinOS\Core\Database;

final class AuditService
{
    public static function log(string $event, string $module, ?int $recordId, string $description, ?array $old = null, ?array $new = null): void
    {
        $statement = Database::connection()->prepare(
            'INSERT INTO audit_logs (user_id, event, module, record_id, description, old_values, new_values, ip_address)
             VALUES (:user_id, :event, :module, :record_id, :description, :old_values, :new_values, :ip_address)'
        );
        $statement->execute([
            'user_id' => $_SESSION['user_id'] ?? null,
            'event' => $event,
            'module' => $module,
            'record_id' => $recordId,
            'description' => $description,
            'old_values' => $old === null ? null : json_encode($old, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'new_values' => $new === null ? null : json_encode($new, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'ip_address' => request_ip(),
        ]);
    }
}

