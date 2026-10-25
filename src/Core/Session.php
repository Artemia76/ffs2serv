<?php
declare(strict_types=1);

namespace Ffs2\Core;

/**
 * Session web : cookie HttpOnly / SameSite=Lax / Secure en HTTPS, messages flash.
 */
final class Session
{
    private bool $started = false;

    public function start(): void
    {
        if ($this->started || PHP_SAPI === 'cli' && !isset($_SERVER['REQUEST_METHOD'])) {
            return;
        }
        session_name('ffs2_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => Request::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
        $this->started = true;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $this->start();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $this->start();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void
    {
        $this->start();
        unset($_SESSION[$key]);
    }

    /** Nouvel identifiant de session (connexion / déconnexion) */
    public function regenerate(): void
    {
        $this->start();
        session_regenerate_id(true);
    }

    public function destroy(): void
    {
        $this->start();
        $_SESSION = [];
        session_regenerate_id(true);
    }

    public function flash(string $type, string $message): void
    {
        $messages = $this->get('_flash', []);
        $messages[] = ['type' => $type, 'message' => $message];
        $this->set('_flash', $messages);
    }

    /** @return array<int, array{type: string, message: string}> */
    public function takeFlash(): array
    {
        $messages = $this->get('_flash', []);
        $this->remove('_flash');
        return $messages;
    }
}
