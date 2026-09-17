<?php

declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use MeatinOS\Core\Database;
use MeatinOS\Services\MigrationService;

$pdo = Database::connection();
MigrationService::apply($pdo, static function (string $status, string $name): void {
    echo '[' . strtoupper($status) . "] {$name}\n";
});

echo "Database migrations are current.\n";
