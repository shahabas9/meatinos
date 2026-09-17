<?php

declare(strict_types=1);

namespace MeatinOS\Core;

final class View
{
    public static function render(string $view, array $data = [], bool $layout = true): void
    {
        $path = BASE_PATH . '/app/Views/' . $view . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException('View not found: ' . $view);
        }
        extract($data, EXTR_SKIP);
        if (!$layout) {
            require $path;
            return;
        }
        ob_start();
        require $path;
        $content = (string) ob_get_clean();
        require BASE_PATH . '/app/Views/layout.php';
    }
}

