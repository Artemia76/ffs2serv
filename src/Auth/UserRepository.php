<?php
declare(strict_types=1);

namespace Ffs2\Auth;

use Ffs2\Core\Database;

/**
 * Comptes des pilotes. L'indicatif (callsign) sert d'identifiant dans ffs2play, de nom de
 * fichier côté client et de balise XML dans le whazzup : il est validé strictement.
 */
final class UserRepository
{
    /** Caractères autorisés par le client (gui/dialogprofils.cpp) + nom d'élément XML valide */
    public const CALLSIGN_PATTERN = '/^[A-Za-z_éèà][A-Za-z0-9_\-\.éèà]{1,31}$/u';

    public function __construct(private Database $db)
    {
    }

    public static function isValidCallsign(string $callsign): bool
    {
        return preg_match(self::CALLSIGN_PATTERN, $callsign) === 1;
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch('SELECT * FROM {users} WHERE id = :id', ['id' => $id]);
    }

    /** Recherche insensible à la casse (collation utf8mb4_unicode_ci) */
    public function findByCallsign(string $callsign): ?array
    {
        return $this->db->fetch('SELECT * FROM {users} WHERE callsign = :callsign', ['callsign' => $callsign]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->db->fetch('SELECT * FROM {users} WHERE email = :email', ['email' => mb_strtolower($email)]);
    }

    public function findByLogin(string $login): ?array
    {
        return str_contains($login, '@') ? $this->findByEmail($login) : $this->findByCallsign($login);
    }

    public function create(string $callsign, string $email, string $password, string $status = 'pending', string $role = 'pilot'): int
    {
        return $this->db->insert('users', [
            'callsign' => $callsign,
            'email' => mb_strtolower($email),
            'password_hash' => self::hash($password),
            'role' => $role,
            'status' => $status,
            'email_verified_at' => $status === 'active' ? now_utc() : null,
            'created_at' => now_utc(),
        ]);
    }

    public function update(int $id, array $values): void
    {
        $this->db->update('users', $values, 'id = :id', ['id' => $id]);
    }

    public function setPassword(int $id, string $password): void
    {
        $this->update($id, ['password_hash' => self::hash($password)]);
    }

    /** Vérifie le mot de passe et met à niveau le hachage si nécessaire */
    public function checkPassword(array $user, string $password): bool
    {
        if (!password_verify($password, (string) $user['password_hash'])) {
            return false;
        }
        if (password_needs_rehash((string) $user['password_hash'], self::algorithm())) {
            $this->setPassword((int) $user['id'], $password);
        }
        return true;
    }

    public function delete(int $id): void
    {
        $this->db->execute('DELETE FROM {users} WHERE id = :id', ['id' => $id]);
    }

    public function count(): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM {users}');
    }

    /** Liste paginée pour le back-office */
    public function search(string $term, int $limit, int $offset): array
    {
        $params = ['limit' => $limit, 'offset' => $offset];
        $where = '';
        if ($term !== '') {
            $where = 'WHERE callsign LIKE :term1 OR email LIKE :term2';
            $params['term1'] = $params['term2'] = '%' . addcslashes($term, '%_\\') . '%';
        }
        return $this->db->fetchAll("SELECT * FROM {users} $where ORDER BY created_at DESC LIMIT :limit OFFSET :offset", $params);
    }

    public function countSearch(string $term): int
    {
        if ($term === '') {
            return $this->count();
        }
        $like = '%' . addcslashes($term, '%_\\') . '%';
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM {users} WHERE callsign LIKE :term1 OR email LIKE :term2',
            ['term1' => $like, 'term2' => $like]);
    }

    private static function algorithm(): string|int|null
    {
        return defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    }

    private static function hash(string $password): string
    {
        return password_hash($password, self::algorithm());
    }
}
