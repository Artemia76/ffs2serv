<?php
declare(strict_types=1);

namespace Ffs2\Web;

use Ffs2\Auth\AuthService;
use Ffs2\Core\Request;
use Ffs2\Domain\ServerRepository;
use Ffs2\Domain\SlotRepository;
use Ffs2\Domain\StatsService;
use Ffs2\Domain\TeamRepository;

/**
 * Page profil du pilote connecté, profils publics, détail d'un vol
 */
final class ProfileController extends Controller
{
    public function show(array $errors = []): void
    {
        $user = $this->requireUser();
        $db = $this->app->db();
        $servers = new ServerRepository($db);
        $stats = new StatsService($db);
        $slots = new SlotRepository($db, $this->app->appKey());
        $team = $user['team_id'] !== null ? (new TeamRepository($db))->find((int) $user['team_id']) : null;

        $this->page('profile/show', [
            'title' => 'Mon profil',
            'user' => $user,
            'errors' => $errors,
            'servers' => $servers->joinableBy($user),
            'preferred' => $user['preferred_server_id'] !== null ? (int) $user['preferred_server_id'] : (int) $servers->default()['id'],
            'slot' => $slots->findByUser((int) $user['id']),
            'online' => $slots->isOnline((int) $user['id']),
            'team' => $team,
            'stats' => $stats->forUser((int) $user['id']),
            'flights' => $stats->recentFlights((int) $user['id']),
            'connections' => $stats->recentConnections((int) $user['id']),
            'serverUrl' => $this->app->baseUrl(),
        ]);
    }

    /** Choix du serveur et du mode global, appliqué immédiatement si ffs2play est connecté */
    public function chooseServer(): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        $servers = new ServerRepository($this->app->db());
        $server = $servers->find((int) Request::post('server_id'));
        if ($server === null || !$servers->canJoin($server, $user)) {
            $this->flash('error', "Vous n'avez pas accès à ce serveur.");
            $this->redirect('/profile');
        }
        $global = Request::post('global_mode') === '1';
        $this->app->users()->update((int) $user['id'], ['preferred_server_id' => (int) $server['id'], 'global_mode' => $global ? 1 : 0]);
        (new SlotRepository($this->app->db(), $this->app->appKey()))->applyServer((int) $user['id'], (int) $server['id'], $global);
        $this->flash('success', 'Serveur « ' . $server['name'] . ' » sélectionné' . ($global ? ' (mode global)' : '') . '. Il est pris en compte dans les 30 secondes si ffs2play est connecté.');
        $this->redirect('/profile');
    }

    public function changePassword(): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        $errors = (new AuthService($this->app))->changePassword($user, Request::raw('current_password'), Request::raw('password'), Request::raw('password_confirmation'));
        if ($errors) {
            $this->show($errors);
            return;
        }
        $this->flash('success', 'Mot de passe modifié. Pensez à le changer aussi dans le profil de ffs2play.');
        $this->redirect('/profile');
    }

    public function changeEmail(): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        try {
            $errors = (new AuthService($this->app))->changeEmail($user, Request::raw('current_password'), Request::post('email'));
        } catch (\RuntimeException $e) {
            error_log('FFS2play changement d\'e-mail : ' . $e->getMessage());
            $errors = [];
        }
        if ($errors) {
            $this->show($errors);
            return;
        }
        $this->flash('success', 'Adresse e-mail mise à jour.');
        $this->redirect('/profile');
    }

    /** Profil public d'un pilote */
    public function publicProfile(string $callsign): void
    {
        $pilot = $this->app->users()->findByCallsign($callsign);
        if ($pilot === null || $pilot['status'] !== 'active') {
            $this->notFound();
        }
        $db = $this->app->db();
        $stats = new StatsService($db);
        $viewer = $this->app->auth()->user();
        $visible = (new ServerRepository($db))->visibleIds($viewer);
        $flights = array_values(array_filter(
            $stats->recentFlights((int) $pilot['id']),
            fn ($f) => $f['server_id'] === null || in_array((int) $f['server_id'], $visible, true) || ($viewer !== null && (int) $viewer['id'] === (int) $pilot['id'])
        ));
        $this->page('profile/public', [
            'title' => $pilot['callsign'],
            'pilot' => $pilot,
            'stats' => $stats->forUser((int) $pilot['id']),
            'flights' => $flights,
            'online' => (new SlotRepository($db, $this->app->appKey()))->isOnline((int) $pilot['id']),
            'team' => $pilot['team_id'] !== null ? (new TeamRepository($db))->find((int) $pilot['team_id']) : null,
        ]);
    }

    /** Détail d'un vol avec sa trace */
    public function flight(string $id): void
    {
        $db = $this->app->db();
        $flight = $db->fetch(
            'SELECT f.*, u.callsign, s.name AS server_name FROM {flights} f JOIN {users} u ON u.id = f.user_id
             LEFT JOIN {servers} s ON s.id = f.server_id WHERE f.id = :id',
            ['id' => (int) $id]
        );
        if ($flight === null) {
            $this->notFound();
        }
        $viewer = $this->app->auth()->user();
        $isOwner = $viewer !== null && (int) $viewer['id'] === (int) $flight['user_id'];
        if (!$isOwner && $flight['server_id'] !== null && !in_array((int) $flight['server_id'], (new ServerRepository($db))->visibleIds($viewer), true)) {
            $this->notFound();
        }
        $track = (new StatsService($db))->flightTrack((int) $flight['id']);
        $this->page('profile/flight', [
            'title' => 'Vol de ' . $flight['callsign'],
            'flight' => $flight,
            'track' => array_map(fn ($p) => [(float) $p['latitude'], (float) $p['longitude'], (int) $p['altitude']], $track),
            'tiles' => ['url' => $this->app->settings()->get('tile_url'), 'attribution' => $this->app->settings()->get('tile_attribution')],
            'withMap' => true,
            'scripts' => ['js/flight.js'],
        ]);
    }
}
