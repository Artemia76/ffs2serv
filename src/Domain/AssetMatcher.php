<?php
declare(strict_types=1);

namespace Ffs2\Domain;

/**
 * Choix de la release et du paquet adaptés au poste d'un joueur (contrat docs/contracts/update-api.md).
 * Fonctions pures sur les releases normalisées par ReleaseCatalog.
 */
final class AssetMatcher
{
    public const APPS = ['ffs2play', 'xffs2play'];
    public const OSES = ['windows', 'macos', 'linux'];
    public const ARCHES = ['x86_64', 'arm64'];
    public const FORMATS = ['', 'inno', 'dmg', 'appimage', 'deb', 'rpm', 'arch', 'tgz'];

    private const ARCH_ALIASES = ['amd64' => 'x86_64', 'x86_64' => 'x86_64', 'aarch64' => 'arm64', 'arm64' => 'arm64'];

    /**
     * Version d'un tag : "v2.1.0", "2.4.3", "2.2.0-beta1" → [2, 1, 0, ''] ; null si le tag n'est pas une version
     * @return ?array{0:int,1:int,2:int,3:string}
     */
    public static function parseVersion(string $tag): ?array
    {
        if (preg_match('/^v?(\d+)\.(\d+)\.(\d+)(?:-([0-9A-Za-z.-]+))?$/', $tag, $m) !== 1) {
            return null;
        }
        return [(int) $m[1], (int) $m[2], (int) $m[3], $m[4] ?? ''];
    }

    /** < 0, 0, > 0 ; à numéros égaux, une version sans suffixe passe avant une version suffixée */
    public static function compareVersions(string $a, string $b): int
    {
        $va = self::parseVersion($a) ?? [0, 0, 0, ''];
        $vb = self::parseVersion($b) ?? [0, 0, 0, ''];
        for ($i = 0; $i < 3; $i++) {
            if ($va[$i] !== $vb[$i]) {
                return $va[$i] <=> $vb[$i];
            }
        }
        if ($va[3] === $vb[3]) {
            return 0;
        }
        if ($va[3] === '' || $vb[3] === '') {
            return $va[3] === '' ? 1 : -1;
        }
        return strnatcmp($va[3], $vb[3]);
    }

    /** Plus haute release du canal (stable : hors prereleases), drafts déjà exclus par le catalogue */
    public static function latest(array $releases, string $channel): ?array
    {
        $best = null;
        foreach ($releases as $release) {
            if ($release['prerelease'] && $channel !== 'beta') {
                continue;
            }
            if ($best === null || self::compareVersions($release['version'], $best['version']) > 0) {
                $best = $release;
            }
        }
        return $best;
    }

    /** Exigences tirées des notes de release : lignes "Requires: ffs2play >= 2.4.3" */
    public static function requires(string $notes): array
    {
        $requires = [];
        if (preg_match_all('/^\s*Requires:\s*(ffs2play|xffs2play)\s*>=\s*v?(\d+\.\d+\.\d+)\s*$/mi', $notes, $m, PREG_SET_ORDER)) {
            foreach ($m as $line) {
                $requires[strtolower($line[1])] = $line[2];
            }
        }
        return $requires;
    }

    /**
     * Décrit un asset d'après son nom (tableau « Nommage des assets » du contrat) ; null si le nom est inconnu.
     * @return ?array{app:string,format:string,os:?string,arch:?string,distro:?string,distro_version:?string,versioned:bool}
     */
    public static function describe(string $name): ?array
    {
        $asset = static fn (string $app, string $format, ?string $os, ?string $arch, ?string $distro = null, ?string $distroVersion = null, bool $versioned = true) => [
            'app' => $app, 'format' => $format, 'os' => $os, 'arch' => $arch === null ? null : self::ARCH_ALIASES[$arch],
            'distro' => $distro, 'distro_version' => $distroVersion, 'versioned' => $versioned,
        ];
        if (preg_match('/^ffs2play_\d+_\d+_\d+_Windows(?:_(x86_64|arm64))?\.exe$/', $name, $m)) {
            return $asset('ffs2play', 'inno', 'windows', ($m[1] ?? '') ?: null);
        }
        if (preg_match('/^ffs2play_\d+_\d+_\d+_Darwin(?:_(x86_64|arm64))?\.dmg$/', $name, $m)) {
            return $asset('ffs2play', 'dmg', 'macos', ($m[1] ?? '') ?: null);
        }
        if (preg_match('/^ffs2play-\d+\.\d+\.\d+-(x86_64|aarch64)\.AppImage$/', $name, $m)) {
            return $asset('ffs2play', 'appimage', 'linux', $m[1]);
        }
        if (preg_match('/^ffs2play_\d+\.\d+\.\d+-\d+\+([a-z][a-z_]*?)(\d[\d.]*)?_(amd64|arm64)\.deb$/', $name, $m)) {
            return $asset('ffs2play', 'deb', 'linux', $m[3], $m[1], $m[2] !== '' ? $m[2] : null);
        }
        if (preg_match('/^ffs2play-\d+\.\d+\.\d+-\d+\.(fc|el)(\d+)\.(x86_64|aarch64)\.rpm$/', $name, $m)) {
            return $asset('ffs2play', 'rpm', 'linux', $m[3], $m[1] === 'fc' ? 'fedora' : 'rhel', $m[2]);
        }
        if (preg_match('/^ffs2play-\d+\.\d+\.\d+-\d+\.([a-z][a-z_]*)(?:\.(\d[\d.]*))?\.(x86_64|aarch64)\.rpm$/', $name, $m)) {
            return $asset('ffs2play', 'rpm', 'linux', $m[3], $m[1], $m[2] !== '' ? $m[2] : null);
        }
        if (preg_match('/^ffs2play-\d+\.\d+\.\d+-\d+-(x86_64|aarch64)\.pkg\.tar\.(?:zst|xz)$/', $name, $m)) {
            return $asset('ffs2play', 'arch', 'linux', $m[1]);
        }
        if (preg_match('/^xffs2play(_\d+_\d+_\d+)?\.zip$/', $name, $m)) {
            return $asset('xffs2play', 'zip', null, null, null, null, ($m[1] ?? '') !== '');
        }
        return null;
    }

    /**
     * Paquet de la release pour le poste décrit par $query (app, os, arch, format, distro, distro_version, distro_like)
     * @return array{asset:?array, alternatives:list<array>, reason:?string}
     */
    public static function choose(array $release, array $query): array
    {
        $candidates = [];
        foreach ($release['assets'] as $asset) {
            $info = self::describe($asset['name']);
            if ($info !== null && $info['app'] === $query['app']) {
                $candidates[] = ['asset' => $asset, 'info' => $info];
            }
        }
        $none = ['asset' => null, 'alternatives' => [], 'reason' => 'no_matching_package'];

        if ($query['app'] === 'xffs2play') {
            // nom versionné préféré à xffs2play.zip
            usort($candidates, static fn ($a, $b) => $b['info']['versioned'] <=> $a['info']['versioned']);
            return $candidates ? self::result($candidates[0]) : $none;
        }

        $arch = $query['arch'];
        $sameArch = static fn (array $c) => $c['info']['arch'] === null || $c['info']['arch'] === $arch;
        $pick = static function (string $format) use ($candidates, $sameArch): array {
            // l'architecture exacte passe avant un paquet sans architecture
            $found = array_values(array_filter($candidates, static fn ($c) => $c['info']['format'] === $format && $sameArch($c)));
            usort($found, static fn ($a, $b) => ($b['info']['arch'] !== null) <=> ($a['info']['arch'] !== null));
            return $found;
        };

        if ($query['os'] === 'windows' || $query['os'] === 'macos') {
            $found = $pick($query['os'] === 'windows' ? 'inno' : 'dmg');
            return $found ? self::result($found[0]) : $none;
        }

        $appImages = $pick('appimage');
        $alternatives = $appImages ? [self::publicAsset($appImages[0])] : [];
        $native = null;
        switch ($query['format']) {
            case 'appimage':
                return $appImages ? self::result($appImages[0]) : $none;
            case 'deb':
            case 'rpm':
                $native = self::nativeForDistro($pick($query['format']), $query);
                break;
            case 'arch':
                $native = $pick('arch')[0] ?? null;
                break;
        }
        if ($native === null) {
            return ['asset' => null, 'alternatives' => $alternatives, 'reason' => 'no_matching_package'];
        }
        return ['asset' => self::publicAsset($native), 'alternatives' => $alternatives, 'reason' => null];
    }

    /** Même distribution et même version, sinon la plus haute version ≤ celle du poste */
    private static function nativeForDistro(array $candidates, array $query): ?array
    {
        $distro = self::normalizeDistro($query['distro']);
        $version = $query['distro_version'];
        $like = array_map(self::normalizeDistro(...), preg_split('/\s+/', $query['distro_like'], -1, PREG_SPLIT_NO_EMPTY));
        $best = null;
        foreach ($candidates as $candidate) {
            $target = $candidate['info']['distro'];
            // les paquets el<N> conviennent à toute la famille RHEL (Rocky, Alma…)
            $sameDistro = $target === $distro || ($target === 'rhel' && in_array('rhel', $like, true));
            if (!$sameDistro) {
                continue;
            }
            $targetVersion = (string) $candidate['info']['distro_version'];
            if ($version !== '' && $targetVersion !== '' && version_compare($targetVersion, $version, '>')) {
                continue;
            }
            if ($best === null || version_compare($targetVersion, (string) $best['info']['distro_version'], '>')) {
                $best = $candidate;
            }
        }
        return $best;
    }

    private static function normalizeDistro(string $id): string
    {
        return str_replace('-', '_', strtolower($id));
    }

    private static function result(array $candidate): array
    {
        return ['asset' => self::publicAsset($candidate), 'alternatives' => [], 'reason' => null];
    }

    private static function publicAsset(array $candidate): array
    {
        $asset = $candidate['asset'];
        $public = ['name' => $asset['name'], 'url' => $asset['url'], 'size' => $asset['size']];
        if ($asset['sha256'] !== null) {
            $public['sha256'] = $asset['sha256'];
        }
        $public['format'] = $candidate['info']['format'];
        return $public;
    }
}
