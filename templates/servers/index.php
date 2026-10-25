<?php /** @var array $servers */ /** @var ?array $user */ /** @var bool $canCreate */ ?>
<header class="page-header">
    <h1>Serveurs</h1>
    <p class="muted">Chaque serveur est un salon : en vol, vous ne voyez que les pilotes du même serveur (ou en mode global).
        Choisissez le vôtre depuis <a href="<?= e(url('/profile')) ?>">votre profil</a>.</p>
</header>
<section class="card">
    <table class="table">
        <thead><tr><th>Serveur</th><th>Accès</th><th>Propriétaire</th><th>Membres</th><th>En ligne</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($servers as $server): ?>
            <tr>
                <td><a href="<?= e(url('/servers/' . $server['id'])) ?>"><strong><?= e($server['name']) ?></strong></a>
                    <?php if ($server['description'] !== ''): ?><br><span class="muted small"><?= e($server['description']) ?></span><?php endif; ?></td>
                <td><?= (int) $server['is_private'] ? '<span class="badge">privé</span>' : '<span class="badge online">public</span>' ?></td>
                <td><?= e($server['owner_callsign'] ?? '—') ?></td>
                <td><?= (int) $server['members'] ?></td>
                <td><?= (int) $server['online'] ?></td>
                <td class="muted small"><?= match ($server['my_role']) {
                        'owner' => 'propriétaire', 'admin' => 'administrateur', 'member' => 'membre', 'pending' => 'demande envoyée', default => '',
                    } ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>
<?php if ($canCreate): ?>
    <section class="card narrow">
        <h2>Créer un serveur</h2>
        <form method="post" action="<?= e(url('/servers')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Nom <input name="name" required minlength="3" maxlength="64"></label>
            <label>Description <input name="description" maxlength="500"></label>
            <label class="checkbox"><input type="checkbox" name="is_private" value="1"> Privé (réservé aux membres que vous acceptez)</label>
            <button type="submit" class="button">Créer</button>
        </form>
    </section>
<?php endif; ?>
