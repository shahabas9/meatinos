<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use MeatinOS\Core\Database;
use PDO;

final class NumberingService
{
    public static function next(string $documentType, ?int $plantId = null, ?string $fallbackPrefix = null): string
    {
        if (!preg_match('/^[a-z_]+$/', $documentType)) throw new \InvalidArgumentException('Invalid document type.');
        $pdo = Database::connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) $pdo->beginTransaction();
        try {
            if (!$plantId) $plantId = (int) $pdo->query("SELECT id FROM plants WHERE status='active' ORDER BY id LIMIT 1")->fetchColumn() ?: null;
            $financialYear = date('Y') . '-' . ((int) date('Y') + 1);
            $statement = $pdo->prepare('SELECT * FROM number_sequences WHERE document_type=? AND financial_year=? AND ((plant_id=? ) OR (plant_id IS NULL AND ? IS NULL)) AND status=\'active\' LIMIT 1 FOR UPDATE');
            $statement->execute([$documentType, $financialYear, $plantId, $plantId]);
            $sequence = $statement->fetch();
            if (!$sequence) {
                $prefix = strtoupper(substr($fallbackPrefix ?: implode('', array_map(static fn (string $part): string => $part[0] ?? '', explode('_', $documentType))), 0, 12));
                $insert = $pdo->prepare("INSERT INTO number_sequences (plant_id,document_type,prefix,financial_year,next_number,padding,status) VALUES (?,?,?,?,1,6,'active')");
                $insert->execute([$plantId, $documentType, $prefix, $financialYear]);
                $sequence = ['id' => (int) $pdo->lastInsertId(), 'prefix' => $prefix, 'next_number' => 1, 'padding' => 6];
            }
            $number = (int) $sequence['next_number'];
            $pdo->prepare('UPDATE number_sequences SET next_number=next_number+1 WHERE id=?')->execute([$sequence['id']]);
            $result = $sequence['prefix'] . '-' . date('Y') . '-' . str_pad((string) $number, (int) $sequence['padding'], '0', STR_PAD_LEFT);
            if ($ownsTransaction) $pdo->commit();
            return $result;
        } catch (\Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) $pdo->rollBack();
            throw $exception;
        }
    }
}
