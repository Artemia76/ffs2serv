<?php
declare(strict_types=1);

namespace Ffs2\Auth;

use Ffs2\Core\Database;

/**
 * Jetons à usage unique envoyés par e-mail (validation d'adresse, mot de passe oublié).
 * Seul le haché SHA-256 est stocké.
 */
final class TokenRepository
{
    public function __construct(private Database $db)
    {
    }

    /** @return string jeton en clair, à placer dans le lien */
    public function create(int $userId, string $type, int $lifetime): string
    {
        $this->db->execute('DELETE FROM {user_tokens} WHERE user_id = :user AND type = :type', ['user' => $userId, 'type' => $type]);
        $token = bin2hex(random_bytes(32));
        $this->db->insert('user_tokens', [
            'user_id' => $userId,
            'type' => $type,
            'token_hash' => hash('sha256', $token),
            'expires_at' => now_utc($lifetime),
            'created_at' => now_utc(),
        ]);
        return $token;
    }

    /** Consomme un jeton valide et retourne l'identifiant de l'utilisateur */
    public function consume(string $token, string $type): ?int
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }
        $row = $this->db->fetch(
            'SELECT id, user_id FROM {user_tokens} WHERE token_hash = :hash AND type = :type AND expires_at > :now',
            ['hash' => hash('sha256', $token), 'type' => $type, 'now' => now_utc()]
        );
        if ($row === null) {
            return null;
        }
        $this->db->execute('DELETE FROM {user_tokens} WHERE id = :id', ['id' => $row['id']]);
        return (int) $row['user_id'];
    }

    /** Vérifie un jeton sans le consommer (affichage du formulaire de nouveau mot de passe) */
    public function isValid(string $token, string $type): bool
    {
        return preg_match('/^[0-9a-f]{64}$/', $token) === 1 && $this->db->fetchValue(
            'SELECT COUNT(*) FROM {user_tokens} WHERE token_hash = :hash AND type = :type AND expires_at > :now',
            ['hash' => hash('sha256', $token), 'type' => $type, 'now' => now_utc()]
        ) > 0;
    }
}
