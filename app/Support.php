<?php

declare(strict_types=1);

function load_env(string $path): void
{
    if (!is_file($path)) {
        return;
    }
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        $value = trim($value, "\"'");
        if (getenv($key) === false) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
}

function env(string $key, mixed $default = null): mixed
{
    $value = getenv($key);
    return $value === false ? $default : $value;
}

function config(string $key, mixed $default = null): mixed
{
    $value = $GLOBALS['config'] ?? [];
    foreach (explode('.', $key) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }
    return $value;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function url(string $route = 'dashboard', array $params = []): string
{
    $query = http_build_query(array_merge(['route' => $route], $params));
    return '?' . $query;
}

function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $file = BASE_PATH . '/public/assets/' . $relative;
    return 'assets/' . $relative . (is_file($file) ? '?v=' . filemtime($file) : '');
}

function redirect(string $route, array $params = []): never
{
    header('Location: ' . url($route, $params));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $flashes = $_SESSION['_flash'] ?? [];
    unset($_SESSION['_flash']);
    return is_array($flashes) ? $flashes : [];
}

function old(string $key, mixed $default = ''): mixed
{
    return $_SESSION['_old'][$key] ?? $default;
}

function money(float|int|string|null $amount): string
{
    $number = number_format((float) $amount, 2, '.', '');
    [$whole, $decimal] = array_pad(explode('.', $number, 2), 2, '00');
    $negative = str_starts_with($whole, '-');
    $digits = ltrim($whole, '-');
    if (strlen($digits) > 3) {
        $tail = substr($digits, -3);
        $head = substr($digits, 0, -3);
        $groups = [];
        while (strlen($head) > 2) {
            array_unshift($groups, substr($head, -2));
            $head = substr($head, 0, -2);
        }
        if ($head !== '') array_unshift($groups, $head);
        $digits = implode(',', $groups) . ',' . $tail;
    }
    return ($negative ? '-' : '') . '₹' . $digits . '.' . $decimal;
}

function gst_rate(): float
{
    static $rate = null;
    if ($rate !== null) return $rate;
    try {
        $statement = MeatinOS\Core\Database::connection()->prepare("SELECT setting_value FROM settings WHERE setting_key='gst_rate_percent'");
        $statement->execute();
        $rate = min(1.0,max(0.0,(float) ($statement->fetchColumn() ?: 5) / 100));
    } catch (Throwable) {
        $rate = 0.05;
    }
    return $rate;
}

function human_status(string $status): string
{
    return ucwords(str_replace('_', ' ', $status));
}

function status_class(string $status): string
{
    return match (strtolower($status)) {
        'approved', 'active', 'completed', 'delivered', 'paid', 'passed', 'accepted', 'available', 'acknowledged', 'on_time' => 'success',
        'pending', 'draft', 'scheduled', 'in_progress', 'partial', 'loading', 'in_transit', 'warning', 'due' => 'warning',
        'rejected', 'cancelled', 'failed', 'overdue', 'critical', 'breakdown', 'inactive' => 'danger',
        default => 'secondary',
    };
}

function request_ip(): string
{
    return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'cli'), 0, 45);
}
