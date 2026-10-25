<?php
declare(strict_types=1);

namespace Ffs2\Domain;

use Ffs2\Core\Database;

/**
 * Statistiques des pilotes (page profil) et du portail (accueil, back-office)
 */
final class StatsService
{
    public function __construct(private Database $db)
    {
    }

    public function forUser(int $userId): array
    {
        $flights = $this->db->fetch(
            "SELECT COUNT(*) AS count,
                    COALESCE(SUM(TIMESTAMPDIFF(SECOND, started_at, ended_at)), 0) AS seconds,
                    COALESCE(SUM(distance_nm), 0) AS distance,
                    COALESCE(MAX(max_altitude), 0) AS ceiling
             FROM {flights} WHERE user_id = :user AND status = 'completed'",
            ['user' => $userId]
        ) ?? [];
        $connected = (int) $this->db->fetchValue(
            'SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, c.connected_at, COALESCE(c.disconnected_at, s.last_seen_at, c.connected_at))), 0)
             FROM {connections} c LEFT JOIN {slots} s ON s.connection_id = c.id WHERE c.user_id = :user',
            ['user' => $userId]
        );
        return [
            'flights' => (int) ($flights['count'] ?? 0),
            'flight_seconds' => (int) ($flights['seconds'] ?? 0),
            'distance_nm' => (float) ($flights['distance'] ?? 0),
            'ceiling' => (int) ($flights['ceiling'] ?? 0),
            'connected_seconds' => $connected,
            'aircraft' => $this->db->fetchAll(
                "SELECT aircraft, COUNT(*) AS count FROM {flights}
                 WHERE user_id = :user AND status = 'completed' AND aircraft <> ''
                 GROUP BY aircraft ORDER BY count DESC LIMIT 5",
                ['user' => $userId]
            ),
            'sims' => $this->db->fetchAll(
                "SELECT sim, COUNT(*) AS count FROM {connections} WHERE user_id = :user AND sim <> ''
                 GROUP BY sim ORDER BY count DESC LIMIT 5",
                ['user' => $userId]
            ),
        ];
    }

    public function recentFlights(int $userId, int $limit = 20): array
    {
        return $this->db->fetchAll(
            "SELECT f.*, s.name AS server_name, TIMESTAMPDIFF(SECOND, f.started_at, COALESCE(f.ended_at, f.updated_at)) AS duration
             FROM {flights} f LEFT JOIN {servers} s ON s.id = f.server_id
             WHERE f.user_id = :user ORDER BY f.started_at DESC LIMIT :limit",
            ['user' => $userId, 'limit' => $limit]
        );
    }

    public function recentConnections(int $userId, int $limit = 10): array
    {
        return $this->db->fetchAll(
            'SELECT c.*, s.name AS server_name FROM {connections} c LEFT JOIN {servers} s ON s.id = c.server_id
             WHERE c.user_id = :user ORDER BY c.connected_at DESC LIMIT :limit',
            ['user' => $userId, 'limit' => $limit]
        );
    }

    /** Trace d'un vol (carte du profil) */
    public function flightTrack(int $flightId): array
    {
        return $this->db->fetchAll(
            'SELECT latitude, longitude, altitude FROM {track_points} WHERE flight_id = :flight ORDER BY id',
            ['flight' => $flightId]
        );
    }

    public function global(): array
    {
        return [
            'pilots' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM {users} WHERE status = 'active'"),
            'online' => (int) $this->db->fetchValue('SELECT COUNT(*) FROM {slots} WHERE last_seen_at >= :active',
                ['active' => now_utc(-SlotRepository::ACTIVE_DELAY)]),
            'flights_today' => (int) $this->db->fetchValue("SELECT COUNT(*) FROM {flights} WHERE started_at >= :day AND status <> 'aborted'",
                ['day' => gmdate('Y-m-d 00:00:00')]),
            'flight_hours' => (int) round((int) $this->db->fetchValue(
                "SELECT COALESCE(SUM(TIMESTAMPDIFF(SECOND, started_at, ended_at)), 0) FROM {flights} WHERE status = 'completed'") / 3600),
        ];
    }

    /** Derniers vols terminés, tous pilotes (accueil) */
    public function latestFlights(int $limit = 8): array
    {
        return $this->db->fetchAll(
            "SELECT f.*, u.callsign, TIMESTAMPDIFF(SECOND, f.started_at, f.ended_at) AS duration
             FROM {flights} f JOIN {users} u ON u.id = f.user_id
             LEFT JOIN {servers} s ON s.id = f.server_id
             WHERE f.status = 'completed' AND COALESCE(s.is_private, 0) = 0
             ORDER BY f.ended_at DESC LIMIT :limit",
            ['limit' => $limit]
        );
    }
}
