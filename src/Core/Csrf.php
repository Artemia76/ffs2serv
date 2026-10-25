<?php
declare(strict_types=1);

namespace Ffs2\Core;

/**
 * Jeton anti-CSRF lié à la session, exigé sur toutes les requêtes POST des pages web.
 */
final class Csrf
{
    public function __construct(private Session $session)
    {
    }

    public function token(): string
    {
        $token = $this->session->get('_csrf');
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->session->set('_csrf', $token);
        }
        return $token;
    }

    public function check(?string $token): bool
    {
        $expected = $this->session->get('_csrf');
        return is_string($expected) && is_string($token) && hash_equals($expected, $token);
    }
}
