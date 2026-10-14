<?php
/*
 * Point d'entrée du client ffs2play : le client appelle <URL du site>/action.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/bootstrap.php';

use Ffs2\Client\ClientController;
use Ffs2\Core\App;
use Ffs2\Core\Request;

header('Content-Type: text/xml; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

$app = App::boot();
if (!$app->installed()) {
    http_response_code(503);
    exit('<?xml version="1.0" encoding="UTF-8"?>' . "\n<info><erreur error=\"2\">Serveur non installé</erreur></info>\n");
}

if (!Request::isPost()) {
    exit('<?xml version="1.0" encoding="UTF-8"?>' . "\n<info/>\n");
}

try {
    $response = (new ClientController($app))->handle((string) file_get_contents('php://input'), Request::ip());
    echo $response->toXml();
} catch (Throwable $e) {
    error_log('FFS2play action.php : ' . $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')');
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n<info><loginStatus>0</loginStatus><erreur error=\"2\">Erreur interne du serveur</erreur></info>\n";
}
