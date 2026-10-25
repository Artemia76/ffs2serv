<?php
declare(strict_types=1);

namespace Ffs2\Web;

use Ffs2\Core\Request;
use Ffs2\Domain\ServerRepository;

/**
 * Serveurs de jeu : liste, création, adhésion aux serveurs privés, gestion des membres
 */
final class ServerController extends Controller
{
    private function servers(): ServerRepository
    {
        return new ServerRepository($this->app->db());
    }

    public function index(): void
    {
        $user = $this->app->auth()->user();
        $servers = $this->servers();
        $list = array_map(function ($server) use ($servers, $user) {
            $server['my_role'] = $servers->role($server, $user);
            $server['can_join'] = $servers->canJoin($server, $user);
            return $server;
        }, $servers->all());
        $this->page('servers/index', [
            'title' => 'Serveurs',
            'servers' => $list,
            'user' => $user,
            'canCreate' => $user !== null && $this->canCreate($user),
        ]);
    }

    public function show(string $id): void
    {
        $user = $this->app->auth()->user();
        $servers = $this->servers();
        $server = $servers->find((int) $id);
        if ($server === null) {
            $this->notFound();
        }
        $canManage = $servers->canManage($server, $user);
        $this->page('servers/show', [
            'title' => $server['name'],
            'server' => $server,
            'user' => $user,
            'role' => $servers->role($server, $user),
            'canJoin' => $servers->canJoin($server, $user),
            'canManage' => $canManage,
            'members' => $canManage || $servers->canJoin($server, $user) ? $servers->members((int) $server['id']) : [],
        ]);
    }

    public function create(): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        if (!$this->canCreate($user)) {
            $this->forbidden();
        }
        [$name, $description, $private] = $this->readForm();
        $error = $this->validate($name, null);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/servers');
        }
        $id = $this->servers()->create($name, $description, $private, (int) $user['id']);
        $this->flash('success', "Serveur « $name » créé.");
        $this->redirect('/servers/' . $id);
    }

    public function update(string $id): void
    {
        $this->checkCsrf();
        [$server, $user] = $this->managed($id);
        [$name, $description, $private] = $this->readForm();
        $error = $this->validate($name, (int) $server['id']);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/servers/' . $server['id']);
        }
        // le serveur par défaut reste public
        $this->servers()->update((int) $server['id'], $name, $description, $private && !(int) $server['is_default']);
        $this->flash('success', 'Serveur mis à jour.');
        $this->redirect('/servers/' . $server['id']);
    }

    public function delete(string $id): void
    {
        $this->checkCsrf();
        [$server, $user] = $this->managed($id);
        if ((int) $server['is_default'] || ($user['role'] !== 'admin' && (int) $server['owner_id'] !== (int) $user['id'])) {
            $this->forbidden();
        }
        $this->servers()->delete((int) $server['id']);
        $this->flash('success', 'Serveur supprimé.');
        $this->redirect('/servers');
    }

    /** Demande d'adhésion à un serveur privé */
    public function join(string $id): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        $server = $this->servers()->find((int) $id) ?? $this->notFound();
        if ((int) $server['is_private'] && $this->servers()->role($server, $user) === null) {
            $this->servers()->requestJoin((int) $server['id'], (int) $user['id']);
            $this->flash('success', "Demande envoyée aux administrateurs du serveur « {$server['name']} ».");
        }
        $this->redirect('/servers/' . $server['id']);
    }

    public function leave(string $id): void
    {
        $this->checkCsrf();
        $user = $this->requireUser();
        $server = $this->servers()->find((int) $id) ?? $this->notFound();
        if ((int) $server['owner_id'] !== (int) $user['id']) {
            $this->servers()->removeMember((int) $server['id'], (int) $user['id']);
            $this->flash('success', "Vous avez quitté le serveur « {$server['name']} ».");
        }
        $this->redirect('/servers/' . $server['id']);
    }

    /** Gestion d'un membre : approve, promote, demote, remove, add (par indicatif) */
    public function member(string $id): void
    {
        $this->checkCsrf();
        [$server] = $this->managed($id);
        $servers = $this->servers();
        $action = Request::post('action');
        if ($action === 'add') {
            $target = $this->app->users()->findByCallsign(Request::post('callsign'));
            if ($target === null) {
                $this->flash('error', 'Pilote introuvable.');
            } else {
                $servers->setMember((int) $server['id'], (int) $target['id'], 'member');
                $this->flash('success', "{$target['callsign']} est maintenant membre.");
            }
            $this->redirect('/servers/' . $server['id']);
        }
        $userId = (int) Request::post('user_id');
        if ($userId === (int) $server['owner_id']) {
            $this->redirect('/servers/' . $server['id']);
        }
        match ($action) {
            'approve', 'demote' => $servers->setMember((int) $server['id'], $userId, 'member'),
            'promote' => $servers->setMember((int) $server['id'], $userId, 'admin'),
            'remove' => $servers->removeMember((int) $server['id'], $userId),
            default => null,
        };
        $this->redirect('/servers/' . $server['id']);
    }

    /** @return array{0: array, 1: array} serveur, utilisateur autorisé à le gérer */
    private function managed(string $id): array
    {
        $user = $this->requireUser();
        $server = $this->servers()->find((int) $id) ?? $this->notFound();
        if (!$this->servers()->canManage($server, $user)) {
            $this->forbidden();
        }
        return [$server, $user];
    }

    private function canCreate(array $user): bool
    {
        if ($user['role'] === 'admin') {
            return true;
        }
        return $this->app->settings()->bool('users_can_create_servers')
            && $this->servers()->countOwnedBy((int) $user['id']) < $this->app->settings()->int('max_servers_per_user');
    }

    private function readForm(): array
    {
        return [
            mb_substr(Request::post('name'), 0, 64),
            mb_substr(Request::post('description'), 0, 500),
            Request::post('is_private') === '1',
        ];
    }

    private function validate(string $name, ?int $exceptId): ?string
    {
        if (mb_strlen($name) < 3) {
            return 'Le nom du serveur doit contenir au moins 3 caractères.';
        }
        if ($this->servers()->nameExists($name, $exceptId)) {
            return 'Un serveur porte déjà ce nom.';
        }
        return null;
    }
}
