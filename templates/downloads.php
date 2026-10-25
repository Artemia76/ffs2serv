<?php
/** @var array $apps */
use Ffs2\Domain\AssetMatcher;

$titles = ['ffs2play' => 'FFS2play (client)', 'xffs2play' => 'xffs2play (plugin X-Plane)'];
$labels = [
    'inno' => 'Windows (installeur)', 'dmg' => 'macOS', 'appimage' => 'Linux, toutes distributions (AppImage)',
    'deb' => 'Linux .deb', 'rpm' => 'Linux .rpm', 'arch' => 'Arch Linux', 'zip' => 'Windows, macOS et Linux',
];
$label = static function (array $info) use ($labels): string {
    $text = $labels[$info['format']];
    if ($info['distro'] !== null) {
        $text .= ' — ' . ucfirst(str_replace('_', ' ', $info['distro'])) . ($info['distro_version'] !== null ? ' ' . $info['distro_version'] : '');
    }
    if ($info['arch'] === 'arm64') {
        $text .= ' (ARM 64 bits)';
    }
    return $text;
};
$size = static fn (int $bytes) => number_format($bytes / 1048576, 1, ',', ' ') . ' Mo';
?>
<header class="page-header"><h1>Téléchargements</h1></header>

<?php foreach ($apps as $app => $channels): ?>
    <section class="card">
        <h2><?= e($titles[$app]) ?></h2>
        <?php if ($channels['stable'] === null && $channels['beta'] === null): ?>
            <p class="muted">Aucune version publiée pour le moment.</p>
        <?php endif; ?>
        <?php foreach (['stable' => 'Version stable', 'beta' => 'Version beta'] as $channel => $heading): ?>
            <?php $release = $channels[$channel]; if ($release === null) { continue; } ?>
            <h3><?= e($heading) ?> <?= e($release['version']) ?>
                <span class="muted small">du <?= e(format_datetime($release['published'], 'd/m/Y')) ?></span></h3>
            <?php if (isset($release['requires']['ffs2play'])): ?>
                <p class="small">Nécessite FFS2play <?= e($release['requires']['ffs2play']) ?> ou plus récent.</p>
            <?php endif; ?>
            <table class="table">
                <tbody>
                <?php foreach ($release['assets'] as $asset): ?>
                    <?php $info = AssetMatcher::describe($asset['name']); if ($info === null || $info['app'] !== $app) { continue; } ?>
                    <tr>
                        <td><?= e($label($info)) ?></td>
                        <td><a href="<?= e($asset['url']) ?>"><?= e($asset['name']) ?></a></td>
                        <td class="muted small"><?= e($size($asset['size'])) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="small"><a href="<?= e($release['page_url']) ?>">Notes de version et autres fichiers</a></p>
        <?php endforeach; ?>
    </section>
<?php endforeach; ?>

<section class="card">
    <h2>Installation</h2>
    <ul>
        <li>Une fois installé, FFS2play se met à jour lui-même et met aussi à jour le plugin xffs2play dans X-Plane.
            La case « Beta » des réglages P2P propose aussi les versions beta.</li>
        <li>Linux : prenez le paquet de votre distribution s'il existe (Qt 6.8 minimum), sinon l'AppImage.</li>
        <li>xffs2play : décompressez le dossier <code>xffs2play</code> dans <code>X-Plane/Resources/plugins</code>.</li>
    </ul>
</section>
