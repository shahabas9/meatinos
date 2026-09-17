<?php

declare(strict_types=1);

namespace MeatinOS\Services;

use MeatinOS\Core\Database;
use PDO;

final class AiSettingsService
{
    public function get(): array
    {
        $row = Database::connection()->query('SELECT * FROM ai_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC) ?: [];
        $row['enabled'] = (bool) ($row['enabled'] ?? false);
        $row['automatic_insights'] = (bool) ($row['automatic_insights'] ?? false);
        $row['configured'] = !empty($row['encrypted_api_key']);
        unset($row['encrypted_api_key']);
        return $row;
    }

    public function save(array $input, int $userId): array
    {
        $enabled = isset($input['enabled']) ? 1 : 0;
        $automatic = isset($input['automatic_insights']) ? 1 : 0;
        $model = trim((string) ($input['model'] ?? 'gpt-5.6'));
        $maxTokens = (int) ($input['max_output_tokens'] ?? 900);
        $budget = (float) ($input['monthly_budget_inr'] ?? 5000);
        $retention = (int) ($input['retention_days'] ?? 90);
        if (!preg_match('/^[a-zA-Z0-9._-]{2,100}$/', $model)) throw new \InvalidArgumentException('Enter a valid OpenAI model name.');
        if ($maxTokens < 100 || $maxTokens > 8000) throw new \InvalidArgumentException('Maximum output tokens must be between 100 and 8,000.');
        if ($budget < 0 || $budget > 10000000) throw new \InvalidArgumentException('Monthly budget must be between ₹0 and ₹1,00,00,000.');
        if ($retention < 7 || $retention > 365) throw new \InvalidArgumentException('Retention must be between 7 and 365 days.');

        $key = trim((string) ($input['api_key'] ?? ''));
        $pdo = Database::connection();
        if ($enabled && $key === '' && (int) $pdo->query("SELECT COUNT(*) FROM ai_settings WHERE id=1 AND encrypted_api_key IS NOT NULL")->fetchColumn() === 0) {
            throw new \InvalidArgumentException('Add an OpenAI API key before enabling Meatin AI.');
        }
        if ($key !== '') {
            if (!preg_match('/^sk-[A-Za-z0-9_-]{20,}$/', $key)) throw new \InvalidArgumentException('The OpenAI API key format is not valid.');
            $encrypted = (new SecretVault())->encrypt($key);
            $statement = $pdo->prepare('UPDATE ai_settings SET enabled=?,model=?,encrypted_api_key=?,key_last4=?,max_output_tokens=?,monthly_budget_inr=?,automatic_insights=?,retention_days=?,updated_by=? WHERE id=1');
            $statement->execute([$enabled,$model,$encrypted,substr($key,-4),$maxTokens,$budget,$automatic,$retention,$userId]);
        } else {
            $statement = $pdo->prepare('UPDATE ai_settings SET enabled=?,model=?,max_output_tokens=?,monthly_budget_inr=?,automatic_insights=?,retention_days=?,updated_by=? WHERE id=1');
            $statement->execute([$enabled,$model,$maxTokens,$budget,$automatic,$retention,$userId]);
        }
        return $this->get();
    }

    public function removeKey(int $userId): void
    {
        Database::connection()->prepare('UPDATE ai_settings SET enabled=0,encrypted_api_key=NULL,key_last4=NULL,updated_by=? WHERE id=1')->execute([$userId]);
    }

    public function apiKey(bool $requireEnabled = true): ?string
    {
        $row = Database::connection()->query('SELECT enabled,encrypted_api_key FROM ai_settings WHERE id=1')->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($requireEnabled && !(bool) $row['enabled']) || empty($row['encrypted_api_key'])) return null;
        return (new SecretVault())->decrypt((string) $row['encrypted_api_key']);
    }
}
