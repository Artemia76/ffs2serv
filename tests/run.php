<?php
/*
 * Tests du portail : php tests/run.php
 *
 * Nécessite une base MariaDB/MySQL jetable (elle est recréée à chaque exécution) :
 *   FFS2_TEST_DB_HOST (localhost), FFS2_TEST_DB_PORT (3306), FFS2_TEST_DB_NAME (ffs2play_test),
 *   FFS2_TEST_DB_USER (root), FFS2_TEST_DB_PASS ('')
 * Avec Docker : docker compose up -d && docker compose exec web php tests/run.php
 */
declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

$root = dirname(__DIR__);
$work = temp_dir('ffs2portal_');
putenv('FFS2_CONFIG=' . $work . '/config/config.php');
require $root . '/src/bootstrap.php';

use Ffs2\Core\App;
use Ffs2\Core\Config;
use Ffs2\Install\Installer;

$db = [
    'host' => getenv('FFS2_TEST_DB_HOST') ?: '127.0.0.1',
    'port' => (int) (getenv('FFS2_TEST_DB_PORT') ?: 3306),
    'name' => getenv('FFS2_TEST_DB_NAME') ?: 'ffs2play_test',
    'user' => getenv('FFS2_TEST_DB_USER') ?: 'root',
    'pass' => getenv('FFS2_TEST_DB_PASS') ?: '',
];
echo 'PHP ' . PHP_VERSION . ', ' . OPENSSL_VERSION_TEXT . "\n";

$server = null;
try {
    // base de test vierge
    $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset=utf8mb4", $db['user'], $db['pass'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo 'Serveur SQL ' . $pdo->query('SELECT VERSION()')->fetchColumn() . "\n";
    $pdo->exec("DROP DATABASE IF EXISTS `{$db['name']}`");
    $pdo->exec("CREATE DATABASE `{$db['name']}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

    echo "installation\n";
    $errors = Installer::install([
        'db_host' => $db['host'], 'db_port' => $db['port'], 'db_name' => $db['name'], 'db_user' => $db['user'],
        'db_pass' => $db['pass'], 'db_prefix' => 'test_', 'site_name' => 'Portail de test', 'base_url' => '',
        'admin_callsign' => 'Admin', 'admin_email' => 'admin@example.org', 'admin_password' => 'admin-password',
        'pretty_urls' => true, 'mail_transport' => 'file', 'storage_dir' => $work . '/storage',
    ]);
    check_equal([], $errors, 'installation complète');
    check(Installer::install(['db_prefix' => 'x']) !== [], 'installeur verrouillé après installation');

    // e-mails et données de travail isolés dans le dossier temporaire
    $app = App::boot(Config::load());
    $storage = $work . '/storage';

    // serveur web intégré de PHP sur public/
    $port = 18000 + random_int(0, 999);
    $env = array_merge(getenv(), ['FFS2_CONFIG' => Config::path()]);
    $server = proc_open([PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root . '/public', $root . '/public/index.php'],
        [0 => ['pipe', 'r'], 1 => ['file', $work . '/server.log', 'a'], 2 => ['file', $work . '/server.log', 'a']],
        $pipes, $root, $env);
    for ($i = 0; $i < 50 && @fsockopen('127.0.0.1', $port) === false; $i++) {
        usleep(100000);
    }
    $base = "http://127.0.0.1:$port";

    foreach (['test_crypto.php', 'test_web.php', 'test_client.php', 'test_flights.php'] as $test) {
        try {
            require __DIR__ . '/' . $test;
        } catch (Throwable $e) {
            $GLOBALS['test_failures']++;
            echo '  ERREUR ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
        }
    }

    $log = (string) @file_get_contents($work . '/server.log');
    check(preg_match('/(PHP (Warning|Notice|Deprecated|Fatal)|FFS2play :)/', $log) !== 1, 'aucune alerte PHP côté serveur');
    if (preg_match_all('/^.*(PHP (Warning|Notice|Deprecated|Fatal)|FFS2play :).*$/m', $log, $lines)) {
        echo '    ' . implode("\n    ", array_slice($lines[0], 0, 10)) . "\n";
    }
} catch (Throwable $e) {
    $GLOBALS['test_failures']++;
    echo '  ERREUR ' . get_class($e) . ': ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
} finally {
    if ($server) {
        proc_terminate($server);
        proc_close($server);
    }
    remove_dir($work);
}

echo "\n" . $GLOBALS['test_count'] . ' vérifications, ' . $GLOBALS['test_failures'] . " échec(s)\n";
exit($GLOBALS['test_failures'] === 0 ? 0 : 1);
