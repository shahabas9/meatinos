<?php

declare(strict_types=1);

namespace MeatinOS\Core;

final class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['_csrf'])) {
            $_SESSION['_csrf'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['_csrf'];
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function verify(?string $token): void
    {
        if (!$token || !hash_equals(self::token(), $token)) {
            http_response_code(403);
            throw new \RuntimeException('Your session security token expired. Refresh the page and try again.', 403);
        }
    }
}
