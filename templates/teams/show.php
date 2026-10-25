<?php
/** @var array $team */ /** @var ?array $user */ /** @var bool $isLeader */ /** @var bool $isMember */
/** @var bool $hasRequested */ /** @var array $members */ /** @var array $requests */
$canManage = $isLeader || ($user['role'] ?? '') === 'admin';
$base = url('/teams/' . $team['id']);
?>
<header class="page-header">
    <h1><?= e($team['name']) ?></h1>
    <p class="muted">Chef d'équipe : <?= e($team['leader_callsign']) ?> · créée le <?= e(format_datetime($team['created_at'], 'd/m/Y')) ?></p>
</header>

<section class="card">
    <?php if ($user === null): ?>
        <p><a href="<?= e(url('/login')) ?>">Connectez-vous</a> pour rejoindre cette équipe.</p>
    <?php elseif ($isMember): ?>
        <form method="post" action="<?= e($base . '/leave') ?>" class="inline confirm" data-confirm="<?= $isLeader ? "Quitter et dissoudre l'équipe ?" : "Quitter l'équipe ?" ?>">
            <?= csrf_field() ?><button class="button ghost" type="submit"><?= $isLeader && count($members) === 1 ? "Dissoudre l'équipe" : "Quitter l'équipe" ?></button>
        </form>
    <?php elseif ($user['team_id'] !== null): ?>
        <p class="muted">Vous faites déjà partie d'une autre équipe.</p>
    <?php elseif ($hasRequested): ?>
        <p>Votre demande est en attente de validation par le chef.</p>
        <form method="post" action="<?= e($base . '/cancel') ?>" class="inline">
            <?= csrf_field() ?><button class="button ghost small" type="submit">Annuler la demande</button>
        </form>
    <?php else: ?>
        <form method="post" action="<?= e($base . '/join') ?>">
            <?= csrf_field() ?><button class="button" type="submit">Demander à rejoindre</button>
        </form>
    <?php endif; ?>
</section>

<section class="card">
    <h2>Membres</h2>
    <table class="table">
        <tbody>
        <?php foreach ($members as $m): ?>
            <tr>
                <td><a href="<?= e(url('/pilots/' . rawurlencode($m['callsign']))) ?>"><?= e($m['callsign']) ?></a>
                    <?= (int) $m['id'] === (int) $team['leader_id'] ? '<span class="badge">chef</span>' : '' ?></td>
                <?php if ($canManage): ?>
                    <td class="actions">
                        <?php if ((int) $m['id'] !== (int) $team['leader_id']): ?>
                            <form method="post" action="<?= e($base . '/manage') ?>" class="inline">
                                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                                <button class="button ghost small" name="action" value="transfer">Nommer chef</button>
                                <button class="button danger small" name="action" value="kick">Retirer</button>
                            </form>
                        <?php endif; ?>
                    </td>
                <?php endif; ?>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</section>

<?php if ($canManage): ?>
    <section class="card">
        <h2>Demandes d'adhésion</h2>
        <?php if ($requests): ?>
            <table class="table">
                <tbody>
                <?php foreach ($requests as $r): ?>
                    <tr>
                        <td><a href="<?= e(url('/pilots/' . rawurlencode($r['callsign']))) ?>"><?= e($r['callsign']) ?></a>
                            <span class="muted small"><?= e(format_datetime($r['created_at'])) ?></span></td>
                        <td class="actions">
                            <form method="post" action="<?= e($base . '/manage') ?>" class="inline">
                                <?= csrf_field() ?><input type="hidden" name="user_id" value="<?= (int) $r['id'] ?>">
                                <button class="button small" name="action" value="accept">Accepter</button>
                                <button class="button ghost small" name="action" value="refuse">Refuser</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <p class="muted">Aucune demande en attente.</p>
        <?php endif; ?>
        <form method="post" action="<?= e($base . '/manage') ?>" class="confirm" data-confirm="Dissoudre l'équipe ? Tous les membres seront libérés.">
            <?= csrf_field() ?><button class="button danger small" name="action" value="dissolve">Dissoudre l'équipe</button>
        </form>
    </section>
<?php endif; ?>
