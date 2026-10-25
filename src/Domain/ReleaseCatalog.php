<?php
declare(strict_types=1);

namespace Ffs2\Domain;

/**
 * Releases GitHub de ffs2play et xffs2play, gardées en cache dans storage/cache (contrat update-api.md).
 * GitHub n'est interrogé qu'une fois toutes les 10 minutes par dépôt ; s'il ne répond pas, le cache périmé sert.
 */
final class ReleaseCatalog
{
    public const REPOSITORIES = ['ffs2play' => 'Artemia76/ffs2play', 'xffs2play' => 'Artemia76/xffs2play'];
    public const TTL = 600;
    private const NOTES_MAX = 20000;

    public function __construct(private string $cacheDir, private string $token = '', private string $apiBase = 'https://api.github.com')
    {
    }

    /** Releases publiées (drafts exclus) ; null si GitHub ne répond pas et qu'il n'y a aucun cache */
    public function releases(string $app): ?array
    {
        $cache = $this->readCache($app);
        if ($cache === null || $cache['checked_at'] + self::TTL < time()) {
            $cache = $this->fetch($app, $cache) ?? $cache;
        }
        return $cache['releases'] ?? null;
    }

    /** Date de la dernière lecture réussie de GitHub (page admin) */
    public function fetchedAt(string $app): ?int
    {
        return $this->readCache($app)['fetched_at'] ?? null;
    }

    /** Relit GitHub sans attendre la fin du délai ; false si GitHub n'a pas répondu */
    public function refresh(string $app): bool
    {
        $cache = $this->readCache($app);
        if ($cache !== null) {
            unset($cache['etag']);
        }
        $fresh = $this->fetch($app, $cache);
        return $fresh !== null && $fresh['fetched_at'] === $fresh['checked_at'];
    }

    /** Releases normalisées à partir de la réponse de l'API GitHub */
    public static function normalize(array $github): array
    {
        $releases = [];
        foreach ($github as $release) {
            $version = ltrim((string) ($release['tag_name'] ?? ''), 'v');
            if (!empty($release['draft']) || AssetMatcher::parseVersion($version) === null) {
                continue;
            }
            $assets = [];
            foreach ($release['assets'] ?? [] as $asset) {
                $digest = (string) ($asset['digest'] ?? '');
                $assets[] = [
                    'name' => (string) $asset['name'],
                    'url' => (string) $asset['browser_download_url'],
                    'size' => (int) $asset['size'],
                    'sha256' => preg_match('/^sha256:([0-9a-f]{64})$/', $digest, $m) ? $m[1] : null,
                ];
            }
            $notes = (string) ($release['body'] ?? '');
            $releases[] = [
                'version' => $version,
                'prerelease' => !empty($release['prerelease']),
                'published' => (string) ($release['published_at'] ?? ''),
                'notes' => mb_substr($notes, 0, self::NOTES_MAX),
                'page_url' => (string) ($release['html_url'] ?? ''),
                'requires' => AssetMatcher::requires($notes),
                'assets' => $assets,
            ];
        }
        return $releases;
    }

    private function fetch(string $app, ?array $cache): ?array
    {
        $headers = ['User-Agent: ffs2serv', 'Accept: application/vnd.github+json', 'X-GitHub-Api-Version: 2022-11-28'];
        if ($this->token !== '') {
            $headers[] = 'Authorization: Bearer ' . $this->token;
        }
        if (isset($cache['etag'])) {
            $headers[] = 'If-None-Match: ' . $cache['etag'];
        }
        $context = stream_context_create(['http' => [
            'method' => 'GET', 'header' => implode("\r\n", $headers), 'timeout' => 5, 'ignore_errors' => true,
        ]]);
        $url = $this->apiBase . '/repos/' . self::REPOSITORIES[$app] . '/releases?per_page=30';
        $body = @file_get_contents($url, false, $context);
        $responseHeaders = function_exists('http_get_last_response_headers') ? (http_get_last_response_headers() ?? []) : ($http_response_header ?? []);
        $status = preg_match('#^HTTP/\S+\s+(\d{3})#', (string) ($responseHeaders[0] ?? ''), $m) ? (int) $m[1] : 0;

        $now = time();
        if ($status === 304 && $cache !== null) {
            $cache['checked_at'] = $cache['fetched_at'] = $now;
        } elseif ($status === 200 && is_array($github = json_decode((string) $body, true))) {
            $cache = ['checked_at' => $now, 'fetched_at' => $now, 'releases' => self::normalize($github)];
            foreach ($responseHeaders as $header) {
                if (preg_match('/^ETag:\s*(.+)$/i', $header, $m)) {
                    $cache['etag'] = trim($m[1]);
                }
            }
        } else {
            error_log("FFS2play : releases GitHub de $app illisibles (HTTP $status)");
            if ($cache === null) {
                return null;
            }
            // nouvel essai seulement après le délai
            $cache['checked_at'] = $now;
        }
        $this->writeCache($app, $cache);
        return $cache;
    }

    private function cacheFile(string $app): string
    {
        return $this->cacheDir . '/releases-' . $app . '.json';
    }

    private function readCache(string $app): ?array
    {
        $data = json_decode((string) @file_get_contents($this->cacheFile($app)), true);
        return is_array($data) && isset($data['releases'], $data['checked_at']) ? $data : null;
    }

    private function writeCache(string $app, array $cache): void
    {
        if (!is_dir($this->cacheDir) && !@mkdir($this->cacheDir, 0750, true) && !is_dir($this->cacheDir)) {
            error_log('FFS2play : dossier de cache impossible à créer : ' . $this->cacheDir);
            return;
        }
        $tmp = $this->cacheFile($app) . '.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmp, json_encode($cache, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) !== false) {
            rename($tmp, $this->cacheFile($app));
        }
    }
}
