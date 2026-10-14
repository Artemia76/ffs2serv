<?php
/*
 * Amorçage commun : pages web (public/index.php), client ffs2play (public/action.php),
 * installeur et outils en ligne de commande.
 */
declare(strict_types=1);

define('FFS2_ROOT', dirname(__DIR__));
define('FFS2_VERSION', '2.0.0');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('FFS2play portal requires PHP 8.1 or newer (current: ' . PHP_VERSION . ')');
}

spl_autoload_register(static function (string $class): void {
    if (strncmp($class, 'Ffs2\\', 5) !== 0) {
        return;
    }
    $file = __DIR__ . '/' . str_replace('\\', '/', substr($class, 5)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require __DIR__ . '/helpers.php';

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');
