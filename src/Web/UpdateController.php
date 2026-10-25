<?php
declare(strict_types=1);

namespace Ffs2\Web;

use Ffs2\Core\Request;
use Ffs2\Domain\AssetMatcher;

/**
 * Mises à jour de ffs2play et xffs2play (contrat docs/contracts/update-api.md) et page de téléchargement
 */
final class UpdateController extends Controller
{
    /** GET /api/update : release et paquet adaptés au poste du joueur */
    public function check(): void
    {
        $query = [];
        $lists = [
            'app' => AssetMatcher::APPS, 'channel' => ['stable', 'beta'], 'os' => AssetMatcher::OSES,
            'arch' => AssetMatcher::ARCHES, 'format' => AssetMatcher::FORMATS,
        ];
        $defaults = ['channel' => 'stable', 'os' => '', 'arch' => 'x86_64', 'format' => ''];
        foreach ($lists as $name => $allowed) {
            $query[$name] = strtolower(Request::query($name, $defaults[$name] ?? ''));
            if (!in_array($query[$name], $allowed, true) && !($name === 'os' && $query['app'] === 'xffs2play' && $query[$name] === '')) {
                $this->invalid($name);
            }
        }
        foreach (['version' => true, 'client_version' => false] as $name => $required) {
            $query[$name] = Request::query($name);
            if (($required || $query[$name] !== '') && AssetMatcher::parseVersion($query[$name]) === null) {
                $this->invalid($name);
            }
        }
        foreach (['distro' => '/^[a-z0-9._-]{0,32}$/', 'distro_version' => '/^[0-9A-Za-z._-]{0,32}$/', 'distro_like' => '/^[a-z0-9._ -]{0,100}$/'] as $name => $pattern) {
            $query[$name] = strtolower(Request::query($name));
            if (preg_match($pattern, $query[$name]) !== 1) {
                $this->invalid($name);
            }
        }

        $releases = $this->app->releases()->releases($query['app']);
        if ($releases === null) {
            $this->json(['error' => 'catalog_unavailable'], 503);
        }
        $latest = AssetMatcher::latest($releases, $query['channel']);
        $minimum = $this->app->settings()->get('update_min_version_' . $query['app']);
        $response = [
            'app' => $query['app'],
            'channel' => $query['channel'],
            'update_available' => $latest !== null && AssetMatcher::compareVersions($latest['version'], $query['version']) > 0,
            'mandatory' => $minimum !== '' && AssetMatcher::compareVersions($query['version'], $minimum) < 0,
            'latest' => null,
            'asset' => null,
            'alternatives' => [],
            'reason' => null,
        ];
        if ($latest !== null) {
            $response['latest'] = [
                'version' => $latest['version'],
                'prerelease' => $latest['prerelease'],
                'published' => $latest['published'],
                'notes' => $latest['notes'],
                'page_url' => $latest['page_url'],
            ];
            if ($latest['requires']) {
                $response['latest']['requires'] = $latest['requires'];
            }
            $choice = AssetMatcher::choose($latest, $query);
            $required = $latest['requires']['ffs2play'] ?? '';
            if ($query['app'] === 'xffs2play' && $required !== '' && $query['client_version'] !== ''
                && AssetMatcher::compareVersions($query['client_version'], $required) < 0) {
                $choice = ['asset' => null, 'alternatives' => [], 'reason' => 'client_too_old'];
            }
            $response = array_merge($response, $choice);
        }
        $this->json($response);
    }

    /** GET /telechargements : dernières versions stable et beta de chaque logiciel */
    public function downloads(): void
    {
        $catalog = $this->app->releases();
        $apps = [];
        foreach (AssetMatcher::APPS as $app) {
            $releases = $catalog->releases($app) ?? [];
            $stable = AssetMatcher::latest($releases, 'stable');
            $beta = AssetMatcher::latest($releases, 'beta');
            $apps[$app] = [
                'stable' => $stable,
                // la beta n'est montrée que si elle est plus récente que la stable
                'beta' => $beta !== null && $beta['prerelease'] ? $beta : null,
            ];
        }
        $this->page('downloads', ['title' => 'Téléchargements', 'apps' => $apps]);
    }

    private function invalid(string $field): never
    {
        $this->json(['error' => 'invalid_parameter', 'field' => $field], 400);
    }
}
