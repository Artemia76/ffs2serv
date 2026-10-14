<?php
/** @var array $server */ /** @var ?array $user */ /** @var ?string $role */ /** @var bool $canJoin */
/** @var bool $canManage */ /** @var array $members */
$isOwner = $user !== null && (int) $server['owner_id'] === (int) $user['id'];
?>
<header class="page-header">
    <h1><?= e($server['name']) ?> <?= (int) $server['is_private'] ? '<span class="badge">privé</span>' : '<span class="badge online">public</span>' ?></h1>
    <p class="muted"><?= e($server['description'] ?: 'Aucune description.') ?>
        <?= $server['owner_callsign'] ? ' · créé par ' . e($server['owner_callsign']) : '' ?></p>
</header>

<section class="card">
    <?php if ($user === null): ?>
        <p><a href="<?= e(url('/login')) ?>">Connectez-vous</a> pour rejoindre ce serveur.</p>
    <?php elseif ($canJoin): ?>
        <p>Vous pouvez voler sur ce serveur : sélectionnez-le dans <a href="<?= e(url('/profile')) ?>">votre profil</a>.</p>
        <?php if (in_array($role, ['member', 'admin'], true) && !$isOwner): ?>
            <form method="post" action="<?= e(url('/servers/' . $server['id'] . '/leave')) ?>" class="inline">
                <?= csrf_field() ?><button class="button ghost small" type="submit">Quitter ce serveur</button>
            </form>
        <?php endif; ?>
    <?php elseif ($role === 'pending'): ?>
        <p>Votre demande d'adhésion est en attente de validation.</p>
        <form method="post" action="<?= e(url('/servers/' . $server['id'] . '/leave')) ?>" class="inline">
            <?= csrf_field() ?><button class="button ghost small" type="submit">Annuler la demande</button>
        </form>
    <?php else: ?>
        <form method="post" action="<?= e(url('/servers/' . $server['id'] . '/join')) ?>">
            <?= csrf_field() ?><button class="button" type="submit">Demander à rejoindre</button>
        </form>
    <?php endif; ?>
</section>

<?php if ($members || $canManage): ?>
    <section class="card">
        <h2>Membres</h2>
        <?php if ($members): ?>
            <table class="table">
                <thead><tr><th>Pilote</th><th>Rôle</th><?php if ($canManage): ?><th></th><?php endif; ?></tr></thead>
                <tbody>
                <?php foreach ($members as $m): ?>
                    <tr>
                        <td><a href="<?= e(url('/pilots/' . rawurlencode($m['callsign']))) ?>"><?= e($m['callsign']) ?></a></td>
                        <td><?= e(['pending' => 'demande en attente', 'member' => 'membre', 'admin' => 'administrateur'][$m['role']] ?? $m['role']) ?></td>
                        <?php if ($canManage): ?>
                            <td class="actions">
                                <?php foreach (($m['role'] === 'pending' ? ['approve' => 'Accepter', 'remove' => 'Refuser']
                                    : ($m['role'] === 'admin' ? ['demote' => 'Rétrograder', 'remove' => 'Retirer'] : ['promote' => 'Nommer admin', 'remove' => 'Retirer'])) as $action => $label): ?>
                                    <form method="post" action="<?= e(url('/servers/' . $server['id'] . '/members')) ?>" class="inline">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                                        <button class="button small<?= $action === 'remove' ? ' danger' : ' ghost' ?>" name="action" value="<?= e($action) ?>"><?= e($label) ?></button>
                                    </form>
                                <?php endforeach; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">Aucun membre.</p>
        <?php endif; ?>
        <?php if ($canManage): ?>
            <form method="post" action="<?= e(url('/servers/' . $server['id'] . '/members')) ?>" class="form row">
                <?= csrf_field() ?>
                <input name="callsign" placeholder="Indicatif du pilote" required>
                <button class="button ghost" name="action" value="add">Ajouter un membre</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>

<?php if ($canManage): ?>
    <section class="card narrow">
        <h2>Réglages du serveur</h2>
        <form method="post" action="<?= e(url('/servers/' . $server['id'] . '/edit')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Nom <input name="name" value="<?= e($server['name']) ?>" required minlength="3" maxlength="64"></label>
            <label>Description <input name="description" value="<?= e($server['description']) ?>" maxlength="500"></label>
            <?php if (!(int) $server['is_default']): ?>
                <label class="checkbox"><input type="checkbox" name="is_private" value="1"<?= (int) $server['is_private'] ? ' checked' : '' ?>> Privé</label>
            <?php endif; ?>
            <button type="submit" class="button">Enregistrer</button>
        </form>
        <?php if (!(int) $server['is_default'] && ($isOwner || ($user['role'] ?? '') === 'admin')): ?>
            <form method="post" action="<?= e(url('/servers/' . $server['id'] . '/delete')) ?>" class="confirm" data-confirm="Supprimer définitivement ce serveur ?">
                <?= csrf_field() ?><button class="button danger small" type="submit">Supprimer le serveur</button>
            </form>
        <?php endif; ?>
    </section>
<?php endif; ?>
