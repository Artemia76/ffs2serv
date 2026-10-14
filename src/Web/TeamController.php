<?php
declare(strict_types=1);

namespace Ffs2\Web;

use Ffs2\Core\Request;
use Ffs2\Domain\TeamRepository;

/**
 * Équipes : création, demandes d'adhésion validées par le chef, départ, transfert, dissolution
 */
final class TeamController extends Controller
{
    private function teams(): TeamRepository
    {
        return new TeamRepository($this->app->db());
    }

    public function index(): void
    {
        $this->page('teams/index', [
            'title' => 'Équipes',
            'teams' => $this->teams()->all(),
            'user' => $this->app->auth()->user(),
        ]);
    }

    public function show(string $id): void
    {
        $team = $this->teams()->find((int) $id) ?? $this->notFound();
        $user = $this->app->auth()->user();
        $isLeader = $user !== null && (int) $team['leader_id'] === (int) $user['id'];
        $this->page('teams/show', [
            'title' => $team['name'],
            'team' => $team,
            'user' => $user,
            'isLeader' => $isLeader,
            'isMember' => $user !== null && (int) $user['team_id'] === (int) $team['id'],
            'hasRequested' => $user !== null && $this->teams()->hasRequested((int) $team['id'], (int) $user['id']),
            'members' => $this->teams()->members((int) $team['id']),
            'requests' => $isLeader || ($user['role'] ?? '') === 'admin' ? $this->teams()->requests((int) $team['id']) : [],
        ]);
    }

    public function create(): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        $name = mb_substr(Request::post('name'), 0, 64);
        if ($user['team_id'] !== null) {
            $this->flash('error', "Vous faites déjà partie d'une équipe.");
        } elseif (mb_strlen($name) < 3) {
            $this->flash('error', "Le nom de l'équipe doit contenir au moins 3 caractères.");
        } elseif ($this->teams()->nameExists($name)) {
            $this->flash('error', 'Une équipe porte déjà ce nom.');
        } else {
            $id = $this->teams()->create($name, (int) $user['id']);
            $this->flash('success', "Équipe « $name » créée : vous en êtes le chef.");
            $this->redirect('/teams/' . $id);
        }
        $this->redirect('/teams');
    }

    public function join(string $id): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        $team = $this->teams()->find((int) $id) ?? $this->notFound();
        if ($user['team_id'] === null) {
            $this->teams()->requestJoin((int) $team['id'], (int) $user['id']);
            $this->flash('success', "Demande envoyée au chef de l'équipe ({$team['leader_callsign']}).");
        }
        $this->redirect('/teams/' . $team['id']);
    }

    public function cancel(string $id): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        $this->teams()->cancelRequest((int) $id, (int) $user['id']);
        $this->redirect('/teams/' . (int) $id);
    }

    public function leave(string $id): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        $team = $this->teams()->find((int) $id) ?? $this->notFound();
        if ((int) $user['team_id'] !== (int) $team['id']) {
            $this->redirect('/teams/' . $team['id']);
        }
        if ((int) $team['leader_id'] === (int) $user['id']) {
            if (count($this->teams()->members((int) $team['id'])) > 1) {
                $this->flash('error', "Transmettez la direction de l'équipe à un membre ou dissolvez-la avant de partir.");
                $this->redirect('/teams/' . $team['id']);
            }
            $this->teams()->dissolve((int) $team['id']);
            $this->flash('success', 'Équipe dissoute.');
            $this->redirect('/teams');
        }
        $this->teams()->removeMember((int) $team['id'], (int) $user['id']);
        $this->flash('success', "Vous avez quitté l'équipe.");
        $this->redirect('/teams');
    }

    /** Actions du chef : accept, refuse, kick, transfer, dissolve */
    public function manage(string $id): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        $team = $this->teams()->find((int) $id) ?? $this->notFound();
        if ((int) $team['leader_id'] !== (int) $user['id'] && $user['role'] !== 'admin') {
            $this->forbidden();
        }
        $teams = $this->teams();
        $target = (int) Request::post('user_id');
        switch (Request::post('action')) {
            case 'accept':
                $teams->accept((int) $team['id'], $target)
                    ? $this->flash('success', 'Membre accepté.')
                    : $this->flash('error', 'Demande introuvable.');
                break;
            case 'refuse':
                $teams->cancelRequest((int) $team['id'], $target);
                break;
            case 'kick':
                if ($target !== (int) $team['leader_id']) {
                    $teams->removeMember((int) $team['id'], $target);
                }
                break;
            case 'transfer':
                $teams->transfer((int) $team['id'], $target)
                    ? $this->flash('success', 'Direction transmise.')
                    : $this->flash('error', "Ce pilote ne fait pas partie de l'équipe.");
                break;
            case 'dissolve':
                $teams->dissolve((int) $team['id']);
                $this->flash('success', 'Équipe dissoute.');
                $this->redirect('/teams');
        }
        $this->redirect('/teams/' . $team['id']);
    }
}
