<?php
declare(strict_types=1);

namespace Ffs2\Core;

/**
 * Limitation des tentatives (connexion web, connexion ffs2play, mot de passe oublié)
 * par adresse IP et par identifiant sur une fenêtre glissante.
 */
final class RateLimiter
{
    public function __construct(
        private Database $db,
        private int $window = 900,
        private int $maxPerIp = 20,
        private int $maxPerIdentifier = 5
    ) {
    }

    public function tooManyAttempts(string $scope, string $ip, string $identifier): bool
    {
        $since = now_utc(-$this->window);
        $byIp = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM {login_attempts} WHERE scope = :scope AND ip = :ip AND attempted_at >= :since',
            ['scope' => $scope, 'ip' => $ip, 'since' => $since]
        );
        $byIdentifier = (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM {login_attempts} WHERE scope = :scope AND identifier = :identifier AND attempted_at >= :since',
            ['scope' => $scope, 'identifier' => mb_strtolower($identifier), 'since' => $since]
        );
        return $byIp >= $this->maxPerIp || $byIdentifier >= $this->maxPerIdentifier;
    }

    public function hit(string $scope, string $ip, string $identifier): void
    {
        $this->db->insert('login_attempts', [
            'scope' => $scope,
            'ip' => $ip,
            'identifier' => mb_substr(mb_strtolower($identifier), 0, 191),
            'attempted_at' => now_utc(),
        ]);
        // nettoyage opportuniste
        if (random_int(1, 50) === 1) {
            $this->db->execute('DELETE FROM {login_attempts} WHERE attempted_at < :before', ['before' => now_utc(-86400)]);
        }
    }

    /** Succès : on efface les échecs de l'identifiant */
    public function clear(string $scope, string $identifier): void
    {
        $this->db->execute(
            'DELETE FROM {login_attempts} WHERE scope = :scope AND identifier = :identifier',
            ['scope' => $scope, 'identifier' => mb_strtolower($identifier)]
        );
    }
}
