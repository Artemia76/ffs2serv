<?php /** @var array $teams */ /** @var ?array $user */ ?>
<header class="page-header">
    <h1>Équipes</h1>
    <p class="muted">Une équipe vole ensemble derrière son chef : dans ffs2play, les équipiers ne sont pas affichés comme appareils séparés.</p>
</header>
<section class="card">
    <?php if ($teams): ?>
        <table class="table">
            <thead><tr><th>Équipe</th><th>Chef</th><th>Membres</th></tr></thead>
            <tbody>
            <?php foreach ($teams as $team): ?>
                <tr>
                    <td><a href="<?= e(url('/teams/' . $team['id'])) ?>"><strong><?= e($team['name']) ?></strong></a></td>
                    <td><?= e($team['leader_callsign']) ?></td>
                    <td><?= (int) $team['members'] ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">Aucune équipe pour le moment.</p>
    <?php endif; ?>
</section>
<?php if ($user !== null && $user['team_id'] === null): ?>
    <section class="card narrow">
        <h2>Créer une équipe</h2>
        <form method="post" action="<?= e(url('/teams')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Nom de l'équipe <input name="name" required minlength="3" maxlength="64"></label>
            <button type="submit" class="button">Créer</button>
        </form>
    </section>
<?php endif; ?>
