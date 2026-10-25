<?php
declare(strict_types=1);

namespace Ffs2\Domain;

use Ffs2\Core\Database;

/**
 * Équipes : dans le whazzup, l'attribut team de chaque membre vaut l'indicatif du chef.
 * Le client ffs2play n'affiche pas comme IA les équipiers dont team diffère de leur propre indicatif
 * (vol en équipage partagé derrière le chef, voir net/p2pmanager.cpp onCheckAI).
 */
final class TeamRepository
{
    public function __construct(private Database $db)
    {
    }

    public function find(int $id): ?array
    {
        return $this->db->fetch(
            'SELECT t.*, u.callsign AS leader_callsign FROM {teams} t JOIN {users} u ON u.id = t.leader_id WHERE t.id = :id',
            ['id' => $id]
        );
    }

    public function all(): array
    {
        return $this->db->fetchAll(
            'SELECT t.*, u.callsign AS leader_callsign,
                (SELECT COUNT(*) FROM {users} m WHERE m.team_id = t.id) AS members
             FROM {teams} t JOIN {users} u ON u.id = t.leader_id ORDER BY t.name'
        );
    }

    public function members(int $teamId): array
    {
        return $this->db->fetchAll('SELECT id, callsign FROM {users} WHERE team_id = :team ORDER BY callsign', ['team' => $teamId]);
    }

    public function requests(int $teamId): array
    {
        return $this->db->fetchAll(
            'SELECT u.id, u.callsign, r.created_at FROM {team_requests} r JOIN {users} u ON u.id = r.user_id
             WHERE r.team_id = :team ORDER BY r.created_at',
            ['team' => $teamId]
        );
    }

    public function hasRequested(int $teamId, int $userId): bool
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM {team_requests} WHERE team_id = :team AND user_id = :user',
            ['team' => $teamId, 'user' => $userId]) > 0;
    }

    public function nameExists(string $name): bool
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM {teams} WHERE name = :name', ['name' => $name]) > 0;
    }

    public function create(string $name, int $leaderId): int
    {
        return $this->db->transaction(function (Database $db) use ($name, $leaderId) {
            $id = $db->insert('teams', ['name' => $name, 'leader_id' => $leaderId, 'created_at' => now_utc()]);
            $db->execute('UPDATE {users} SET team_id = :team WHERE id = :user', ['team' => $id, 'user' => $leaderId]);
            $db->execute('DELETE FROM {team_requests} WHERE user_id = :user', ['user' => $leaderId]);
            return $id;
        });
    }

    public function requestJoin(int $teamId, int $userId): void
    {
        $this->db->execute('INSERT IGNORE INTO {team_requests} (team_id, user_id, created_at) VALUES (:team, :user, :now)',
            ['team' => $teamId, 'user' => $userId, 'now' => now_utc()]);
    }

    public function cancelRequest(int $teamId, int $userId): void
    {
        $this->db->execute('DELETE FROM {team_requests} WHERE team_id = :team AND user_id = :user', ['team' => $teamId, 'user' => $userId]);
    }

    /** Acceptation par le chef : le pilote quitte ses autres demandes */
    public function accept(int $teamId, int $userId): bool
    {
        if (!$this->hasRequested($teamId, $userId)) {
            return false;
        }
        $this->db->execute('UPDATE {users} SET team_id = :team WHERE id = :user AND team_id IS NULL', ['team' => $teamId, 'user' => $userId]);
        $this->db->execute('DELETE FROM {team_requests} WHERE user_id = :user', ['user' => $userId]);
        return true;
    }

    /** Départ d'un membre (pas du chef) */
    public function removeMember(int $teamId, int $userId): void
    {
        $this->db->execute('UPDATE {users} SET team_id = NULL WHERE id = :user AND team_id = :team', ['user' => $userId, 'team' => $teamId]);
    }

    public function transfer(int $teamId, int $newLeaderId): bool
    {
        $isMember = (int) $this->db->fetchValue('SELECT COUNT(*) FROM {users} WHERE id = :user AND team_id = :team',
            ['user' => $newLeaderId, 'team' => $teamId]) > 0;
        if ($isMember) {
            $this->db->execute('UPDATE {teams} SET leader_id = :user WHERE id = :team', ['user' => $newLeaderId, 'team' => $teamId]);
        }
        return $isMember;
    }

    public function dissolve(int $teamId): void
    {
        $this->db->execute('UPDATE {users} SET team_id = NULL WHERE team_id = :team', ['team' => $teamId]);
        $this->db->execute('DELETE FROM {teams} WHERE id = :team', ['team' => $teamId]);
    }
}
