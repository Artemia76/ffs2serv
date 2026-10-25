<?php /** @var array $servers */ ?>
<header class="page-header"><h1>Serveurs</h1></header>
<?= $view->partial('admin/nav') ?>
<section class="card">
    <p class="muted">La gestion détaillée (membres, réglages, suppression) se fait depuis la page de chaque serveur, où les administrateurs ont tous les droits.</p>
    <table class="table">
        <thead><tr><th>Serveur</th><th>Accès</th><th>Propriétaire</th><th>Membres</th><th>En ligne</th><th>Créé le</th></tr></thead>
        <tbody>
        <?php foreach ($servers as $s): ?>
            <tr>
                <td><a href="<?= e(url('/servers/' . $s['id'])) ?>"><?= e($s['name']) ?></a><?= (int) $s['is_default'] ? ' <span class="badge">par défaut</span>' : '' ?></td>
                <td><?= (int) $s['is_private'] ? 'privé' : 'public' ?></td>
                <td><?= e($s['owner_callsign'] ?? '—') ?></td>
                <td><?= (int) $s['members'] ?></td>
                <td><?= (int) $s['online'] ?></td>
                <td><?= e(format_datetime($s['created_at'], 'd/m/Y')) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
