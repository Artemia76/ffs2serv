<?php
/*
 * Protocole du client ffs2play (action.php), requêtes construites comme web/webmanager.cpp
 */
use Ffs2\Client\Crypto;

echo "client ffs2play\n";

function client_request(string $base, string $switch, string $key, string $body = '', string $version = 'ffs2play_2_4_0', string $source = '127.0.0.1'): ?SimpleXMLElement
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<FFS2Play><version>$version</version>"
        . '<switch><data>' . $switch . '</data><key>' . htmlspecialchars($key) . "</key></switch>$body</FFS2Play>\n";
    $browser = new Browser($base, $source);
    $browser->raw('/action.php', $xml, "text/xml; encoding='utf-8'");
    $parsed = @simplexml_load_string($browser->body);
    return $parsed === false ? null : $parsed;
}

/** hello puis verify, comme le client : retourne [réponse, clé AES] */
function client_login(string $base, string $callsign, string $password, string $source = '127.0.0.1'): array
{
    $hello = client_request($base, 'hello', '', "<hello><pilotID>$callsign</pilotID></hello>", 'ffs2play_2_4_0', $source);
    $aes = random_bytes(32);
    openssl_public_encrypt($aes, $crypted, openssl_pkey_get_public((string) $hello->certificat), OPENSSL_PKCS1_PADDING);
    $localIp = base64_encode("\x0a\x0c192.168.1.10");
    $verify = client_request($base, 'verify', '', '<verify><AES>' . base64_encode($crypted) . "</AES><pilotID>$callsign</pilotID><password>"
        . Crypto::encrypt($password, $aes) . "</password><port>54857</port><local_ip>$localIp</local_ip></verify>", 'ffs2play_2_4_0', $source);
    return [$verify, $aes, $hello];
}

function whazzup(SimpleXMLElement $response): array
{
    $players = [];
    foreach ($response->whazzup->children() as $player) {
        $players[$player->getName()] = $player;
    }
    ksort($players);
    return $players;
}

function live_update(string $base, string $key, float $lat, float $lon, int $alt, string $phase, int $onGround): ?SimpleXMLElement
{
    return client_request($base, 'liveupdate', $key, "<liveupdate><registration>Robin DR400</registration><latitude>$lat</latitude>"
        . "<longitude>$lon</longitude><heading>270</heading><altitude>$alt</altitude><groundSpeed>110</groundSpeed>"
        . "<iaspeed>105</iaspeed><status>$phase</status><category>Airplane</category><squawk>7000</squawk>"
        . "<onground>$onGround</onground><sim>FS2020</sim></liveupdate>");
}

[$hello] = [client_request($base, 'hello', '', '<hello><pilotID>Alpha</pilotID></hello>')];
check_equal('world', (string) $hello->hello, 'hello : réponse');
check(str_contains((string) $hello->certificat, 'BEGIN CERTIFICATE'), 'hello : certificat du serveur');

[$login, $keyA] = client_login($base, 'Alpha', $pilots['Alpha']);
check_equal('1', (string) $login->loginStatus, 'verify : connexion avec le compte du site');
check(preg_match('/^[0-9a-f]{32}$/', (string) $login->key) === 1, 'verify : clé de rappel aléatoire');
check_equal('1', (string) $login->atc, 'verify : envoi des positions activé');
check_equal(['Alpha'], array_keys(whazzup($login)), 'verify : le pilote se voit lui-même (mode shadow)');
check_equal('Alpha', (string) whazzup($login)['Alpha']['team'], 'verify : attribut team = indicatif du chef');
check(str_contains((string) $login->erreur, 'Public'), 'verify : message de bienvenue avec le serveur');
$keyAlpha = (string) $login->key;

[$bad] = client_login($base, 'Bravo_2', 'mauvais');
check(((string) $bad->loginStatus) === '0' && ((string) $bad->erreur['error']) === '2', 'verify : mauvais mot de passe refusé');
[$banned] = client_login($base, 'Charlie', $pilots['Charlie']);
check(((string) $banned->loginStatus) === '0' && str_contains((string) $banned->erreur, 'suspendu'), 'verify : compte suspendu refusé');
$app->users()->create('Delta', 'delta@example.org', 'delta-password');
[$pending] = client_login($base, 'Delta', 'delta-password');
check(str_contains((string) $pending->erreur, 'pas encore activé'), 'verify : compte non validé refusé');
$old = client_request($base, 'verify', '', '<verify><pilotID>Alpha</pilotID></verify>', 'ffstracker_1_9_0');
check_equal('0', (string) $old->loginStatus, 'verify : ancien client refusé');

[$loginB, $keyB] = client_login($base, 'Bravo_2', $pilots['Bravo_2']);
$keyBravo = (string) $loginB->key;
$players = whazzup($loginB);
check_equal(['Alpha', 'Bravo_2'], array_keys($players), 'verify : les deux pilotes du serveur public');
check_equal('Alpha', (string) $players['Bravo_2']['team'], 'équipier : team = chef (masqué comme IA par le client)');
check_equal('127.0.0.1', Crypto::decrypt((string) $players['Alpha']['ip'], $keyB), 'IP cryptée avec la clé du demandeur');
check_equal(base64_encode("\x0a\x0c192.168.1.10"), (string) $players['Alpha']['local_ip'], 'IP locales transmises telles quelles');

// positions et page d'accueil
$update = live_update($base, $keyAlpha, 48.72528, 2.35944, 1500, 'Climb', 0);
check(isset($update->whazzup) && count($update->whazzup->children()) === 2, 'liveupdate : liste des pilotes');
client_request($base, 'atc', $keyAlpha, '<atc><latitude>48.73</latitude><longitude>2.30</longitude><heading>275</heading>'
    . '<altitude>1800</altitude><groundSpeed>112</groundSpeed><iaspeed>106</iaspeed><squawk>7000</squawk></atc>');
$position = $app->db()->fetch("SELECT p.* FROM {positions} p JOIN {users} u ON u.id = p.user_id WHERE u.callsign = 'Alpha'");
check($position !== null && (int) $position['altitude'] === 1800 && $position['phase'] === 'Climb' && $position['aircraft'] === 'Robin DR400',
    'atc : position mise à jour, phase et appareil conservés');
$liveRaw = (new Browser($base))->get('/api/live')->body;
$live = json_decode($liveRaw, true);
$alphaLive = array_values(array_filter($live['pilots'], fn ($p) => $p['callsign'] === 'Alpha'))[0] ?? null;
check($alphaLive !== null && $alphaLive['position']['altitude'] === 1800 && $alphaLive['sim'] === 'FS2020', '/api/live : position et simulateur');
check(!str_contains($liveRaw, '127.0.0.1') && !str_contains($liveRaw, '192.168'), "/api/live : aucune adresse IP exposée");

// serveurs : Alpha passe sur son serveur privé depuis le site
$alpha = new Browser($base);
$alpha->get('/login');
$alpha->post('/login', ['login' => 'Alpha', 'password' => $pilots['Alpha']]);
$alpha->get('/profile');
$alpha->post('/profile/server', ['server_id' => $serverId]);
$update = live_update($base, $keyAlpha, 48.8, 2.2, 3000, 'Cruise', 0);
check_equal(['Alpha'], array_keys(whazzup($update)), 'serveur privé : Alpha ne voit plus le serveur public');
$updateB = live_update($base, $keyBravo, 45.7, 5.0, 0, 'Taxiway', 1);
check_equal(['Bravo_2'], array_keys(whazzup($updateB)), 'serveur public : Bravo ne voit plus Alpha');
$anonymous = json_decode((new Browser($base))->get('/api/live')->body, true);
check_equal(['Bravo_2'], array_column($anonymous['pilots'], 'callsign'), '/api/live : pilotes des serveurs privés masqués aux visiteurs');
$member = json_decode($bravo->get('/api/live')->body, true);
check_equal(['Alpha', 'Bravo_2'], array_column($member['pilots'], 'callsign'), '/api/live : visibles par les membres du serveur privé');

// mode global : les pilotes en mode global se voient entre serveurs
$alpha->get('/profile');
$alpha->post('/profile/server', ['server_id' => $serverId, 'global_mode' => '1']);
$bravo->get('/profile');
$bravo->post('/profile/server', ['server_id' => (string) $app->db()->fetchValue('SELECT id FROM {servers} WHERE is_default = 1'), 'global_mode' => '1']);
check_equal(['Alpha', 'Bravo_2'], array_keys(whazzup(live_update($base, $keyBravo, 45.7, 5.0, 0, 'Taxiway', 1))), 'mode global : pilotes visibles entre serveurs');

// erreurs, déconnexion
$denied = client_request($base, 'liveupdate', str_repeat('0', 32));
check_equal('2', (string) $denied->erreur['error'], 'liveupdate : clé inconnue => erreur de niveau 2 (reconnexion du client)');
client_request($base, 'logout', $keyBravo);
check_equal(['Alpha'], array_keys(whazzup(live_update($base, $keyAlpha, 48.8, 2.2, 3000, 'Cruise', 0))), 'logout : le pilote disparaît');
check_equal(0, (int) $app->db()->fetchValue("SELECT COUNT(*) FROM {connections} c JOIN {users} u ON u.id = c.user_id WHERE u.callsign = 'Bravo_2' AND c.disconnected_at IS NULL"),
    'logout : connexion clôturée');

for ($i = 0; $i < 5; $i++) {
    client_login($base, 'Bravo_2', 'mauvais', '127.0.0.4');
}
[$limited] = client_login($base, 'Bravo_2', $pilots['Bravo_2'], '127.0.0.4');
check(str_contains((string) $limited->erreur, 'Trop de tentatives'), 'verify : limitation des essais');
$app->db()->execute('DELETE FROM {login_attempts}');

$garbage = (new Browser($base))->raw('/action.php', 'pas du xml', 'text/xml');
check(@simplexml_load_string($garbage->body) !== false, 'requête invalide : réponse XML valide');

// IPv6 (fiche 0003) : requêtes passées directement au contrôleur avec l'adresse observée
function direct_request(Ffs2\Core\App $app, string $switch, string $key, string $body, string $ip): SimpleXMLElement
{
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n<FFS2Play><version>ffs2play_2_4_0</version>"
        . '<switch><data>' . $switch . '</data><key>' . htmlspecialchars($key) . "</key></switch>$body</FFS2Play>\n";
    return simplexml_load_string((new Ffs2\Client\ClientController($app))->handle($xml, $ip)->toXml());
}

$hello6 = direct_request($app, 'hello', '', '<hello><pilotID>Bravo_2</pilotID></hello>', '2001:db8::2');
check(str_contains((string) $hello6->certificat, 'BEGIN CERTIFICATE') && (string) $hello6->your_ip === '2001:db8::2', 'IPv6 : hello accepté');
$aes6 = random_bytes(32);
openssl_public_encrypt($aes6, $crypted6, openssl_pkey_get_public((string) $hello6->certificat), OPENSSL_PKCS1_PADDING);
$login6 = direct_request($app, 'verify', '', '<verify><AES>' . base64_encode($crypted6) . '</AES><pilotID>Bravo_2</pilotID><password>'
    . Crypto::encrypt($pilots['Bravo_2'], $aes6) . '</password><port>54857</port><local_ip>na</local_ip></verify>', '2001:db8::2');
check_equal('1', (string) $login6->loginStatus, 'IPv6 : verify accepté');
$key6 = (string) $login6->key;
$bravo6 = whazzup($login6)['Bravo_2'] ?? null;
check($bravo6 !== null && (string) $bravo6['ip'] === '' && Crypto::decrypt((string) $bravo6['ip6'], $aes6) === '2001:db8::2',
    'IPv6 : Whazzup avec ip6 seule');

$addr4 = direct_request($app, 'addr', $key6, '', '::ffff:198.51.100.7');
check_equal('198.51.100.7', (string) $addr4->your_ip, 'addr : adresse IPv4-mapped normalisée');
$both = whazzup(direct_request($app, 'liveupdate', $key6, '', '2001:db8::2'))['Bravo_2'];
check(Crypto::decrypt((string) $both['ip'], $aes6) === '198.51.100.7' && Crypto::decrypt((string) $both['ip6'], $aes6) === '2001:db8::2',
    'addr : IPv4 ajoutée au slot IPv6, les deux dans le Whazzup');

direct_request($app, 'addr', str_repeat('0', 32), '', '203.0.113.9');
$slot6 = $app->db()->fetch("SELECT s.* FROM {slots} s JOIN {users} u ON u.id = s.user_id WHERE u.callsign = 'Bravo_2'");
check_equal('198.51.100.7', $slot6['ip'], 'addr : clé inconnue sans effet');

$app->db()->execute('UPDATE {slots} SET ip6_seen_at = :old WHERE user_id = :user', ['old' => now_utc(-200), 'user' => $slot6['user_id']]);
$stale = whazzup(direct_request($app, 'liveupdate', $key6, '', '198.51.100.7'))['Bravo_2'];
check(!isset($stale['ip6']) && Crypto::decrypt((string) $stale['ip'], $aes6) === '198.51.100.7', 'Whazzup : IPv6 trop ancienne non donnée');
$app->db()->execute('UPDATE {slots} SET ip_seen_at = :old WHERE user_id = :user', ['old' => now_utc(-200), 'user' => $slot6['user_id']]);
$repository = new Ffs2\Domain\SlotRepository($app->db(), $app->appKey());
check(!in_array('Bravo_2', array_column($repository->whazzup($slot6, $aes6), 'name'), true), 'Whazzup : pilote sans adresse récente non listé');
direct_request($app, 'logout', $key6, '', '198.51.100.7');
