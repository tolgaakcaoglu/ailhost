<?php

declare(strict_types=1);

namespace Ailhost\Core;

final class View
{
    public static function render(string $view, array $data = []): string
    {
        $viewPath = AILHOST_ROOT . '/resources/views/' . $view . '.php';
        if (!is_file($viewPath)) {
            return 'View not found: ' . $view;
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $viewPath;
        return (string) ob_get_clean();
    }
}
