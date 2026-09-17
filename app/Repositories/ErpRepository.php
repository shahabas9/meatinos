<?php

declare(strict_types=1);

namespace MeatinOS\Repositories;

use MeatinOS\Core\Database;
use PDO;

final class ErpRepository
{
    private PDO $pdo;

    public function __construct()
    {
        $this->pdo = Database::connection();
    }

    public function paginate(array $module, string $query, int $page = 1, int $perPage = 15, ?array $scope = null): array
    {
        $table = $this->identifier((string) $module['table']);
        $searchColumns = array_map([$this, 'identifier'], $module['search'] ?? []);
        $where = [];
        $params = [];
        if ($query !== '' && $searchColumns) {
            $parts = [];
            foreach ($searchColumns as $index => $column) {
                $key = ':search' . $index;
                $parts[] = "`{$column}` LIKE {$key}";
                $params[$key] = '%' . $query . '%';
            }
            $where[] = '(' . implode(' OR ', $parts) . ')';
        }
        if ($scope && isset($scope['column'])) {
            $column = $this->identifier((string) $scope['column']);
            $where[] = "`{$column}` = :scope";
            $params[':scope'] = $scope['value'];
        } elseif ($scope && isset($scope['sql'])) {
            $where[] = '(' . $scope['sql'] . ')';
            $params[':scope'] = $scope['value'];
        }
        $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $count = $this->pdo->prepare("SELECT COUNT(*) FROM `{$table}`{$whereSql}");
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $page = max(1, min($page, max(1, (int) ceil($total / $perPage))));
        $offset = ($page - 1) * $perPage;
        $orderBy = $this->orderBy((string) ($module['order_by'] ?? 'id DESC'));
        $statement = $this->pdo->prepare("SELECT * FROM `{$table}`{$whereSql} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}");
        $statement->execute($params);
        return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'pages' => max(1, (int) ceil($total / $perPage))];
    }

    public function find(array $module, int $id, ?array $scope = null): ?array
    {
        $table = $this->identifier((string) $module['table']);
        $sql = "SELECT * FROM `{$table}` WHERE id = :id";
        $params = [':id' => $id];
        if ($scope && isset($scope['column'])) {
            $column = $this->identifier((string) $scope['column']);
            $sql .= " AND `{$column}` = :scope";
            $params[':scope'] = $scope['value'];
        } elseif ($scope && isset($scope['sql'])) {
            $sql .= ' AND (' . $scope['sql'] . ')';
            $params[':scope'] = $scope['value'];
        }
        $statement = $this->pdo->prepare($sql . ' LIMIT 1');
        $statement->execute($params);
        return $statement->fetch() ?: null;
    }

    public function insert(array $module, array $data): int
    {
        $table = $this->identifier((string) $module['table']);
        $columns = array_map([$this, 'identifier'], array_keys($data));
        $quoted = array_map(static fn (string $column): string => "`{$column}`", $columns);
        $placeholders = array_map(static fn (string $column): string => ':' . $column, $columns);
        $statement = $this->pdo->prepare("INSERT INTO `{$table}` (" . implode(',', $quoted) . ') VALUES (' . implode(',', $placeholders) . ')');
        $statement->execute(array_combine($placeholders, array_values($data)) ?: []);
        return (int) $this->pdo->lastInsertId();
    }

    public function update(array $module, int $id, array $data, ?array $scope = null): void
    {
        $table = $this->identifier((string) $module['table']);
        $columns = array_map([$this, 'identifier'], array_keys($data));
        $sets = array_map(static fn (string $column): string => "`{$column}` = :{$column}", $columns);
        $params = array_combine(array_map(static fn (string $column): string => ':' . $column, $columns), array_values($data)) ?: [];
        $params[':id'] = $id;
        $sql = "UPDATE `{$table}` SET " . implode(', ', $sets) . ' WHERE id = :id';
        if ($scope && isset($scope['column'])) {
            $column = $this->identifier((string) $scope['column']);
            $sql .= " AND `{$column}` = :scope";
            $params[':scope'] = $scope['value'];
        } elseif ($scope && isset($scope['sql'])) {
            $sql .= ' AND (' . $scope['sql'] . ')';
            $params[':scope'] = $scope['value'];
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
    }

    public function delete(array $module, int $id, ?array $scope = null): void
    {
        $table = $this->identifier((string) $module['table']);
        $sql = "DELETE FROM `{$table}` WHERE id = :id";
        $params = [':id' => $id];
        if ($scope && isset($scope['column'])) {
            $column = $this->identifier((string) $scope['column']);
            $sql .= " AND `{$column}` = :scope";
            $params[':scope'] = $scope['value'];
        } elseif ($scope && isset($scope['sql'])) {
            $sql .= ' AND (' . $scope['sql'] . ')';
            $params[':scope'] = $scope['value'];
        }
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);
        if ($statement->rowCount() !== 1) {
            throw new \RuntimeException('Record not found or no longer available.', 404);
        }
    }

    public function archive(array $module, int $id): void
    {
        $table = $this->identifier((string) $module['table']);
        $columns = $this->pdo->query("SHOW COLUMNS FROM `{$table}`")->fetchAll(PDO::FETCH_COLUMN);
        if (in_array('status', $columns, true)) {
            $statement = $this->pdo->prepare("UPDATE `{$table}` SET status = CASE WHEN status = 'inactive' THEN 'active' ELSE 'inactive' END WHERE id = ?");
            $statement->execute([$id]);
            return;
        }
        throw new \RuntimeException('This record cannot be archived.');
    }

    public function lookupOptions(array $module): array
    {
        $result = [];
        foreach ($module['fields'] as $name => $field) {
            if (($field['type'] ?? '') !== 'lookup') {
                continue;
            }
            $lookup = $field['lookup'];
            $table = $this->identifier((string) $lookup['table']);
            $value = $this->identifier((string) $lookup['value']);
            $label = $this->identifier((string) $lookup['label']);
            $sql = "SELECT `{$value}` AS option_value, `{$label}` AS option_label FROM `{$table}`";
            if (!empty($lookup['where'])) {
                $sql .= ' WHERE ' . $lookup['where'];
            }
            $sql .= " ORDER BY `{$label}`";
            $result[$name] = $this->pdo->query($sql)->fetchAll();
        }
        return $result;
    }

    private function identifier(string $identifier): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $identifier)) {
            throw new \InvalidArgumentException('Unsafe database identifier.');
        }
        return $identifier;
    }

    private function orderBy(string $orderBy): string
    {
        if (!preg_match('/^([A-Za-z_][A-Za-z0-9_]*)(\s+(ASC|DESC))?$/i', trim($orderBy), $matches)) {
            return '`id` DESC';
        }
        return '`' . $matches[1] . '` ' . strtoupper($matches[3] ?? 'ASC');
    }
}
