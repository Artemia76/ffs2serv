<?php
declare(strict_types=1);

namespace Ffs2\Domain;

use Ffs2\Client\Crypto;
use Ffs2\Core\Database;

/**
 * Slots de connexion des clients ffs2play (un par pilote connecté) et historique des connexions.
 */
final class SlotRepository
{
    /** Délai sans nouvelles au delà duquel un pilote n'est plus listé (le client envoie un liveupdate toutes les 30 s) */
    public const ACTIVE_DELAY = 40;
    /** Délai au delà duquel un slot abandonné est supprimé */
    public const PURGE_DELAY = 300;

    public function __construct(private Database $db, private string $appKey)
    {
    }

    /**
     * Ouvre (ou rouvre) le slot d'un pilote
     * @return string clé de rappel en clair, renvoyée au client
     */
    public function open(array $user, int $serverId, string $ip, string $localIp, int $port, string $aesKey, string $clientVersion): string
    {
        return $this->db->transaction(function (Database $db) use ($user, $serverId, $ip, $localIp, $port, $aesKey, $clientVersion) {
            $userId = (int) $user['id'];
            $this->closeConnection($userId);
            $connectionId = $db->insert('connections', [
                'user_id' => $userId,
                'server_id' => $serverId,
                'client_version' => mb_substr($clientVersion, 0, 32),
                'connected_at' => now_utc(),
            ]);
            $key = bin2hex(random_bytes(16));
            $values = [
                'user_id' => $userId,
                'server_id' => $serverId,
                'global_mode' => (int) $user['global_mode'],
                'ip' => $ip,
                'local_ip' => $localIp,
                'port' => $port,
                'aes_key' => Crypto::seal($aesKey, $this->appKey),
                'key_hash' => hash('sha256', $key),
                'client_version' => mb_substr($clientVersion, 0, 32),
                'sim' => '',
                'connection_id' => $connectionId,
                'connected_at' => now_utc(),
                'last_seen_at' => now_utc(),
            ];
            $columns = implode(', ', array_map(fn ($c) => "`$c`", array_keys($values)));
            $placeholders = implode(', ', array_map(fn ($c) => ":$c", array_keys($values)));
            $updates = implode(', ', array_map(fn ($c) => "`$c` = VALUES(`$c`)", array_keys($values)));
            $db->execute("INSERT INTO {slots} ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updates", $values);
            return $key;
        });
    }

    /** Slot actif correspondant à une clé de rappel, avec l'indicatif et la clé AES du client */
    public function findByKey(string $key): ?array
    {
        if (!preg_match('/^[0-9a-f]{32}$/', $key)) {
            return null;
        }
        $slot = $this->db->fetch(
            'SELECT s.*, u.callsign, u.status FROM {slots} s JOIN {users} u ON u.id = s.user_id WHERE s.key_hash = :hash',
            ['hash' => hash('sha256', $key)]
        );
        if ($slot === null || $slot['status'] !== 'active') {
            return null;
        }
        $slot['aes_key_plain'] = Crypto::unseal((string) $slot['aes_key'], $this->appKey);
        return $slot['aes_key_plain'] === null ? null : $slot;
    }

    public function findByUser(int $userId): ?array
    {
        return $this->db->fetch('SELECT * FROM {slots} WHERE user_id = :user', ['user' => $userId]);
    }

    public function isOnline(int $userId): bool
    {
        return (int) $this->db->fetchValue('SELECT COUNT(*) FROM {slots} WHERE user_id = :user AND last_seen_at >= :active',
            ['user' => $userId, 'active' => now_utc(-self::ACTIVE_DELAY)]) > 0;
    }

    /** Le client a donné des nouvelles */
    public function touch(int $userId, string $ip, ?string $sim = null): void
    {
        $values = ['last_seen_at' => now_utc(), 'ip' => $ip];
        if ($sim !== null && $sim !== '') {
            $values['sim'] = mb_substr($sim, 0, 32);
        }
        $this->db->update('slots', $values, 'user_id = :user', ['user' => $userId]);
        if ($sim !== null && $sim !== '') {
            $this->db->execute('UPDATE {connections} c JOIN {slots} s ON s.connection_id = c.id SET c.sim = :sim WHERE s.user_id = :user',
                ['sim' => mb_substr($sim, 0, 32), 'user' => $userId]);
        }
    }

    /** Choix de serveur fait sur le site : appliqué au slot actif, effectif au liveupdate suivant */
    public function applyServer(int $userId, int $serverId, bool $globalMode): void
    {
        $this->db->update('slots', ['server_id' => $serverId, 'global_mode' => $globalMode ? 1 : 0], 'user_id = :user', ['user' => $userId]);
    }

    /** Déconnexion (logout du client, expulsion par un admin) */
    public function close(int $userId): void
    {
        $this->closeConnection($userId);
        $this->db->execute('DELETE FROM {slots} WHERE user_id = :user', ['user' => $userId]);
    }

    /**
     * Liste des pilotes à envoyer au client (whazzup) : même serveur, plus les pilotes en mode global
     * si le demandeur l'est aussi. Le demandeur est inclus : le client l'ignore, sauf en mode « shadow »
     * où il crée un pair superposé pour tester le P2P en boucle locale.
     */
    public function whazzup(array $slot, string $cryptKey): array
    {
        $params = ['server' => $slot['server_id'], 'active' => now_utc(-self::ACTIVE_DELAY)];
        $globalFilter = '';
        if ((int) $slot['global_mode']) {
            $globalFilter = ' OR s.global_mode = 1';
        }
        $rows = $this->db->fetchAll(
            "SELECT u.callsign, s.ip, s.local_ip, s.port, s.last_seen_at, leader.callsign AS team
             FROM {slots} s
             JOIN {users} u ON u.id = s.user_id
             LEFT JOIN {teams} t ON t.id = u.team_id
             LEFT JOIN {users} leader ON leader.id = t.leader_id
             WHERE s.last_seen_at >= :active AND s.port > 0 AND u.status = 'active'
               AND (s.server_id = :server$globalFilter)
             ORDER BY u.callsign",
            $params
        );
        return array_map(fn ($row) => [
            'name' => $row['callsign'],
            'ip' => (string) Crypto::encrypt((string) $row['ip'], $cryptKey),
            'local_ip' => $row['local_ip'],
            'port' => (int) $row['port'],
            'lastupdate' => $row['last_seen_at'],
            'team' => $row['team'],
        ], $rows);
    }

    /** Pilotes connectés pour la page d'accueil (aucune IP) */
    public function online(): array
    {
        return $this->db->fetchAll(
            "SELECT u.id AS user_id, u.callsign, s.server_id, s.global_mode, s.sim, s.client_version, s.connected_at,
                    srv.name AS server_name, srv.is_private, leader.callsign AS team, t.name AS team_name,
                    p.latitude, p.longitude, p.heading, p.altitude, p.ground_speed, p.ias, p.squawk,
                    p.on_ground, p.phase, p.aircraft, p.category, p.updated_at AS position_at
             FROM {slots} s
             JOIN {users} u ON u.id = s.user_id
             JOIN {servers} srv ON srv.id = s.server_id
             LEFT JOIN {positions} p ON p.user_id = s.user_id
             LEFT JOIN {teams} t ON t.id = u.team_id
             LEFT JOIN {users} leader ON leader.id = t.leader_id
             WHERE s.last_seen_at >= :active
             ORDER BY u.callsign",
            ['active' => now_utc(-self::ACTIVE_DELAY)]
        );
    }

    /** Nettoyage des slots abandonnés (client fermé sans logout) */
    public function purge(): void
    {
        $stale = $this->db->fetchAll('SELECT user_id FROM {slots} WHERE last_seen_at < :before', ['before' => now_utc(-self::PURGE_DELAY)]);
        foreach ($stale as $row) {
            $this->close((int) $row['user_id']);
        }
    }

    private function closeConnection(int $userId): void
    {
        $this->db->execute(
            'UPDATE {connections} c JOIN {slots} s ON s.connection_id = c.id
             SET c.disconnected_at = s.last_seen_at WHERE s.user_id = :user AND c.disconnected_at IS NULL',
            ['user' => $userId]
        );
    }
}
