<?php
/*
 * Scénario complet hello / verify / liveupdate / logout contre le serveur intégré de PHP,
 * avec des requêtes construites comme celles du client FFS2play (web/webmanager.cpp)
 */
echo "protocole\n";

$root = dirname (__DIR__);
$work = temp_dir ('ffs2serv_');
$config = $work . '/config_gen.php';
$sessions = $work . '/sessions';
$password = 'mot de passe "test" $x';

// configuration générée par l'outil livré
exec (escapeshellarg (PHP_BINARY) . ' ' . escapeshellarg ($root . '/tools/generate_config.php')
	. ' --output=' . escapeshellarg ($config) . ' --password=' . escapeshellarg ($password) . ' --name=Test', $out, $status);
check_equal (0, $status, 'génération de la configuration');

// serveur PHP intégré
$port = 18000 + random_int (0, 999);
$env = array_merge (getenv (), array ('FFS2SERV_CONFIG' => $config, 'FFS2SERV_SESSION_DIR' => $sessions));
$server = proc_open (array (PHP_BINARY, '-S', "127.0.0.1:$port", '-t', $root),
	array (0 => array ('pipe', 'r'), 1 => array ('file', $work . '/server.log', 'a'), 2 => array ('file', $work . '/server.log', 'a')),
	$pipes, $root, $env);
for ($i = 0; $i < 50 and @fsockopen ('127.0.0.1', $port) === false; $i++) {
	usleep (100000);
}

/**
 * Requête XML au format du client FFS2play
 */
function client_request ($port, $switch, $key, $body = '', $version = 'ffs2play_2_4_0', $source = '127.0.0.1')
{
	$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<FFS2Play><version>$version</version>"
		. "<switch><data>$switch</data><key>" . htmlspecialchars ($key) . "</key></switch>$body</FFS2Play>\n";
	$context = stream_context_create (array (
		'http'   => array ('method' => 'POST', 'header' => "Content-Type: text/xml; encoding='utf-8'\r\nUser-Agent: FFS2Play\r\n", 'content' => $xml, 'ignore_errors' => TRUE),
		'socket' => array ('bindto' => "$source:0"),
	));
	$response = file_get_contents ("http://127.0.0.1:$port/action.php", false, $context);
	$parsed = simplexml_load_string ($response);
	if ($parsed === false) {
		echo "  réponse invalide : $response\n";
	}
	return $parsed;
}

/**
 * Connexion d'un joueur comme le fait le client : hello, puis verify avec une clé AES cryptée en RSA
 */
function client_login ($port, $name, $password, $local_ip, $source = '127.0.0.1')
{
	$hello = client_request ($port, 'hello', '', "<hello><pilotID>$name</pilotID></hello>", 'ffs2play_2_4_0', $source);
	$aes_key = random_bytes (32);
	openssl_public_encrypt ($aes_key, $crypted_key, openssl_pkey_get_public (strval ($hello->certificat)), OPENSSL_PKCS1_PADDING);
	$verify = client_request ($port, 'verify', '', '<verify><AES>' . base64_encode ($crypted_key) . '</AES>'
		. "<pilotID>$name</pilotID><password>" . encrypt ($password, $aes_key, TRUE) . '</password>'
		. "<port>54857</port><local_ip>$local_ip</local_ip></verify>", 'ffs2play_2_4_0', $source);
	return array ($verify, $aes_key, $hello);
}

function whazzup_names ($response)
{
	$names = array ();
	foreach ($response->whazzup->children () as $player) {
		$names[] = $player->getName ();
	}
	return $names;
}

try {
	// IP locales au format du client : StringArray protobuf (champ 1, chaînes) en base64
	$local_ip_a = base64_encode ("\x0a\x0c192.168.1.10");
	$local_ip_b = base64_encode ("\x0a\x0810.0.0.2");

	list ($login_a, $key_a, $hello) = client_login ($port, 'Alpha', $password, $local_ip_a);
	check_equal ('world', strval ($hello->hello), 'hello : prise en charge du cryptage dynamique');
	check (strpos (strval ($hello->certificat), 'BEGIN CERTIFICATE') !== false, 'hello : certificat envoyé');
	check_equal ('1', strval ($login_a->loginStatus), 'verify : connexion acceptée');
	check (preg_match ('/^[0-9a-f]{32}$/', strval ($login_a->key)) === 1, 'verify : clé de rappel aléatoire');
	check_equal ('127.0.0.1', strval ($login_a->your_ip), 'verify : IP publique renvoyée');
	check_equal (array ('Alpha'), whazzup_names ($login_a), 'verify : le joueur se voit lui-même (mode shadow)');

	list ($login_b, $key_b) = client_login ($port, 'Bravo_2', $password, $local_ip_b);
	check_equal ('1', strval ($login_b->loginStatus), 'verify : second joueur accepté');
	$names = whazzup_names ($login_b);
	sort ($names);
	check_equal (array ('Alpha', 'Bravo_2'), $names, 'verify : le second joueur voit le premier et lui-même');

	$update = client_request ($port, 'liveupdate', strval ($login_a->key));
	$names = whazzup_names ($update);
	sort ($names);
	check_equal (array ('Alpha', 'Bravo_2'), $names, 'liveupdate : liste complète, demandeur compris');
	check_equal ('127.0.0.1', decrypt (strval ($update->whazzup->Alpha['ip']), $key_a, TRUE), 'liveupdate : propre entrée décryptable (mode shadow)');
	$bravo = $update->whazzup->Bravo_2;
	check_equal ('127.0.0.1', decrypt (strval ($bravo['ip']), $key_a, TRUE), 'liveupdate : IP cryptée avec la clé du demandeur');
	check_equal ($local_ip_b, strval ($bravo['local_ip']), 'liveupdate : IP locales transmises telles quelles');
	check_equal ('54857', strval ($bravo['port']), 'liveupdate : port P2P');

	$denied = client_request ($port, 'liveupdate', 'cle-inconnue');
	check_equal ('2', strval ($denied->erreur['error']), 'liveupdate : clé inconnue => erreur de niveau 2 (déconnexion du client)');

	list ($bad) = client_login ($port, 'Charlie', 'mauvais', $local_ip_a);
	check_equal ('0', strval ($bad->loginStatus), 'verify : mauvais mot de passe refusé');

	list ($traversal) = client_login ($port, '../../config/x', $password, $local_ip_a);
	check_equal ('0', strval ($traversal->loginStatus), 'verify : pseudo avec chemin refusé');
	check (count (glob ($work . '/*.json') ?: array ()) === 0 and !file_exists ($root . '/config/x.json'), 'verify : aucun fichier créé hors des sessions');

	list ($taken) = client_login ($port, 'Alpha', $password, $local_ip_a, '127.0.0.2');
	check_equal ('0', strval ($taken->loginStatus), 'verify : pseudo déjà utilisé depuis une autre IP refusé');

	$old = client_request ($port, 'verify', '', '<verify><pilotID>Old</pilotID></verify>', 'ffstracker_1_9_0');
	check_equal ('0', strval ($old->loginStatus), 'verify : ancien client refusé');

	client_request ($port, 'logout', strval ($login_b->key));
	$update = client_request ($port, 'liveupdate', strval ($login_a->key));
	check_equal (array ('Alpha'), whazzup_names ($update), 'logout : le joueur disparaît de la liste');

	client_request ($port, 'logout', strval ($login_a->key));
	list ($login_c) = client_login ($port, 'Charlie', $password, $local_ip_a, '127.0.0.2');
	client_request ($port, 'logout', strval ($login_c->key));
	$update = client_request ($port, 'liveupdate', strval ($login_c->key));
	check_equal ('2', strval ($update->erreur['error']), 'logout : la clé de rappel n\'est plus valide');

	$garbage = client_request ($port, 'pas du xml', '');
	check ($garbage !== false, 'requête quelconque : réponse XML valide');

	$log = file_get_contents ($work . '/server.log');
	check (preg_match ('/(Warning|Notice|Deprecated|Fatal)/i', $log) !== 1, 'aucune alerte PHP côté serveur');
}
finally {
	proc_terminate ($server);
	proc_close ($server);
	remove_dir ($work);
}
?>
