<?php
/** @var array $apps */ /** @var \Ffs2\Core\Settings $settings */
use Ffs2\Domain\AssetMatcher;
?>
<header class="page-header"><h1>Releases</h1></header>
<?= $view->partial('admin/nav') ?>

<section class="card">
    <p>Les paquets viennent des GitHub Releases ; le portail relit GitHub toutes les 10 minutes.
        Une release en draft n'est pas visible ; une prerelease ne va qu'aux clients du canal beta.</p>
    <form method="post" action="<?= e(url('/admin/releases/refresh')) ?>" class="inline">
        <?= csrf_field() ?><button class="button ghost" type="submit">Relire GitHub maintenant</button>
    </form>
</section>

<?php foreach ($apps as $app => $data): ?>
    <section class="card">
        <h2><?= e($app) ?></h2>
        <p class="small">
            Dernière lecture de GitHub : <?= $data['fetched_at'] ? e(format_datetime(gmdate('Y-m-d H:i:s', $data['fetched_at']), 'd/m/Y H:i')) : 'jamais' ?>
            · stable : <strong><?= e($data['stable']['version'] ?? '—') ?></strong>
            · beta : <strong><?= e($data['beta']['version'] ?? '—') ?></strong>
            · mise à jour obligatoire en dessous de : <strong><?= e($settings->get('update_min_version_' . $app) ?: '—') ?></strong>
            (<a href="<?= e(url('/admin/settings')) ?>">réglages</a>)
        </p>
        <?php if ($data['releases'] === null): ?>
            <p class="alert alert-warning">GitHub n'a pas répondu et aucun cache n'existe : les clients reçoivent une erreur 503.</p>
        <?php elseif (!$data['releases']): ?>
            <p class="muted">Aucune release publiée.</p>
        <?php else: ?>
            <table class="table">
                <thead><tr><th>Version</th><th>Publiée le</th><th>Fichiers</th></tr></thead>
                <tbody>
                <?php foreach (array_slice($data['releases'], 0, 10) as $release): ?>
                    <tr>
                        <td><a href="<?= e($release['page_url']) ?>"><?= e($release['version']) ?></a><?= $release['prerelease'] ? ' <span class="muted small">beta</span>' : '' ?>
                            <?php if (isset($release['requires']['ffs2play'])): ?><br><span class="muted small">ffs2play ≥ <?= e($release['requires']['ffs2play']) ?></span><?php endif; ?></td>
                        <td><?= e(format_datetime($release['published'], 'd/m/Y')) ?></td>
                        <td class="small">
                            <?php foreach ($release['assets'] as $asset): ?>
                                <?php $info = AssetMatcher::describe($asset['name']); ?>
                                <div><?= e($asset['name']) ?>
                                    <?php if ($info === null): ?><span class="muted">— nom non reconnu, ignoré</span>
                                    <?php elseif ($asset['sha256'] === null): ?><span class="muted">— sans SHA-256 : pas d'installation automatique</span><?php endif; ?></div>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
<?php endforeach; ?>
