<?php
declare(strict_types=1);

namespace Ffs2\Auth;

use Ffs2\Core\Session;

/**
 * Utilisateur connecté au site (session web). L'identité ne dépend jamais de l'IP.
 */
final class Auth
{
    private ?array $user = null;
    private bool $loaded = false;

    /** @param \Closure(): UserRepository $users création différée : pas d'accès à la base sans session */
    public function __construct(private Session $session, private \Closure $users)
    {
    }

    private function users(): UserRepository
    {
        return ($this->users)();
    }

    public function user(): ?array
    {
        if (!$this->loaded) {
            $this->loaded = true;
            $id = $this->session->get('user_id');
            if (is_int($id)) {
                $user = $this->users()->find($id);
                // un compte suspendu perd immédiatement sa session
                $this->user = $user !== null && $user['status'] === 'active' ? $user : null;
                if ($this->user === null) {
                    $this->session->remove('user_id');
                }
            }
        }
        return $this->user;
    }

    public function id(): ?int
    {
        $user = $this->user();
        return $user === null ? null : (int) $user['id'];
    }

    public function isAdmin(): bool
    {
        return ($this->user()['role'] ?? '') === 'admin';
    }

    public function login(array $user): void
    {
        $this->session->regenerate();
        $this->session->set('user_id', (int) $user['id']);
        $this->users()->update((int) $user['id'], ['last_login_at' => now_utc()]);
        $this->user = $user;
        $this->loaded = true;
    }

    public function logout(): void
    {
        $this->session->destroy();
        $this->user = null;
    }

    /** Recharge l'utilisateur après modification */
    public function refresh(): void
    {
        $this->loaded = false;
        $this->user = null;
    }
}
