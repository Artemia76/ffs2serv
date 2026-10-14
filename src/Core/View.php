<?php
declare(strict_types=1);

namespace Ffs2\Core;

/**
 * Templates PHP de templates/, rendus dans templates/layout.php.
 * Toute donnée affichée passe par e() (helpers.php).
 */
final class View
{
    public function __construct(private string $dir = FFS2_ROOT . '/templates')
    {
    }

    /** Page complète dans la mise en page */
    public function page(string $template, array $data = [], int $status = 200): void
    {
        http_response_code($status);
        $content = $this->render($template, $data);
        echo $this->render('layout', $data + ['content' => $content]);
    }

    public function render(string $template, array $data = []): string
    {
        $file = $this->dir . '/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("Template introuvable : $template");
        }
        $view = $this;
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    public function partial(string $template, array $data = []): string
    {
        return $this->render($template, $data);
    }
}
