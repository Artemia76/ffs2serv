<?php
/*
 * Pages web : inscription, validation d'e-mail, connexion, profil, serveurs, équipes, administration
 */
echo "web\n";

/** Inscrit et valide un pilote, retourne son navigateur connecté */
function register_pilot(string $base, string $storage, string $callsign, string $password): Browser
{
    $browser = new Browser($base);
    $browser->get('/register');
    $browser->post('/register', ['callsign' => $callsign, 'email' => strtolower($callsign) . '@example.org',
        'password' => $password, 'password_confirmation' => $password]);
    if (preg_match('#/verify\?token=([0-9a-f]{64})#', last_mail($storage), $m)) {
        $browser->get('/verify?token=' . $m[1]);
    }
    return $browser;
}

$home = (new Browser($base))->get('/');
check_equal(200, $home->status, 'accueil');
check(str_contains($home->body, 'Pilotes en direct') && str_contains($home->body, 'Portail de test'), 'accueil : titre et nom du site');

$live = json_decode((new Browser($base))->get('/api/live')->body, true);
check(is_array($live) && $live['servers'][0]['name'] === 'Public' && $live['pilots'] === [], '/api/live : serveur public, aucun pilote');

// inscription
$alpha = new Browser($base);
$alpha->get('/register');
$alpha->post('/register', ['callsign' => '1nvalide', 'email' => 'x@example.org', 'password' => 'password1', 'password_confirmation' => 'password1']);
check(str_contains($alpha->body, "L&#039;indicatif doit"), 'inscription : indicatif invalide refusé');
$alpha->post('/register', ['callsign' => 'Alpha', 'email' => 'alpha@example.org', 'password' => 'alpha-password', 'password_confirmation' => 'alpha-password']);
check_equal(303, $alpha->status, 'inscription : compte créé');
$mail = last_mail($storage);
check(str_contains($mail, 'alpha@example.org') && preg_match('#/verify\?token=([0-9a-f]{64})#', $mail, $token) === 1, 'inscription : e-mail de validation envoyé');

$alpha->get('/login');
$alpha->post('/login', ['login' => 'Alpha', 'password' => 'alpha-password']);
check(str_contains($alpha->body, 'pas encore activé'), 'connexion refusée avant validation');

$alpha->get('/register');
$alpha->post('/register', ['callsign' => 'alpha', 'email' => 'other@example.org', 'password' => 'alpha-password', 'password_confirmation' => 'alpha-password']);
check(str_contains($alpha->body, 'déjà utilisé'), 'inscription : indicatif unique sans tenir compte de la casse');

$alpha->get('/verify?token=' . $token[1]);
check_equal(303, $alpha->status, 'validation e-mail : redirection');
$alpha->follow();
check(str_contains($alpha->body, 'Connecter ffs2play') && str_contains($alpha->body, $base), 'profil : aide de connexion ffs2play avec l\'URL du site');
check_equal(303, (new Browser($base))->get('/verify?token=' . $token[1])->status, 'lien de validation à usage unique');

// CSRF
$alpha->get('/profile');
$alpha->post('/profile/password', ['_csrf' => 'faux', 'current_password' => 'alpha-password', 'password' => 'x', 'password_confirmation' => 'x']);
check_equal(419, $alpha->status, 'CSRF : formulaire sans jeton valide refusé');

// limitation des essais de connexion
$attacker = new Browser($base, '127.0.0.3');
for ($i = 0; $i < 5; $i++) {
    $attacker->get('/login');
    $attacker->post('/login', ['login' => 'Admin', 'password' => 'mauvais']);
}
$attacker->get('/login');
$attacker->post('/login', ['login' => 'Admin', 'password' => 'admin-password']);
check(str_contains($attacker->body, 'Trop de tentatives'), 'limitation des essais de connexion');
$app->db()->execute('DELETE FROM {login_attempts}');

// second pilote, pilote suspendu
$bravo = register_pilot($base, $storage, 'Bravo_2', 'bravo-password');
check(str_contains($bravo->get('/profile')->body, 'Bravo_2'), 'second pilote inscrit et connecté');
$charlie = register_pilot($base, $storage, 'Charlie', 'charlie-password');

// administration
check_equal(403, $alpha->get('/admin')->status, 'administration refusée à un pilote');
$admin = new Browser($base);
$admin->get('/login');
$admin->post('/login', ['login' => 'admin@example.org', 'password' => 'admin-password']);
check_equal(200, $admin->get('/admin')->status, "administration accessible à l'admin (connexion par e-mail)");
$charlieId = (int) $app->db()->fetchValue("SELECT id FROM {users} WHERE callsign = 'Charlie'");
$admin->get('/admin/users');
$admin->post('/admin/users/' . $charlieId, ['action' => 'ban']);
check_equal('banned', $app->db()->fetchValue('SELECT status FROM {users} WHERE id = :id', ['id' => $charlieId]), 'admin : suspension d\'un compte');
check_equal(303, $charlie->get('/profile')->status, 'un compte suspendu perd sa session');

// serveurs privés
$alpha->get('/servers');
$alpha->post('/servers', ['name' => 'Escadrille', 'description' => 'Serveur privé de test', 'is_private' => '1']);
check_equal(303, $alpha->status, 'création d\'un serveur privé');
$serverId = (int) $app->db()->fetchValue("SELECT id FROM {servers} WHERE name = 'Escadrille'");
$bravo->get('/profile');
$bravo->post('/profile/server', ['server_id' => $serverId]);
$bravo->follow();
check(str_contains($bravo->body, "pas accès à ce serveur"), 'serveur privé refusé à un non-membre');
$bravo->get('/servers/' . $serverId);
$bravo->post('/servers/' . $serverId . '/join');
$bravoId = (int) $app->db()->fetchValue("SELECT id FROM {users} WHERE callsign = 'Bravo_2'");
$alpha->get('/servers/' . $serverId);
check(str_contains($alpha->body, 'demande en attente'), 'demande d\'adhésion visible du propriétaire');
$alpha->post('/servers/' . $serverId . '/members', ['user_id' => $bravoId, 'action' => 'approve']);
$bravo->get('/profile');
$bravo->post('/profile/server', ['server_id' => $serverId]);
check_equal($serverId, (int) $app->db()->fetchValue('SELECT preferred_server_id FROM {users} WHERE id = :id', ['id' => $bravoId]), 'membre accepté : serveur privé sélectionné');
$bravo->get('/servers/' . $serverId);
$bravo->post('/servers/' . $serverId . '/edit', ['name' => 'Piraté']);
check_equal(403, $bravo->status, 'un simple membre ne peut pas modifier le serveur');
$bravo->get('/profile');
$bravo->post('/profile/server', ['server_id' => $app->db()->fetchValue('SELECT id FROM {servers} WHERE is_default = 1')]);

// équipes
$alpha->get('/teams');
$alpha->post('/teams', ['name' => 'Patrouille']);
$teamId = (int) $app->db()->fetchValue("SELECT id FROM {teams} WHERE name = 'Patrouille'");
check($teamId > 0, 'création d\'une équipe');
$bravo->get('/teams/' . $teamId);
$bravo->post('/teams/' . $teamId . '/join');
$alpha->get('/teams/' . $teamId);
$alpha->post('/teams/' . $teamId . '/manage', ['user_id' => $bravoId, 'action' => 'accept']);
check_equal($teamId, (int) $app->db()->fetchValue('SELECT team_id FROM {users} WHERE id = :id', ['id' => $bravoId]), "le chef accepte l'équipier");
$bravo->get('/teams/' . $teamId);
$bravo->post('/teams/' . $teamId . '/manage', ['user_id' => $bravoId, 'action' => 'dissolve']);
check_equal(403, $bravo->status, "un équipier ne peut pas dissoudre l'équipe");

// mot de passe oublié
$forgot = new Browser($base);
$forgot->get('/forgot');
$forgot->post('/forgot', ['email' => 'alpha@example.org']);
check(preg_match('#/reset\?token=([0-9a-f]{64})#', last_mail($storage), $reset) === 1, 'mot de passe oublié : lien envoyé');
$forgot->get('/reset?token=' . $reset[1]);
$forgot->post('/reset', ['token' => $reset[1], 'password' => 'alpha-new-password', 'password_confirmation' => 'alpha-new-password']);
$forgot->follow();
$forgot->post('/login', ['login' => 'Alpha', 'password' => 'alpha-new-password']);
check_equal(303, $forgot->status, 'connexion avec le nouveau mot de passe');

// profils publics
check_equal(200, (new Browser($base))->get('/pilots/Alpha')->status, 'profil public');
check_equal(404, (new Browser($base))->get('/pilots/Inconnu')->status, 'profil inexistant : 404');
check_equal(404, (new Browser($base))->get('/pilots/Charlie')->status, 'profil d\'un compte suspendu masqué');

$pilots = ['Alpha' => 'alpha-new-password', 'Bravo_2' => 'bravo-password', 'Charlie' => 'charlie-password'];
