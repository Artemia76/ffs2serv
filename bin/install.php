<?php
/*
 * Installation en ligne de commande (VPS, Docker, tests) :
 *
 *   php bin/install.php --db-host=localhost --db-name=ffs2play --db-user=ffs2 --db-pass=secret \
 *       --admin-callsign=Admin --admin-email=admin@example.org --admin-password=motdepasse \
 *       [--db-port=3306] [--db-prefix=ffs2_] [--site-name=FFS2play] [--base-url=https://ffs2play.fr] [--no-rewrite]
 *
 *   php bin/install.php --migrate     applique les mises à jour du schéma d'un portail installé
 */
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require dirname(__DIR__) . '/src/bootstrap.php';

use Ffs2\Core\App;
use Ffs2\Core\Config;
use Ffs2\Core\Migrator;
use Ffs2\Install\Installer;

$options = getopt('', ['db-host:', 'db-port:', 'db-name:', 'db-user:', 'db-pass:', 'db-prefix:', 'site-name:', 'base-url:',
    'admin-callsign:', 'admin-email:', 'admin-password:', 'mail-transport:', 'no-rewrite', 'migrate']);

if (isset($options['migrate'])) {
    if (!Config::exists()) {
        fwrite(STDERR, "Portail non installé\n");
        exit(1);
    }
    $done = (new Migrator(App::boot()->db()))->migrate();
    echo $done ? 'Mises à jour appliquées : ' . implode(', ', $done) . "\n" : "Base de données à jour\n";
    exit(0);
}

foreach (Installer::requirements() as $requirement) {
    echo ($requirement['ok'] ? '  ok   ' : '  FAIL ') . $requirement['label'] . "\n";
}
if (!Installer::requirementsMet()) {
    exit(1);
}

$errors = Installer::install([
    'db_host' => $options['db-host'] ?? 'localhost',
    'db_port' => $options['db-port'] ?? '3306',
    'db_name' => $options['db-name'] ?? '',
    'db_user' => $options['db-user'] ?? '',
    'db_pass' => $options['db-pass'] ?? '',
    'db_prefix' => $options['db-prefix'] ?? 'ffs2_',
    'site_name' => $options['site-name'] ?? 'FFS2play',
    'base_url' => $options['base-url'] ?? '',
    'admin_callsign' => $options['admin-callsign'] ?? '',
    'admin_email' => $options['admin-email'] ?? '',
    'admin_password' => $options['admin-password'] ?? '',
    'pretty_urls' => !isset($options['no-rewrite']),
    'mail_transport' => $options['mail-transport'] ?? null,
]);
if ($errors) {
    fwrite(STDERR, implode("\n", $errors) . "\n");
    exit(1);
}
echo 'Portail installé, configuration : ' . Config::path() . "\n";
