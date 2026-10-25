<?php
/*
 * Mises à jour (contrat docs/contracts/update-api.md) : choix des releases et des paquets, API /api/update.
 * Les releases GitHub viennent de tests/fixtures (aucun accès réseau).
 */
use Ffs2\Domain\AssetMatcher;
use Ffs2\Domain\ReleaseCatalog;

echo "mises à jour\n";

$fixture = static fn (string $app) => ReleaseCatalog::normalize(json_decode(file_get_contents(__DIR__ . "/fixtures/github-releases-$app.json"), true));
$ffs2play = $fixture('ffs2play');
$xffs2play = $fixture('xffs2play');

// versions
check(AssetMatcher::compareVersions('2.10.0', '2.9.9') > 0, 'versions : comparaison numérique');
check(AssetMatcher::compareVersions('3.0.0', '2.9.0') > 0, 'versions : numéro majeur');
check(AssetMatcher::compareVersions('2.2.0', '2.2.0-beta1') > 0, 'versions : la version finale passe avant la beta');
check(AssetMatcher::compareVersions('2.2.0-beta10', '2.2.0-beta9') > 0, 'versions : suffixes comparés naturellement');
check_equal(0, AssetMatcher::compareVersions('v2.1.0', '2.1.0'), 'versions : préfixe v ignoré');
check(AssetMatcher::parseVersion('2.1') === null && AssetMatcher::parseVersion('latest') === null, 'versions : tags invalides refusés');

// catalogue
check_equal(['2.5.1', '2.5.0', '2.4.3'], array_column($ffs2play, 'version'), 'catalogue : drafts exclus');
check_equal(['2.2.0-beta1', '2.1.0'], array_column($xffs2play, 'version'), 'catalogue : préfixe v retiré');
check(preg_match('/^[0-9a-f]{64}$/', (string) $ffs2play[1]['assets'][0]['sha256']) === 1, 'catalogue : SHA-256 tiré du digest GitHub');
check_equal(null, $ffs2play[0]['assets'][0]['sha256'], 'catalogue : asset sans digest');
check_equal(['ffs2play' => '2.4.3'], $xffs2play[1]['requires'], 'catalogue : ligne Requires (fins de ligne Windows)');
check_equal('2.5.0', AssetMatcher::latest($ffs2play, 'stable')['version'], 'canal stable : plus haute version hors prerelease');
check_equal('2.5.1', AssetMatcher::latest($ffs2play, 'beta')['version'], 'canal beta : prereleases comprises');
check_equal(null, AssetMatcher::latest([], 'stable'), 'canal vide');

// nommage des assets
$deb = AssetMatcher::describe('ffs2play_2.5.0-1+debian13_amd64.deb');
check_equal(['deb', 'debian', '13', 'x86_64'], [$deb['format'], $deb['distro'], $deb['distro_version'], $deb['arch']], 'nommage : .deb Debian');
check_equal(['rpm', 'opensuse_tumbleweed', '20261020'], [
    AssetMatcher::describe('ffs2play-2.5.0-1.opensuse_tumbleweed.20261020.x86_64.rpm')['format'],
    AssetMatcher::describe('ffs2play-2.5.0-1.opensuse_tumbleweed.20261020.x86_64.rpm')['distro'],
    AssetMatcher::describe('ffs2play-2.5.0-1.opensuse_tumbleweed.20261020.x86_64.rpm')['distro_version'],
], 'nommage : .rpm openSUSE');
check_equal('fedora', AssetMatcher::describe('ffs2play-2.5.0-1.fc43.x86_64.rpm')['distro'], 'nommage : .rpm Fedora');
check_equal('arm64', AssetMatcher::describe('ffs2play_2_5_0_Darwin_arm64.dmg')['arch'], 'nommage : architecture facultative');
check_equal(null, AssetMatcher::describe('notes.txt'), 'nommage : fichier inconnu ignoré');

$stable = AssetMatcher::latest($ffs2play, 'stable');
$query = static fn (array $values) => $values + ['app' => 'ffs2play', 'os' => 'linux', 'arch' => 'x86_64', 'format' => '', 'distro' => '', 'distro_version' => '', 'distro_like' => ''];
$pick = static fn (array $values) => AssetMatcher::choose($stable, $query($values));
check_equal('ffs2play_2_5_0_Windows.exe', $pick(['os' => 'windows', 'format' => 'inno'])['asset']['name'], 'paquet : Windows');
check_equal('ffs2play_2_5_0_Darwin.dmg', $pick(['os' => 'macos', 'arch' => 'arm64'])['asset']['name'], 'paquet : macOS sans architecture');
check_equal('ffs2play-2.5.0-x86_64.AppImage', $pick(['format' => 'appimage', 'distro' => 'debian', 'distro_version' => '13'])['asset']['name'], 'paquet : AppImage gardée');
check_equal('ffs2play_2.5.0-1+debian13_amd64.deb', $pick(['format' => 'deb', 'distro' => 'debian', 'distro_version' => '13'])['asset']['name'], 'paquet : .deb de la distribution');
check_equal('ffs2play_2.5.0-1+debian13_amd64.deb', $pick(['format' => 'deb', 'distro' => 'debian', 'distro_version' => '14'])['asset']['name'], 'paquet : version de distribution plus récente');
check_equal('ffs2play_2.5.0-1+ubuntu25.04_amd64.deb', $pick(['format' => 'deb', 'distro' => 'ubuntu', 'distro_version' => '25.04'])['asset']['name'], 'paquet : Ubuntu 25.04 exact');
check_equal('ffs2play_2.5.0-1+ubuntu25.10_amd64.deb', $pick(['format' => 'deb', 'distro' => 'ubuntu', 'distro_version' => '26.04'])['asset']['name'], 'paquet : Ubuntu plus récente → plus haute ≤');
check_equal('ffs2play-2.5.0-1.el10.x86_64.rpm', $pick(['format' => 'rpm', 'distro' => 'rocky', 'distro_version' => '10.0', 'distro_like' => 'rhel centos fedora'])['asset']['name'], 'paquet : famille RHEL');
check_equal('ffs2play-2.5.0-1.opensuse_tumbleweed.20261020.x86_64.rpm', $pick(['format' => 'rpm', 'distro' => 'opensuse-tumbleweed', 'distro_version' => '20261101'])['asset']['name'], 'paquet : openSUSE Tumbleweed');
check_equal('ffs2play-2.5.0-1-x86_64.pkg.tar.zst', $pick(['format' => 'arch', 'distro' => 'manjaro'])['asset']['name'], 'paquet : Arch et dérivées');
$mint = $pick(['format' => 'deb', 'distro' => 'linuxmint', 'distro_version' => '22', 'distro_like' => 'ubuntu debian']);
check($mint['asset'] === null && $mint['reason'] === 'no_matching_package', 'paquet : dérivée sans paquet → aucun');
check_equal('ffs2play-2.5.0-x86_64.AppImage', $mint['alternatives'][0]['name'] ?? null, 'paquet : AppImage proposée en alternative');
check_equal(null, $pick(['format' => 'deb', 'distro' => 'debian', 'distro_version' => '12'])['asset'], 'paquet : distribution trop ancienne');
check_equal(null, $pick(['format' => '', 'distro' => 'debian'])['asset'], 'paquet : build de développement');
check_equal('xffs2play_2_2_0.zip', AssetMatcher::choose($xffs2play[0], ['app' => 'xffs2play'] + $query([]))['asset']['name'], 'paquet : zip versionné du plugin préféré');

// catalogue en cache, GitHub injoignable
$cacheDir = $storage . '/cache';
$offline = new ReleaseCatalog($cacheDir, '', 'http://127.0.0.1:9');
check_equal(null, $offline->releases('ffs2play'), 'cache : GitHub injoignable sans cache');
@mkdir($cacheDir, 0750, true);
foreach (['ffs2play' => $ffs2play, 'xffs2play' => $xffs2play] as $name => $releases) {
    file_put_contents("$cacheDir/releases-$name.json", json_encode(['checked_at' => time() - 3600, 'fetched_at' => time() - 3600, 'releases' => $releases]));
}
check_equal('2.5.1', $offline->releases('ffs2play')[0]['version'] ?? null, 'cache : périmé mais servi si GitHub ne répond pas');
check(!$offline->refresh('ffs2play'), 'cache : rafraîchissement en échec signalé');
check(json_decode(file_get_contents("$cacheDir/releases-ffs2play.json"), true)['checked_at'] >= time() - 5, 'cache : nouvel essai reporté');

// API, sur un cache à jour : le serveur de test ne doit pas appeler GitHub
foreach (['ffs2play' => $ffs2play, 'xffs2play' => $xffs2play] as $name => $releases) {
    file_put_contents("$cacheDir/releases-$name.json", json_encode(['checked_at' => time(), 'fetched_at' => time(), 'releases' => $releases]));
}
$api = static function (string $base, array $params): array {
    $browser = (new Browser($base))->get('/api/update?' . http_build_query($params));
    return [$browser->status, json_decode($browser->body, true)];
};
$linux = ['app' => 'ffs2play', 'version' => '2.4.3', 'os' => 'linux', 'arch' => 'x86_64', 'format' => 'deb', 'distro' => 'debian', 'distro_version' => '13'];
[$status, $body] = $api($base, $linux);
check_equal(200, $status, 'api : réponse');
check($body['update_available'] === true && $body['latest']['version'] === '2.5.0' && $body['mandatory'] === false, 'api : 2.5.0 proposée en stable');
check_equal('ffs2play_2.5.0-1+debian13_amd64.deb', $body['asset']['name'] ?? null, 'api : paquet de la distribution');
check(isset($body['asset']['sha256'], $body['asset']['size'], $body['asset']['url']) && $body['reason'] === null, 'api : description du paquet');
[, $body] = $api($base, ['version' => '2.5.0'] + $linux);
check($body['update_available'] === false, 'api : déjà à jour');
[, $body] = $api($base, ['channel' => 'beta', 'os' => 'windows', 'format' => 'inno'] + $linux);
check($body['latest']['version'] === '2.5.1' && $body['latest']['prerelease'] === true, 'api : canal beta');
check(!isset($body['asset']['sha256']), 'api : empreinte absente non inventée');
[, $body] = $api($base, ['app' => 'xffs2play', 'version' => '2.0.5', 'client_version' => '2.4.3', 'os' => '']);
check_equal('xffs2play.zip', $body['asset']['name'] ?? null, 'api : plugin');
check_equal(['ffs2play' => '2.4.3'], $body['latest']['requires'] ?? null, 'api : exigence du plugin');
[, $body] = $api($base, ['app' => 'xffs2play', 'version' => '2.1.0', 'client_version' => '2.4.3', 'channel' => 'beta']);
check($body['asset'] === null && $body['reason'] === 'client_too_old', 'api : plugin beta refusé à un client trop ancien');
foreach (['app' => 'ffs3', 'version' => '2.4', 'os' => 'beos', 'format' => 'exe', 'distro' => 'Debian<script>'] as $field => $value) {
    [$status, $body] = $api($base, [$field => $value] + $linux);
    check($status === 400 && ($body['field'] ?? '') === $field, "api : paramètre $field invalide refusé");
}
[$status] = $api($base, ['app' => 'ffs2play', 'version' => '2.4.3']);
check_equal(400, $status, 'api : os obligatoire pour ffs2play');

$app->settings()->save(['update_min_version_ffs2play' => '2.5.0']);
[, $body] = $api($base, $linux);
check($body['mandatory'] === true, 'api : mise à jour obligatoire sous la version minimale');
$app->settings()->save(['update_min_version_ffs2play' => '']);

// pages
$page = (new Browser($base))->get('/telechargements');
check($page->status === 200 && str_contains($page->body, 'ffs2play_2.5.0-1+debian13_amd64.deb') && str_contains($page->body, 'Version beta'), 'page de téléchargement');
check(!str_contains($page->body, '2.6.0') && !str_contains($page->body, 'notes.txt'), 'page de téléchargement : ni draft ni fichier inconnu');
$page = $admin->get('/admin/releases');
check($page->status === 200 && str_contains($page->body, 'notes.txt') && str_contains($page->body, 'nom non reconnu'), 'admin : releases et fichiers ignorés');
check(str_contains($page->body, 'sans SHA-256'), 'admin : paquet sans empreinte signalé');
