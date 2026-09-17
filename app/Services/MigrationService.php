<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use PDO;
use Throwable;

final class MigrationService
{
    private const LOCK_PREFIX = 'meatinos_migrate_';

    /**
     * Return the installed and bundled migration state without changing the schema.
     *
     * @return array{
     *   current:?string,
     *   latest:?string,
     *   applied:list<string>,
     *   pending:list<array{name:string,size:int,sha256:string}>,
     *   unknown_applied:list<string>,
     *   total:int
     * }
     */
    public static function status(PDO $pdo): array
    {
        $files = self::migrationFiles();
        $available = [];
        foreach ($files as $file) {
            if (!is_file($file) || !is_readable($file)) {
                throw new \RuntimeException('Migration file is not readable: ' . basename($file));
            }
            $checksum = hash_file('sha256', $file);
            $size = filesize($file);
            if ($checksum === false || $size === false) {
                throw new \RuntimeException('Unable to inspect migration file: ' . basename($file));
            }
            $available[] = [
                'name' => basename($file),
                'size' => (int) $size,
                'sha256' => $checksum,
            ];
        }

        $table = $pdo->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'schema_migrations'");
        $applied = (int) $table->fetchColumn() > 0
            ? $pdo->query('SELECT migration FROM schema_migrations ORDER BY applied_at, migration')->fetchAll(PDO::FETCH_COLUMN)
            : [];
        $applied = array_values(array_map('strval', $applied));
        $appliedMap = array_fill_keys($applied, true);
        $availableNames = array_column($available, 'name');
        $pending = array_values(array_filter($available, static fn (array $migration): bool => !isset($appliedMap[$migration['name']])));
        $knownApplied = array_values(array_filter($availableNames, static fn (string $name): bool => isset($appliedMap[$name])));

        return [
            'current' => $knownApplied ? $knownApplied[array_key_last($knownApplied)] : null,
            'latest' => $available ? $available[array_key_last($available)]['name'] : null,
            'applied' => $applied,
            'pending' => $pending,
            'unknown_applied' => array_values(array_diff($applied, $availableNames)),
            'total' => count($available),
        ];
    }

    /** @return list<string> Applied migration filenames. */
    public static function apply(PDO $pdo, ?callable $reporter = null): array
    {
        $lockName = self::acquireLock($pdo);
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (migration VARCHAR(190) PRIMARY KEY, applied_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            $applied = array_fill_keys($pdo->query('SELECT migration FROM schema_migrations')->fetchAll(PDO::FETCH_COLUMN), true);
            $completed = [];

            foreach (self::migrationFiles() as $file) {
                $name = basename($file);
                if (isset($applied[$name])) {
                    if ($reporter) $reporter('skip', $name);
                    continue;
                }
                if (!is_file($file) || !is_readable($file)) {
                    throw new \RuntimeException("Migration {$name} is not readable.");
                }
                $sql = trim((string) file_get_contents($file));
                if ($sql === '') throw new \RuntimeException("Migration {$name} is empty.");
                $statements = array_values(array_filter(array_map('trim', preg_split('/;\s*(?:\r?\n|$)/', $sql) ?: [])));
                try {
                    foreach ($statements as $statement) {
                        if (preg_match('/^ALTER\s+TABLE\s+([`\w]+)\s+ADD\s+(?:COLUMN\s+)?IF\s+NOT\s+EXISTS\s+([`\w]+)\s+(.*)$/is', $statement, $matches)) {
                            $table = trim($matches[1], '`');
                            $column = trim($matches[2], '`');
                            $columnDef = $matches[3];
                            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
                            $stmtCheck->execute([$table, $column]);
                            if ((int) $stmtCheck->fetchColumn() === 0) {
                                $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$columnDef}");
                            }
                            continue;
                        }
                        try {
                            $pdo->exec($statement);
                        } catch (Throwable $statementException) {
                            $msg = $statementException->getMessage();
                            if (str_contains($msg, 'Duplicate column name') || str_contains($msg, 'Duplicate key name')) {
                                continue;
                            }
                            throw $statementException;
                        }
                    }
                    $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)')->execute([$name]);
                    $completed[] = $name;
                    if ($reporter) $reporter('applied', $name);
                } catch (Throwable $exception) {
                    throw new \RuntimeException("Migration {$name} failed: " . $exception->getMessage(), 0, $exception);
                }
            }
            return $completed;
        } finally {
            self::releaseLock($pdo, $lockName);
        }
    }

    /** @return list<string> */
    private static function migrationFiles(): array
    {
        $files = glob(BASE_PATH . '/database/migrations/*.sql') ?: [];
        sort($files, SORT_STRING);
        return array_values($files);
    }

    private static function acquireLock(PDO $pdo): string
    {
        $database = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
        $lockName = self::LOCK_PREFIX . substr(hash('sha256', $database), 0, 40);
        $statement = $pdo->prepare('SELECT GET_LOCK(?, 0)');
        $statement->execute([$lockName]);
        if ((int) $statement->fetchColumn() !== 1) {
            throw new \RuntimeException('Another database update is already running. Wait for it to finish, then refresh this page.');
        }
        return $lockName;
    }

    private static function releaseLock(PDO $pdo, string $lockName): void
    {
        try {
            $statement = $pdo->prepare('SELECT RELEASE_LOCK(?)');
            $statement->execute([$lockName]);
        } catch (Throwable) {
            // MySQL also releases advisory locks when the connection closes.
        }
    }
}
