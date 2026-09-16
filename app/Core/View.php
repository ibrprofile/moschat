<?php

declare(strict_types=1);

namespace MosChat\Core;

final class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::renderPartial($view, $data);
        if ($layout === null) {
            return $content;
        }
        return self::renderPartial($layout, array_merge($data, ['content' => $content]));
    }

    public static function renderPartial(string $view, array $data = []): string
    {
        $path = base_path('app/Views/' . str_replace('.', '/', $view) . '.php');
        if (!is_file($path)) {
            throw new \RuntimeException("View not found: {$view}");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $path;
        return (string) ob_get_clean();
    }
}
