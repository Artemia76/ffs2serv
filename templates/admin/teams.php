<?php /** @var array $teams */ ?>
<header class="page-header"><h1>Équipes</h1></header>
<?= $view->partial('admin/nav') ?>
<section class="card">
    <?php if ($teams): ?>
        <table class="table">
            <thead><tr><th>Équipe</th><th>Chef</th><th>Membres</th><th>Créée le</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($teams as $t): ?>
                <tr>
                    <td><a href="<?= e(url('/teams/' . $t['id'])) ?>"><?= e($t['name']) ?></a></td>
                    <td><?= e($t['leader_callsign']) ?></td>
                    <td><?= (int) $t['members'] ?></td>
                    <td><?= e(format_datetime($t['created_at'], 'd/m/Y')) ?></td>
                    <td>
                        <form method="post" action="<?= e(url('/teams/' . $t['id'] . '/manage')) ?>" class="inline confirm" data-confirm="Dissoudre cette équipe ?">
                            <?= csrf_field() ?><button class="button danger small" name="action" value="dissolve">Dissoudre</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">Aucune équipe.</p>
    <?php endif; ?>
</section>
