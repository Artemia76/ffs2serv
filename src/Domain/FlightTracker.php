<?php
declare(strict_types=1);

namespace Ffs2\Domain;

use Ffs2\Core\Database;

/**
 * Positions envoyées par le client et détection des vols.
 *
 * Le client envoie :
 *  - toutes les 5 s (« atc ») : latitude, longitude, cap, altitude, vitesses, transpondeur
 *  - toutes les 30 s (« liveupdate ») : la même chose + appareil, phase de vol, au sol, simulateur
 * Phases (sim/analyzer.h) : IDLE, Boarding, Pushback, Taxiway, Takeoff, Climb, Cruise, Mission,
 * InFlight, StepLevel, Descent, Approach, Final, GoAround, Landing, JoinGate.
 *
 * Un vol commence quand l'appareil quitte le sol et se termine au toucher (au sol en phase
 * Landing / Taxiway / JoinGate / IDLE…). Silence prolongé ou déconnexion : vol interrompu.
 */
final class FlightTracker
{
    /** Silence au delà duquel un vol en cours est déclaré interrompu */
    public const STALE_DELAY = 600;
    private const MIN_DURATION = 120;
    private const MIN_DISTANCE_NM = 2.0;
    private const TRACK_INTERVAL = 60;
    private const TRACK_DISTANCE_NM = 1.0;

    public function __construct(private Database $db)
    {
    }

    /**
     * Normalise les champs XML du client ; null si la position est inexploitable
     * (simulateur non connecté : 0,0)
     */
    public static function parse(\SimpleXMLElement $node, bool $full): ?array
    {
        $lat = (float) str_replace(',', '.', (string) $node->latitude);
        $lon = (float) str_replace(',', '.', (string) $node->longitude);
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180 || (abs($lat) < 0.0001 && abs($lon) < 0.0001)) {
            return null;
        }
        $data = [
            'latitude' => $lat,
            'longitude' => $lon,
            'heading' => ((int) round((float) $node->heading) % 360 + 360) % 360,
            'altitude' => max(-2000, min(100000, (int) round((float) $node->altitude))),
            'ground_speed' => max(0, min(5000, (int) round((float) $node->groundSpeed))),
            'ias' => max(0, min(5000, (int) round((float) $node->iaspeed))),
            'squawk' => max(0, min(7777, (int) $node->squawk)),
        ];
        if ($full) {
            $data['on_ground'] = (int) $node->onground ? 1 : 0;
            $data['phase'] = mb_substr(preg_replace('/[^A-Za-z]/', '', (string) $node->status) ?? '', 0, 20);
            $data['aircraft'] = mb_substr(trim((string) $node->registration), 0, 128);
            $data['category'] = mb_substr(trim((string) $node->category), 0, 64);
        }
        return $data;
    }

    /** Enregistre une position et fait avancer le vol en cours */
    public function update(int $userId, int $serverId, string $sim, array $data): void
    {
        $previous = $this->db->fetch('SELECT * FROM {positions} WHERE user_id = :user', ['user' => $userId]);
        $merged = array_merge([
            'on_ground' => 1, 'phase' => '', 'aircraft' => '', 'category' => '',
        ], $previous ?? [], $data, ['user_id' => $userId, 'updated_at' => now_utc()]);
        unset($merged['id']);

        $columns = ['user_id', 'latitude', 'longitude', 'heading', 'altitude', 'ground_speed', 'ias', 'squawk',
            'on_ground', 'phase', 'aircraft', 'category', 'updated_at'];
        $values = array_intersect_key($merged, array_flip($columns));
        $updates = implode(', ', array_map(fn ($c) => "`$c` = VALUES(`$c`)", array_diff($columns, ['user_id'])));
        $this->db->execute(
            'INSERT INTO {positions} (`' . implode('`, `', $columns) . '`) VALUES (:' . implode(', :', $columns) . ")
             ON DUPLICATE KEY UPDATE $updates",
            $values
        );

        $flight = $this->db->fetch("SELECT * FROM {flights} WHERE user_id = :user AND status = 'in_progress' ORDER BY id DESC LIMIT 1", ['user' => $userId]);
        $airborne = !(int) $merged['on_ground'];

        if ($flight === null && $airborne) {
            $flight = $this->start($userId, $serverId, $sim, $merged, $previous);
        } elseif ($flight !== null) {
            $this->progress($flight, $merged, $previous);
            if (!$airborne && in_array($merged['phase'], ['Landing', 'Taxiway', 'JoinGate', 'IDLE', 'Boarding', ''], true)) {
                $this->finish($flight, $merged);
                $flight = null;
            }
        }
        $this->track($userId, $flight === null ? null : (int) $flight['id'], $merged);
    }

    /** Fin de connexion : le vol en cours est interrompu */
    public function abort(int $userId): void
    {
        $this->db->execute(
            "UPDATE {flights} SET status = 'aborted', ended_at = updated_at WHERE user_id = :user AND status = 'in_progress'",
            ['user' => $userId]
        );
        $this->db->execute('DELETE FROM {positions} WHERE user_id = :user', ['user' => $userId]);
    }

    /** Nettoyage : vols sans nouvelles, traces hors vol anciennes */
    public function purge(): void
    {
        $this->db->execute(
            "UPDATE {flights} SET status = 'aborted', ended_at = updated_at WHERE status = 'in_progress' AND updated_at < :before",
            ['before' => now_utc(-self::STALE_DELAY)]
        );
        $this->db->execute('DELETE FROM {track_points} WHERE flight_id IS NULL AND recorded_at < :before', ['before' => now_utc(-3600)]);
    }

    private function start(int $userId, int $serverId, string $sim, array $position, ?array $previous): array
    {
        // départ : dernière position connue au sol si elle est récente, sinon la position actuelle
        $origin = $position;
        if ($previous !== null && (int) $previous['on_ground'] && strtotime($previous['updated_at'] . ' UTC') >= time() - 300) {
            $origin = $previous;
        }
        $id = $this->db->insert('flights', [
            'user_id' => $userId,
            'server_id' => $serverId,
            'aircraft' => (string) $position['aircraft'],
            'sim' => mb_substr($sim, 0, 32),
            'status' => 'in_progress',
            'started_at' => now_utc(),
            'updated_at' => now_utc(),
            'dep_latitude' => (float) $origin['latitude'],
            'dep_longitude' => (float) $origin['longitude'],
            'max_altitude' => (int) $position['altitude'],
            'distance_nm' => $previous !== null ? $this->segment($previous, $position) : 0.0,
        ]);
        return (array) $this->db->fetch('SELECT * FROM {flights} WHERE id = :id', ['id' => $id]);
    }

    private function progress(array $flight, array $position, ?array $previous): void
    {
        $distance = $previous !== null ? $this->segment($previous, $position) : 0.0;
        $values = [
            'updated_at' => now_utc(),
            'max_altitude' => max((int) $flight['max_altitude'], (int) $position['altitude']),
            'distance_nm' => (float) $flight['distance_nm'] + $distance,
        ];
        if ($flight['aircraft'] === '' && $position['aircraft'] !== '') {
            $values['aircraft'] = $position['aircraft'];
        }
        $this->db->update('flights', $values, 'id = :id', ['id' => $flight['id']]);
    }

    private function finish(array $flight, array $position): void
    {
        $flight = (array) $this->db->fetch('SELECT * FROM {flights} WHERE id = :id', ['id' => $flight['id']]);
        $duration = time() - strtotime($flight['started_at'] . ' UTC');
        if ($duration < self::MIN_DURATION && (float) $flight['distance_nm'] < self::MIN_DISTANCE_NM) {
            // rebond, glissement de l'appareil au chargement du simulateur…
            $this->db->execute('DELETE FROM {flights} WHERE id = :id', ['id' => $flight['id']]);
            return;
        }
        $this->db->update('flights', [
            'status' => 'completed',
            'ended_at' => now_utc(),
            'updated_at' => now_utc(),
            'arr_latitude' => (float) $position['latitude'],
            'arr_longitude' => (float) $position['longitude'],
        ], 'id = :id', ['id' => $flight['id']]);
    }

    /** Point de trace si le dernier date de plus d'une minute ou est à plus d'1 NM */
    private function track(int $userId, ?int $flightId, array $position): void
    {
        $last = $this->db->fetch('SELECT * FROM {track_points} WHERE user_id = :user ORDER BY id DESC LIMIT 1', ['user' => $userId]);
        if ($last !== null && (int) ($last['flight_id'] ?? 0) === (int) $flightId
            && strtotime($last['recorded_at'] . ' UTC') > time() - self::TRACK_INTERVAL
            && self::distanceNm((float) $last['latitude'], (float) $last['longitude'], (float) $position['latitude'], (float) $position['longitude']) < self::TRACK_DISTANCE_NM) {
            return;
        }
        $this->db->insert('track_points', [
            'user_id' => $userId,
            'flight_id' => $flightId,
            'latitude' => (float) $position['latitude'],
            'longitude' => (float) $position['longitude'],
            'altitude' => (int) $position['altitude'],
            'recorded_at' => now_utc(),
        ]);
    }

    /** Distance parcourue entre deux positions, en ignorant les sauts (téléportation, mode slew) */
    private function segment(array $from, array $to): float
    {
        $elapsed = time() - strtotime($from['updated_at'] . ' UTC');
        $distance = self::distanceNm((float) $from['latitude'], (float) $from['longitude'], (float) $to['latitude'], (float) $to['longitude']);
        return ($elapsed <= 120 && $distance < 50) ? $distance : 0.0;
    }

    /** Distance orthodromique en milles nautiques */
    public static function distanceNm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $rad = M_PI / 180;
        $a = sin(($lat2 - $lat1) * $rad / 2) ** 2 + cos($lat1 * $rad) * cos($lat2 * $rad) * sin(($lon2 - $lon1) * $rad / 2) ** 2;
        return 3440.065 * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
