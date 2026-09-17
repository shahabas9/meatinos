<?php

declare(strict_types=1);

namespace MeatinOS\Core;

final class Validator
{
    public static function validate(array $fields, array $input): array
    {
        $data = [];
        $errors = [];
        foreach ($fields as $name => $field) {
            if (!empty($field['readonly']) || ($field['type'] ?? '') === 'display') {
                continue;
            }
            $value = $input[$name] ?? null;
            if (is_string($value)) {
                $value = trim($value);
            }
            if (!empty($field['required']) && ($value === null || $value === '')) {
                $errors[$name] = ($field['label'] ?? $name) . ' is required.';
                continue;
            }
            if ($value === '' || $value === null) {
                $data[$name] = null;
                continue;
            }
            $type = $field['type'] ?? 'text';
            if (in_array($type, ['number', 'decimal'], true) && !is_numeric($value)) {
                $errors[$name] = ($field['label'] ?? $name) . ' must be numeric.';
                continue;
            }
            if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                $errors[$name] = 'Enter a valid email address.';
                continue;
            }
            if (isset($field['max']) && is_string($value) && mb_strlen($value) > (int) $field['max']) {
                $errors[$name] = ($field['label'] ?? $name) . ' is too long.';
                continue;
            }
            if (isset($field['options']) && !array_key_exists((string) $value, $field['options'])) {
                $errors[$name] = 'Select a valid ' . strtolower((string) ($field['label'] ?? $name)) . '.';
                continue;
            }
            if ($type === 'datetime-local') {
                $value = str_replace('T', ' ', (string) $value);
                if (strlen($value) === 16) {
                    $value .= ':00';
                }
            }
            $data[$name] = $value;
        }
        return [$data, $errors];
    }
}

