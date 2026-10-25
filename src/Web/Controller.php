<?php
declare(strict_types=1);

namespace Ffs2\Web;

use Ffs2\Core\App;
use Ffs2\Core\Request;

/**
 * Base des contrôleurs de pages : rendu, redirections, contrôle d'accès, CSRF.
 */
abstract class Controller
{
    public function __construct(protected App $app)
    {
    }

    protected function page(string $template, array $data = [], int $status = 200): void
    {
        $this->app->view()->page($template, $data + ['title' => ''], $status);
    }

    protected function redirect(string $path, array $query = []): never
    {
        header('Location: ' . url($path, $query), true, 303);
        exit;
    }

    protected function back(string $fallback = '/'): never
    {
        $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        // uniquement vers le site lui-même
        if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === preg_replace('/:\d+$/', '', $host)) {
            header('Location: ' . $referer, true, 303);
            exit;
        }
        $this->redirect($fallback);
    }

    protected function flash(string $type, string $message): void
    {
        $this->app->session()->flash($type, $message);
    }

    /** Arrête la requête si le jeton CSRF est absent ou faux */
    protected function checkCsrf(): void
    {
        if (!$this->app->csrf()->check(Request::post('_csrf'))) {
            $this->page('error', ['title' => 'Session expirée', 'message' => 'Le formulaire a expiré, veuillez réessayer.'], 419);
            exit;
        }
    }

    protected function requireUser(): array
    {
        $user = $this->app->auth()->user();
        if ($user === null) {
            $this->app->session()->set('after_login', (string) ($_SERVER['REQUEST_URI'] ?? ''));
            $this->flash('info', 'Connectez-vous pour accéder à cette page.');
            $this->redirect('/login');
        }
        return $user;
    }

    protected function requireAdmin(): array
    {
        $user = $this->requireUser();
        if ($user['role'] !== 'admin') {
            $this->forbidden();
        }
        return $user;
    }

    protected function forbidden(): never
    {
        $this->page('error', ['title' => 'Accès refusé', 'message' => "Vous n'avez pas accès à cette page."], 403);
        exit;
    }

    protected function notFound(): never
    {
        $this->page('error', ['title' => 'Page introuvable', 'message' => "Cette page n'existe pas ou n'est plus disponible."], 404);
        exit;
    }

    protected function json(array $data, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}
