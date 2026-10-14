<?php
/*
 * Contrôleur frontal des pages web du portail FFS2play
 */
declare(strict_types=1);

// serveur intégré de PHP (développement, tests) : fichiers statiques servis directement
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $dir = rtrim($file, '/');
    if ($file !== __FILE__ && $dir !== __DIR__ && (is_file($file) || is_file($dir . '/index.php'))) {
        return false;
    }
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Ffs2\Core\App;
use Ffs2\Core\Request;
use Ffs2\Web\AdminController;
use Ffs2\Web\AuthController;
use Ffs2\Web\HomeController;
use Ffs2\Web\ProfileController;
use Ffs2\Web\ServerController;
use Ffs2\Web\TeamController;

$app = App::boot();
$router = $app->router();

if (!$app->installed()) {
    header('Location: ' . $router->basePath() . '/install/', true, 302);
    exit;
}

header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline'; script-src 'self'; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");

$home = new HomeController($app);
$auth = new AuthController($app);
$profile = new ProfileController($app);
$servers = new ServerController($app);
$teams = new TeamController($app);
$admin = new AdminController($app);

$router->get('/', [$home, 'index']);
$router->get('/api/live', [$home, 'live']);

$router->get('/register', fn () => $auth->registerForm());
$router->post('/register', [$auth, 'register']);
$router->get('/login', fn () => $auth->loginForm());
$router->post('/login', [$auth, 'login']);
$router->post('/logout', [$auth, 'logout']);
$router->get('/verify', [$auth, 'verify']);
$router->get('/verify/resend', [$auth, 'resendForm']);
$router->post('/verify/resend', [$auth, 'resend']);
$router->get('/forgot', [$auth, 'forgotForm']);
$router->post('/forgot', [$auth, 'forgot']);
$router->get('/reset', fn () => $auth->resetForm());
$router->post('/reset', [$auth, 'reset']);

$router->get('/profile', fn () => $profile->show());
$router->post('/profile/server', [$profile, 'chooseServer']);
$router->post('/profile/password', [$profile, 'changePassword']);
$router->post('/profile/email', [$profile, 'changeEmail']);
$router->get('/pilots/{callsign}', [$profile, 'publicProfile']);
$router->get('/flights/{id}', [$profile, 'flight']);

$router->get('/servers', [$servers, 'index']);
$router->post('/servers', [$servers, 'create']);
$router->get('/servers/{id}', [$servers, 'show']);
$router->post('/servers/{id}/edit', [$servers, 'update']);
$router->post('/servers/{id}/delete', [$servers, 'delete']);
$router->post('/servers/{id}/join', [$servers, 'join']);
$router->post('/servers/{id}/leave', [$servers, 'leave']);
$router->post('/servers/{id}/members', [$servers, 'member']);

$router->get('/teams', [$teams, 'index']);
$router->post('/teams', [$teams, 'create']);
$router->get('/teams/{id}', [$teams, 'show']);
$router->post('/teams/{id}/join', [$teams, 'join']);
$router->post('/teams/{id}/cancel', [$teams, 'cancel']);
$router->post('/teams/{id}/leave', [$teams, 'leave']);
$router->post('/teams/{id}/manage', [$teams, 'manage']);

$router->get('/admin', [$admin, 'dashboard']);
$router->post('/admin/kick/{id}', [$admin, 'kick']);
$router->get('/admin/users', [$admin, 'users']);
$router->post('/admin/users/{id}', [$admin, 'user']);
$router->get('/admin/servers', [$admin, 'servers']);
$router->get('/admin/teams', [$admin, 'teams']);
$router->get('/admin/settings', [$admin, 'settingsForm']);
$router->post('/admin/settings', [$admin, 'saveSettings']);
$router->post('/admin/settings/test-mail', [$admin, 'testMail']);
$router->post('/admin/settings/keys', [$admin, 'regenerateKeys']);
$router->post('/admin/settings/migrate', [$admin, 'migrate']);

try {
    if (!$router->dispatch(Request::method(), $router->currentPath())) {
        $app->view()->page('error', ['title' => 'Page introuvable', 'message' => "Cette page n'existe pas."], http_response_code() === 405 ? 405 : 404);
    }
} catch (Throwable $e) {
    error_log('FFS2play : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
    if (!headers_sent()) {
        http_response_code(500);
    }
    $message = $app->config()->get('debug') ? $e->getMessage() : 'Une erreur est survenue. Réessayez plus tard.';
    echo $app->view()->render('layout', ['title' => 'Erreur', 'content' => '<section class="card"><h1>Erreur</h1><p>' . e($message) . '</p></section>']);
}
