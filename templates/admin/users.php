<?php
/** @var array $users */ /** @var string $term */ /** @var int $page */ /** @var int $pages */ /** @var int $total */ /** @var ?int $me */
$statusLabels = ['pending' => 'en attente', 'active' => 'actif', 'banned' => 'suspendu'];
?>
<header class="page-header"><h1>Membres <span class="muted">(<?= $total ?>)</span></h1></header>
<?= $view->partial('admin/nav') ?>

<form method="get" action="<?= e(url('/admin/users')) ?>" class="form row">
    <input name="q" value="<?= e($term) ?>" placeholder="Indicatif ou e-mail">
    <button class="button ghost" type="submit">Rechercher</button>
</form>

<section class="card">
    <table class="table">
        <thead><tr><th>Indicatif</th><th>E-mail</th><th>État</th><th>Rôle</th><th>Inscrit</th><th>Dernière connexion</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><a href="<?= e(url('/pilots/' . rawurlencode($u['callsign']))) ?>"><?= e($u['callsign']) ?></a></td>
                <td><?= e($u['email']) ?><?= $u['email_verified_at'] === null ? ' <span class="badge offline">non vérifié</span>' : '' ?></td>
                <td><?= e($statusLabels[$u['status']] ?? $u['status']) ?></td>
                <td><?= $u['role'] === 'admin' ? '<span class="badge">admin</span>' : 'pilote' ?></td>
                <td><?= e(format_datetime($u['created_at'], 'd/m/Y')) ?></td>
                <td><?= e(format_datetime($u['last_login_at'])) ?></td>
                <td class="actions">
                    <form method="post" action="<?= e(url('/admin/users/' . $u['id'])) ?>" class="inline">
                        <?= csrf_field() ?>
                        <?php if ($u['status'] === 'pending'): ?>
                            <button class="button small" name="action" value="activate">Activer</button>
                            <button class="button ghost small" name="action" value="resend">Renvoyer le lien</button>
                        <?php endif; ?>
                        <?php if ((int) $u['id'] !== $me): ?>
                            <?php if ($u['status'] === 'banned'): ?>
                                <button class="button ghost small" name="action" value="unban">Réactiver</button>
                            <?php else: ?>
                                <button class="button ghost small" name="action" value="ban">Suspendre</button>
                            <?php endif; ?>
                            <button class="button ghost small" name="action" value="<?= $u['role'] === 'admin' ? 'pilot' : 'admin' ?>">
                                <?= $u['role'] === 'admin' ? 'Retirer admin' : 'Nommer admin' ?></button>
                        <?php endif; ?>
                    </form>
                    <?php if ((int) $u['id'] !== $me): ?>
                        <form method="post" action="<?= e(url('/admin/users/' . $u['id'])) ?>" class="inline confirm" data-confirm="Supprimer définitivement le compte <?= e($u['callsign']) ?> et tout son historique ?">
                            <?= csrf_field() ?><button class="button danger small" name="action" value="delete">Supprimer</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php if ($pages > 1): ?>
        <nav class="pagination">
            <?php for ($i = 1; $i <= $pages; $i++): ?>
                <a href="<?= e(url('/admin/users', ['q' => $term, 'page' => $i])) ?>"<?= $i === $page ? ' aria-current="page"' : '' ?>><?= $i ?></a>
            <?php endfor; ?>
        </nav>
    <?php endif; ?>
</section>
