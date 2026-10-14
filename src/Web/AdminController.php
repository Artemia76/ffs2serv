<?php
declare(strict_types=1);

namespace Ffs2\Web;

use Ffs2\Auth\AuthService;
use Ffs2\Core\Migrator;
use Ffs2\Core\Request;
use Ffs2\Core\Settings;
use Ffs2\Domain\FlightTracker;
use Ffs2\Domain\ServerRepository;
use Ffs2\Domain\SlotRepository;
use Ffs2\Domain\StatsService;
use Ffs2\Domain\TeamRepository;

/**
 * Back-office (rôle admin)
 */
final class AdminController extends Controller
{
    private const PAGE_SIZE = 30;

    public function dashboard(): void
    {
        $this->requireAdmin();
        $db = $this->app->db();
        $this->page('admin/dashboard', [
            'title' => 'Administration',
            'stats' => (new StatsService($db))->global(),
            'online' => (new SlotRepository($db, $this->app->appKey()))->online(),
            'pending' => (int) $db->fetchValue("SELECT COUNT(*) FROM {users} WHERE status = 'pending'"),
            'latestUsers' => $db->fetchAll('SELECT id, callsign, email, status, created_at FROM {users} ORDER BY created_at DESC LIMIT 8'),
            'migrations' => (new Migrator($db))->pending(),
            'certificateExpires' => $this->app->keys()->expiresAt(),
        ]);
    }

    /** Expulsion d'un pilote connecté : son client sera déconnecté au liveupdate suivant */
    public function kick(string $id): void
    {
        $this->checkCsrf();
        $this->requireAdmin();
        (new FlightTracker($this->app->db()))->abort((int) $id);
        (new SlotRepository($this->app->db(), $this->app->appKey()))->close((int) $id);
        $this->flash('success', 'Pilote déconnecté.');
        $this->redirect('/admin');
    }

    public function users(): void
    {
        $this->requireAdmin();
        $users = $this->app->users();
        $term = Request::query('q');
        $page = max(1, (int) Request::query('page', '1'));
        $total = $users->countSearch($term);
        $this->page('admin/users', [
            'title' => 'Membres',
            'users' => $users->search($term, self::PAGE_SIZE, ($page - 1) * self::PAGE_SIZE),
            'term' => $term,
            'page' => $page,
            'pages' => max(1, (int) ceil($total / self::PAGE_SIZE)),
            'total' => $total,
            'me' => $this->app->auth()->id(),
        ]);
    }

    /** Actions sur un compte : activate, ban, unban, admin, pilot, resend, delete */
    public function user(string $id): void
    {
        $this->checkCsrf();
        $admin = $this->requireAdmin();
        $users = $this->app->users();
        $target = $users->find((int) $id) ?? $this->notFound();
        $action = Request::post('action');
        if ((int) $target['id'] === (int) $admin['id'] && in_array($action, ['ban', 'pilot', 'delete'], true)) {
            $this->flash('error', 'Vous ne pouvez pas appliquer cette action à votre propre compte.');
            $this->back('/admin/users');
        }
        $slots = new SlotRepository($this->app->db(), $this->app->appKey());
        switch ($action) {
            case 'activate':
                $users->update((int) $target['id'], ['status' => 'active', 'email_verified_at' => $target['email_verified_at'] ?? now_utc()]);
                break;
            case 'ban':
                $users->update((int) $target['id'], ['status' => 'banned']);
                $slots->close((int) $target['id']);
                break;
            case 'unban':
                $users->update((int) $target['id'], ['status' => 'active']);
                break;
            case 'admin':
            case 'pilot':
                $users->update((int) $target['id'], ['role' => $action]);
                break;
            case 'resend':
                try {
                    (new AuthService($this->app))->sendVerification($target);
                    $this->flash('success', 'Lien de validation renvoyé.');
                } catch (\RuntimeException $e) {
                    $this->flash('error', "Envoi impossible : {$e->getMessage()}");
                }
                break;
            case 'delete':
                $teams = new TeamRepository($this->app->db());
                foreach ($this->app->db()->fetchAll('SELECT id FROM {teams} WHERE leader_id = :user', ['user' => $target['id']]) as $team) {
                    $teams->dissolve((int) $team['id']);
                }
                $users->delete((int) $target['id']);
                $this->flash('success', "Compte {$target['callsign']} supprimé.");
                $this->redirect('/admin/users');
        }
        $this->back('/admin/users');
    }

    public function teams(): void
    {
        $this->requireAdmin();
        $this->page('admin/teams', ['title' => 'Équipes', 'teams' => (new TeamRepository($this->app->db()))->all()]);
    }

    public function servers(): void
    {
        $this->requireAdmin();
        $this->page('admin/servers', ['title' => 'Serveurs', 'servers' => (new ServerRepository($this->app->db()))->all()]);
    }

    public function settingsForm(): void
    {
        $this->requireAdmin();
        $this->page('admin/settings', [
            'title' => 'Réglages',
            'settings' => $this->app->settings(),
            'mailConfigured' => $this->app->mailer()->isConfigured(),
            'certificateExpires' => $this->app->keys()->expiresAt(),
            'migrations' => (new Migrator($this->app->db()))->pending(),
            'transportForced' => $this->app->config()->get('mail_transport'),
        ]);
    }

    public function saveSettings(): void
    {
        $this->checkCsrf();
        $this->requireAdmin();
        $values = [];
        foreach (array_keys(Settings::DEFAULTS) as $name) {
            if ($name === 'smtp_pass') {
                // champ vide : mot de passe inchangé
                if (Request::raw('smtp_pass') !== '') {
                    $values[$name] = Request::raw('smtp_pass');
                }
                continue;
            }
            if (in_array($name, ['registration_open', 'require_email_verification', 'users_can_create_servers'], true)) {
                $values[$name] = Request::post($name) === '1' ? '1' : '0';
                continue;
            }
            if (isset($_POST[$name])) {
                $values[$name] = Request::post($name);
            }
        }
        if (isset($values['timezone']) && !in_array($values['timezone'], \DateTimeZone::listIdentifiers(), true)) {
            unset($values['timezone']);
        }
        if (isset($values['mail_transport']) && !in_array($values['mail_transport'], ['mail', 'smtp'], true)) {
            unset($values['mail_transport']);
        }
        $this->app->settings()->save($values);
        $this->flash('success', 'Réglages enregistrés.');
        $this->redirect('/admin/settings');
    }

    public function testMail(): void
    {
        $this->checkCsrf();
        $admin = $this->requireAdmin();
        try {
            $site = $this->app->settings()->get('site_name');
            $this->app->mailer()->send($admin['email'], "[$site] E-mail de test", "Si vous lisez ce message, l'envoi d'e-mails du portail fonctionne.\n");
            $this->flash('success', "E-mail de test envoyé à {$admin['email']}.");
        } catch (\RuntimeException $e) {
            $this->flash('error', 'Échec : ' . $e->getMessage());
        }
        $this->redirect('/admin/settings');
    }

    /** Nouvelle paire RSA : les clients connectés devront se reconnecter */
    public function regenerateKeys(): void
    {
        $this->checkCsrf();
        $this->requireAdmin();
        $this->app->keys()->generate($this->app->settings()->get('site_name'));
        $this->flash('success', 'Nouvelle paire de clés générée. Les clients ffs2play la récupèrent automatiquement à leur prochaine connexion.');
        $this->redirect('/admin/settings');
    }

    public function migrate(): void
    {
        $this->checkCsrf();
        $this->requireAdmin();
        $done = (new Migrator($this->app->db()))->migrate();
        $this->flash('success', $done ? 'Mises à jour appliquées : ' . implode(', ', $done) : 'La base de données est déjà à jour.');
        $this->redirect('/admin/settings');
    }
}
