<?php
/*
 * Détection des vols (FlightTracker) : décollage → croisière → atterrissage
 */
use Ffs2\Domain\FlightTracker;

echo "vols\n";

$db = $app->db();
$tracker = new FlightTracker($db);
$userId = (int) $db->fetchValue("SELECT id FROM {users} WHERE callsign = 'Bravo_2'");
$serverId = (int) $db->fetchValue('SELECT id FROM {servers} WHERE is_default = 1');
$db->execute('DELETE FROM {flights} WHERE user_id = :u', ['u' => $userId]);
$db->execute('DELETE FROM {positions} WHERE user_id = :u', ['u' => $userId]);

$point = fn (float $lat, float $lon, int $alt, string $phase, int $ground) => [
    'latitude' => $lat, 'longitude' => $lon, 'heading' => 0, 'altitude' => $alt, 'ground_speed' => 120, 'ias' => 110,
    'squawk' => 7000, 'on_ground' => $ground, 'phase' => $phase, 'aircraft' => 'Cessna 172', 'category' => 'Airplane',
];

$tracker->update($userId, $serverId, 'XPLANE', $point(45.0, 5.0, 800, 'Taxiway', 1));
$tracker->update($userId, $serverId, 'XPLANE', $point(45.01, 5.0, 900, 'Takeoff', 0));
$flight = $db->fetch("SELECT * FROM {flights} WHERE user_id = :u AND status = 'in_progress'", ['u' => $userId]);
check($flight !== null && (float) $flight['dep_latitude'] === 45.0, 'décollage : vol ouvert, départ = dernière position au sol');

for ($i = 1; $i <= 5; $i++) {
    $tracker->update($userId, $serverId, 'XPLANE', $point(45.01 + $i * 0.05, 5.0, 3000 + $i * 500, 'Cruise', 0));
}
$tracker->update($userId, $serverId, 'XPLANE', $point(45.30, 5.0, 900, 'Landing', 1));
$flight = $db->fetch('SELECT * FROM {flights} WHERE id = :id', ['id' => $flight['id']]);
check_equal('completed', $flight['status'], 'atterrissage : vol terminé');
check(abs((float) $flight['distance_nm'] - 18.0) < 1.0, 'distance parcourue ≈ 18 NM (' . round((float) $flight['distance_nm'], 1) . ')');
check_equal(5500, (int) $flight['max_altitude'], 'altitude maximale');
check_equal(45.3, (float) $flight['arr_latitude'], "position d'arrivée");
check((int) $db->fetchValue('SELECT COUNT(*) FROM {track_points} WHERE flight_id = :f', ['f' => $flight['id']]) >= 5, 'trace du vol enregistrée');

// rebond au chargement du simulateur : pas de vol
$tracker->update($userId, $serverId, 'XPLANE', $point(46.0, 6.0, 500, 'Takeoff', 0));
$tracker->update($userId, $serverId, 'XPLANE', $point(46.0, 6.0, 500, 'IDLE', 1));
check_equal(1, (int) $db->fetchValue('SELECT COUNT(*) FROM {flights} WHERE user_id = :u', ['u' => $userId]), 'saut sans déplacement ignoré');

// vol sans nouvelles : interrompu par la purge
$tracker->update($userId, $serverId, 'XPLANE', $point(46.0, 6.0, 2000, 'Climb', 0));
$db->execute("UPDATE {flights} SET updated_at = :old WHERE user_id = :u AND status = 'in_progress'", ['old' => now_utc(-3600), 'u' => $userId]);
$tracker->purge();
check_equal(1, (int) $db->fetchValue("SELECT COUNT(*) FROM {flights} WHERE user_id = :u AND status = 'aborted'", ['u' => $userId]), 'vol abandonné marqué interrompu');

// pages
$bravo->get('/profile');
check(str_contains($bravo->body, 'Cessna 172') && str_contains($bravo->body, 'terminé'), 'profil : historique des vols');
check_equal(200, $bravo->get('/flights/' . $flight['id'])->status, "détail d'un vol avec sa trace");
check(str_contains($bravo->body, 'data-track="[[45'), 'détail du vol : points de trace transmis à la carte');
check(str_contains((new Browser($base))->get('/')->body, 'Cessna 172'), 'accueil : derniers vols');
