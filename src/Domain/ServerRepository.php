<?php
declare(strict_types=1);

namespace Ffs2\Domain;

use Ffs2\Core\Database;

/**
 * Serveurs (salons) de jeu : un pilote ne voit en P2P que les pilotes de son serveur
 * (plus les pilotes en mode global s'il l'est lui-même). Un serveur privé n'accepte que ses membres.
 */
final class ServerRepository
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT s.*, u.callsign AS owner_callsign FROM {servers} s LEFT JOIN {users} u ON u.id = s.owner_id WHERE s.id = :id',
            ['id' => $id]
        );
    }

    public function default(): array
    {
        $server = $this->db->fetch('SELECT * FROM {servers} WHERE is_default = 1 ORDER BY id LIMIT 1')
            ?? $this->db->fetch('SELECT * FROM {servers} ORDER BY id LIMIT 1');
        if ($server === null) {
            $id = $this->create('Public', 'Serveur ouvert à tous', false, null, true);
            $server = $this->find($id);
        }
        return $server;
    }

    /** Liste des serveurs avec propriétaire, nombre de membres et de pilotes connectés */
    public function all(): array
    {
        return $this->db->fetchAll(
            "SELECT s.*, u.callsign AS owner_callsign,
                (SELECT COUNT(*) FROM {server_members} m WHERE m.server_id = s.id AND m.role <> 'pending') AS members,
                (SELECT COUNT(*) FROM {slots} sl WHERE sl.server_id = s.id AND sl.last_seen_at >= :active) AS online
             FROM {servers} s LEFT JOIN {users} u ON u.id = s.owner_id
             ORDER BY s.is_default DESC, s.name",
            ['active' => now_utc(-SlotRepository::ACTIVE_DELAY)]
        );
    }

    /** Rôle d'un utilisateur sur un serveur : owner, admin, member, pending ou null */
    public function role(array $server, ?array $user): ?string
    {
        if ($user === null) {
            return null;
        }
        if ((int) $server['owner_id'] === (int) $user['id']) {
            return 'owner';
        }
        $role = $this->db->fetchValue(
            'SELECT role FROM {server_members} WHERE server_id = :server AND user_id = :user',
            ['server' => $server['id'], 'user' => $user['id']]
        );
        return $role === null ? null : (string) $role;
    }

    /** Le pilote peut-il voler sur ce serveur ? */
    public function canJoin(array $server, ?array $user): bool
    {
        if (!(int) $server['is_private']) {
            return true;
        }
        if ($user === null) {
            return false;
        }
        return $user['role'] === 'admin' || in_array($this->role($server, $user), ['owner', 'admin', 'member'], true);
    }

    /** Le pilote peut-il gérer ce serveur (membres, réglages) ? */
    public function canManage(array $server, ?array $user): bool
    {
        if ($user === null) {
            return false;
        }
        return $user['role'] === 'admin' || in_array($this->role($server, $user), ['owner', 'admin'], true);
    }

    /** Serveurs sur lesquels le pilote peut voler */
    public function joinableBy(array $user): array
    {
        return array_values(array_filter($this->all(), fn ($server) => $this->canJoin($server, $user)));
    }

    /** Identifiants des serveurs privés dont le pilote voit le trafic */
    public function visibleIds(?array $user): array
    {
        $ids = [];
        foreach ($this->all() as $server) {
            if ($this->canJoin($server, $user)) {
                $ids[] = (int) $server['id'];
            }
        }
        return $ids;
    }

    public function members(int $serverId): array
    {
        return $this->db->fetchAll(
            "SELECT m.role, m.created_at, u.id, u.callsign FROM {server_members} m
             JOIN {users} u ON u.id = m.user_id WHERE m.server_id = :server
             ORDER BY FIELD(m.role, 'pending', 'admin', 'member'), u.callsign",
            ['server' => $serverId]
        );
    }

    public function create(string $name, string $description, bool $private, ?int $ownerId, bool $isDefault = false): int
    {
        return $this->db->insert('servers', [
            'name' => $name,
            'description' => $description,
            'is_private' => $private ? 1 : 0,
            'is_default' => $isDefault ? 1 : 0,
            'owner_id' => $ownerId,
            'created_at' => now_utc(),
        ]);
    }

    public function update(int $id, string $name, string $description, bool $private): void
    {
        $this->db->update('servers', ['name' => $name, 'description' => $description, 'is_private' => $private ? 1 : 0], 'id = :id', ['id' => $id]);
    }

    /** Suppression : les pilotes connectés basculent sur le serveur par défaut */
    public function delete(int $id): void
    {
        $default = $this->default();
        if ((int) $default['id'] === $id) {
            throw new \RuntimeException('Le serveur par défaut ne peut pas être supprimé');
        }
        $this->db->execute('UPDATE {slots} SET server_id = :default WHERE server_id = :id', ['default' => $default['id'], 'id' => $id]);
        $this->db->execute('DELETE FROM {servers} WHERE id = :id', ['id' => $id]);
    }

    public function nameExists(string $name, ?int $exceptId = null): bool
    {
        return (int) $this->db->fetchValue(
            'SELECT COUNT(*) FROM {servers} WHERE name = :name AND id <> :id',
            ['name' => $name, 'id' => $exceptId ?? 0]
        ) > 0;
    }

    public function countOwnedBy(int $userId): int
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM {servers} WHERE owner_id = :user', ['user' => $userId]);
    }

    public function requestJoin(int $serverId, int $userId): void
    {
        $this->db->execute(
            "INSERT IGNORE INTO {server_members} (server_id, user_id, role, created_at) VALUES (:server, :user, 'pending', :now)",
            ['server' => $serverId, 'user' => $userId, 'now' => now_utc()]
        );
    }

    /** @param string $role member ou admin */
    public function setMember(int $serverId, int $userId, string $role): void
    {
        $this->db->execute(
            'INSERT INTO {server_members} (server_id, user_id, role, created_at) VALUES (:server, :user, :role, :now)
             ON DUPLICATE KEY UPDATE role = VALUES(role)',
            ['server' => $serverId, 'user' => $userId, 'role' => $role, 'now' => now_utc()]
        );
    }

    /** Retrait d'un membre : s'il vole sur ce serveur privé, il revient au serveur par défaut */
    public function removeMember(int $serverId, int $userId): void
    {
        $this->db->execute('DELETE FROM {server_members} WHERE server_id = :server AND user_id = :user', ['server' => $serverId, 'user' => $userId]);
        $server = $this->find($serverId);
        if ($server !== null && (int) $server['is_private'] && (int) $server['owner_id'] !== $userId) {
            $default = (int) $this->default()['id'];
            $this->db->execute('UPDATE {slots} SET server_id = :default WHERE server_id = :server AND user_id = :user',
                ['default' => $default, 'server' => $serverId, 'user' => $userId]);
            $this->db->execute('UPDATE {users} SET preferred_server_id = NULL WHERE id = :user AND preferred_server_id = :server',
                ['user' => $userId, 'server' => $serverId]);
        }
    }
}
