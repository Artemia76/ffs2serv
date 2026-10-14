<?php
/*
 * Installeur web du portail FFS2play. Se verrouille dès que config/config.php existe.
 */
declare(strict_types=1);

// public/ comme racine web, ou installation « à plat » (contenu de public/ à côté de src/)
define('FFS2_PUBLIC', dirname(__DIR__));
require (is_dir(dirname(__DIR__) . '/src') ? dirname(__DIR__) : dirname(__DIR__, 2)) . '/src/bootstrap.php';

use Ffs2\Core\App;
use Ffs2\Core\Config;
use Ffs2\Core\Request;
use Ffs2\Install\Installer;

$app = App::boot();
header('X-Frame-Options: DENY');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data:; form-action 'self'");

if (Config::exists()) {
    $app->view()->page('install/done', ['title' => 'Installation', 'already' => true]);
    exit;
}

$errors = [];
$input = [
    'db_host' => 'localhost', 'db_port' => '3306', 'db_name' => '', 'db_user' => '', 'db_pass' => '', 'db_prefix' => 'ffs2_',
    'site_name' => 'FFS2play', 'base_url' => '', 'admin_callsign' => '', 'admin_email' => '', 'admin_password' => '',
];

if (Request::isPost()) {
    if (!$app->csrf()->check(Request::post('_csrf'))) {
        $errors[] = 'Le formulaire a expiré, veuillez réessayer.';
    } else {
        foreach (array_keys($input) as $field) {
            $input[$field] = in_array($field, ['db_pass', 'admin_password'], true) ? Request::raw($field) : Request::post($field);
        }
        $input['pretty_urls'] = detectRewrite($app->router()->basePath());
        $errors = Installer::requirementsMet() ? Installer::install($input) : ['Les prérequis ne sont pas remplis.'];
        if (!$errors) {
            $app->view()->page('install/done', ['title' => 'Installation terminée', 'already' => false]);
            exit;
        }
    }
}

$input['db_pass'] = $input['admin_password'] = '';
$app->view()->page('install/form', [
    'title' => 'Installation',
    'requirements' => Installer::requirements(),
    'errors' => $errors,
    'input' => $input,
]);

/**
 * La réécriture d'URL fonctionne-t-elle ? Une URL inexistante doit être prise en charge par index.php
 * (qui redirige vers l'installeur tant que le portail n'est pas installé).
 */
function detectRewrite(string $basePath): bool
{
    $url = Request::origin() . $basePath . '/rewrite-check-' . bin2hex(random_bytes(4));
    $context = stream_context_create(['http' => ['method' => 'GET', 'follow_location' => 0, 'timeout' => 3, 'ignore_errors' => true],
        'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
    $result = @file_get_contents($url, false, $context);
    if ($result === false || !isset($http_response_header[0])) {
        return true; // test impossible depuis le serveur : on suppose la réécriture active
    }
    return !str_contains($http_response_header[0], '404');
}
