<?php
/** @var array $stats */ /** @var array $online */ /** @var int $pending */ /** @var array $latestUsers */
/** @var array $migrations */ /** @var ?string $certificateExpires */
?>
<header class="page-header"><h1>Administration</h1></header>
<?= $view->partial('admin/nav') ?>

<?php if ($migrations): ?>
    <div class="alert alert-warning">La base de données doit être mise à jour (<?= e(implode(', ', $migrations)) ?>) :
        <a href="<?= e(url('/admin/settings')) ?>">appliquer depuis les réglages</a>.</div>
<?php endif; ?>
<?php if ($certificateExpires !== null && strtotime($certificateExpires . ' UTC') < time() + 90 * 86400): ?>
    <div class="alert alert-warning">Le certificat du serveur expire le <?= e(format_datetime($certificateExpires, 'd/m/Y')) ?> :
        <a href="<?= e(url('/admin/settings')) ?>">régénérez la paire de clés</a>.</div>
<?php endif; ?>

<section class="card">
    <ul class="stats">
        <li><strong><?= (int) $stats['online'] ?></strong><span>en ligne</span></li>
        <li><strong><?= (int) $stats['pilots'] ?></strong><span>comptes actifs</span></li>
        <li><strong><?= $pending ?></strong><span><a href="<?= e(url('/admin/users', ['q' => ''])) ?>">en attente</a></span></li>
        <li><strong><?= (int) $stats['flights_today'] ?></strong><span>vols aujourd'hui</span></li>
    </ul>
</section>

<section class="card">
    <h2>Pilotes connectés</h2>
    <?php if ($online): ?>
        <table class="table">
            <thead><tr><th>Pilote</th><th>Serveur</th><th>Simulateur</th><th>Client</th><th>Depuis</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($online as $p): ?>
                <tr>
                    <td><?= e($p['callsign']) ?></td>
                    <td><?= e($p['server_name']) ?><?= (int) $p['global_mode'] ? ' <span class="badge">global</span>' : '' ?></td>
                    <td><?= e($p['sim'] ?: '—') ?></td>
                    <td><?= e(str_replace('_', ' ', $p['client_version'])) ?></td>
                    <td><?= e(format_datetime($p['connected_at'], 'H:i')) ?></td>
                    <td>
                        <form method="post" action="<?= e(url('/admin/kick/' . $p['user_id'])) ?>" class="inline confirm" data-confirm="Déconnecter ce pilote ?">
                            <?= csrf_field() ?><button class="button danger small" type="submit">Déconnecter</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">Aucun pilote connecté.</p>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Dernières inscriptions</h2>
    <table class="table">
        <tbody>
        <?php foreach ($latestUsers as $u): ?>
            <tr>
                <td><?= e($u['callsign']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td><?= e($u['status']) ?></td>
                <td><?= e(format_datetime($u['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
