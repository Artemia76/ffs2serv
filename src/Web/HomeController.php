<?php
declare(strict_types=1);

namespace Ffs2\Web;

use Ffs2\Domain\ServerRepository;
use Ffs2\Domain\SlotRepository;
use Ffs2\Domain\StatsService;

/**
 * Page d'accueil (pilotes en temps réel) et flux JSON qui l'alimente
 */
final class HomeController extends Controller
{
    public function index(): void
    {
        $stats = new StatsService($this->app->db());
        $settings = $this->app->settings();
        $this->page('home', [
            'title' => '',
            'stats' => $stats->global(),
            'latest' => $stats->latestFlights(),
            'user' => $this->app->auth()->user(),
            'tiles' => ['url' => $settings->get('tile_url'), 'attribution' => $settings->get('tile_attribution')],
            'withMap' => true,
            'scripts' => ['js/live.js'],
        ]);
    }

    /**
     * GET /api/live : pilotes connectés et serveurs visibles par le visiteur.
     * Aucune adresse IP n'est jamais exposée.
     */
    public function live(): void
    {
        $db = $this->app->db();
        $viewer = $this->app->auth()->user();
        $servers = new ServerRepository($db);
        $visible = [];
        foreach ($servers->all() as $server) {
            if ($servers->canJoin($server, $viewer)) {
                $visible[(int) $server['id']] = [
                    'id' => (int) $server['id'],
                    'name' => $server['name'],
                    'private' => (bool) $server['is_private'],
                    'default' => (bool) $server['is_default'],
                    'pilots' => 0,
                ];
            }
        }

        $pilots = [];
        $slots = new SlotRepository($db, $this->app->appKey());
        $online = $slots->online();
        $userIds = [];
        foreach ($online as $row) {
            $serverId = (int) $row['server_id'];
            if (!isset($visible[$serverId])) {
                continue;
            }
            $visible[$serverId]['pilots']++;
            $userIds[] = (int) $row['user_id'];
            $hasPosition = $row['latitude'] !== null;
            $pilots[(int) $row['user_id']] = [
                'callsign' => $row['callsign'],
                'me' => $viewer !== null && (int) $viewer['id'] === (int) $row['user_id'],
                'server_id' => $serverId,
                'server' => $row['server_name'],
                'global' => (bool) $row['global_mode'],
                'team' => $row['team_name'],
                'leader' => $row['team'],
                'sim' => $row['sim'],
                'connected_at' => $row['connected_at'],
                'aircraft' => $row['aircraft'],
                'category' => $row['category'],
                'phase' => $row['phase'],
                'position' => $hasPosition ? [
                    'lat' => round((float) $row['latitude'], 5),
                    'lon' => round((float) $row['longitude'], 5),
                    'heading' => (int) $row['heading'],
                    'altitude' => (int) $row['altitude'],
                    'gs' => (int) $row['ground_speed'],
                    'ias' => (int) $row['ias'],
                    'squawk' => sprintf('%04d', (int) $row['squawk']),
                    'on_ground' => (bool) $row['on_ground'],
                    'updated_at' => $row['position_at'],
                ] : null,
                'trail' => [],
            ];
        }

        // traces des 20 dernières minutes
        if ($userIds) {
            $placeholders = implode(',', array_fill(0, count($userIds), '?'));
            $points = $db->fetchAll(
                "SELECT user_id, latitude, longitude FROM {track_points}
                 WHERE user_id IN ($placeholders) AND recorded_at >= ? ORDER BY id",
                array_merge($userIds, [now_utc(-1200)])
            );
            foreach ($points as $point) {
                $pilots[(int) $point['user_id']]['trail'][] = [round((float) $point['latitude'], 4), round((float) $point['longitude'], 4)];
            }
        }

        $this->json([
            'generated_at' => now_utc(),
            'servers' => array_values($visible),
            'pilots' => array_values($pilots),
        ]);
    }
}
