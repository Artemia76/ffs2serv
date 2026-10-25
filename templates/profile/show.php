<?php
/** @var array $user */ /** @var array $errors */ /** @var array $servers */ /** @var int $preferred */
/** @var ?array $slot */ /** @var bool $online */ /** @var ?array $team */ /** @var array $stats */
/** @var array $flights */ /** @var array $connections */ /** @var string $serverUrl */
$currentServer = null;
foreach ($servers as $server) {
    if ((int) $server['id'] === (int) ($slot['server_id'] ?? $preferred)) {
        $currentServer = $server;
    }
}
?>
<header class="page-header">
    <h1><?= e($user['callsign']) ?></h1>
    <p class="muted">Membre depuis le <?= e(format_datetime($user['created_at'], 'd/m/Y')) ?>
        · <a href="<?= e(url('/pilots/' . rawurlencode($user['callsign']))) ?>">voir mon profil public</a></p>
</header>

<?= $view->partial('partials/errors', ['errors' => $errors]) ?>

<div class="grid two">
    <section class="card">
        <h2>ffs2play</h2>
        <?php if ($online): ?>
            <p><span class="badge online">Connecté</span>
                sur <strong><?= e($currentServer['name'] ?? '?') ?></strong>
                depuis <?= e(format_datetime($slot['connected_at'], 'H:i')) ?><?= $slot['sim'] !== '' ? ' · ' . e($slot['sim']) : '' ?></p>
        <?php else: ?>
            <p><span class="badge offline">Hors ligne</span></p>
        <?php endif; ?>
        <details<?= $online ? '' : ' open' ?>>
            <summary>Connecter ffs2play à ce site</summary>
            <ol class="steps">
                <li>Installez <a href="<?= e(url('/telechargements')) ?>">ffs2play</a> et lancez-le avec votre simulateur.</li>
                <li>Ouvrez <em>Profils</em> et créez un profil avec :
                    <dl class="kv">
                        <dt>URL</dt><dd><code class="copy"><?= e($serverUrl) ?></code></dd>
                        <dt>Login</dt><dd><code class="copy"><?= e($user['callsign']) ?></code></dd>
                        <dt>Mot de passe</dt><dd>le mot de passe de votre compte sur ce site</dd>
                    </dl>
                </li>
                <li>Cliquez sur <em>Connecter</em> : vous apparaissez sur la page « En direct ».</li>
            </ol>
        </details>
    </section>

    <section class="card">
        <h2>Serveur de jeu</h2>
        <p class="muted">Vous ne voyez en vol que les pilotes de votre serveur. En mode global, vous voyez aussi tous les pilotes en mode global.</p>
        <form method="post" action="<?= e(url('/profile/server')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Serveur
                <select name="server_id">
                    <?php foreach ($servers as $server): ?>
                        <option value="<?= (int) $server['id'] ?>"<?= (int) $server['id'] === $preferred ? ' selected' : '' ?>>
                            <?= e($server['name']) ?><?= (int) $server['is_private'] ? ' (privé)' : '' ?> — <?= (int) $server['online'] ?> en ligne
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label class="checkbox"><input type="checkbox" name="global_mode" value="1"<?= (int) $user['global_mode'] ? ' checked' : '' ?>> Mode global</label>
            <button type="submit" class="button">Appliquer</button>
        </form>
        <p class="muted small"><a href="<?= e(url('/servers')) ?>">Tous les serveurs</a> ·
            Équipe : <?= $team ? '<a href="' . e(url('/teams/' . $team['id'])) . '">' . e($team['name']) . '</a>' : '<a href="' . e(url('/teams')) . '">aucune</a>' ?></p>
    </section>
</div>

<section class="card">
    <h2>Statistiques</h2>
    <ul class="stats">
        <li><strong><?= (int) $stats['flights'] ?></strong><span>vols</span></li>
        <li><strong><?= e(format_duration($stats['flight_seconds'])) ?></strong><span>de vol</span></li>
        <li><strong><?= e(number_format($stats['distance_nm'], 0, ',', ' ')) ?></strong><span>NM parcourus</span></li>
        <li><strong><?= e(format_duration($stats['connected_seconds'])) ?></strong><span>connecté</span></li>
    </ul>
    <div class="grid two">
        <div>
            <h3>Appareils favoris</h3>
            <?php if ($stats['aircraft']): ?>
                <ol><?php foreach ($stats['aircraft'] as $a): ?><li><?= e($a['aircraft']) ?> <span class="muted">(<?= (int) $a['count'] ?>)</span></li><?php endforeach; ?></ol>
            <?php else: ?><p class="muted">Aucun vol enregistré.</p><?php endif; ?>
        </div>
        <div>
            <h3>Simulateurs</h3>
            <?php if ($stats['sims']): ?>
                <ol><?php foreach ($stats['sims'] as $s): ?><li><?= e($s['sim']) ?> <span class="muted">(<?= (int) $s['count'] ?>)</span></li><?php endforeach; ?></ol>
            <?php else: ?><p class="muted">—</p><?php endif; ?>
        </div>
    </div>
</section>

<section class="card">
    <h2>Mes vols</h2>
    <?= $view->partial('partials/flights', ['flights' => $flights]) ?>
</section>

<section class="card">
    <h2>Dernières connexions</h2>
    <?php if ($connections): ?>
        <table class="table">
            <thead><tr><th>Début</th><th>Fin</th><th>Serveur</th><th>Simulateur</th><th>Version</th></tr></thead>
            <tbody>
            <?php foreach ($connections as $c): ?>
                <tr>
                    <td><?= e(format_datetime($c['connected_at'])) ?></td>
                    <td><?= $c['disconnected_at'] ? e(format_datetime($c['disconnected_at'])) : '<span class="badge online">en cours</span>' ?></td>
                    <td><?= e($c['server_name'] ?? '—') ?></td>
                    <td><?= e($c['sim'] ?: '—') ?></td>
                    <td><?= e(str_replace('_', ' ', $c['client_version'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p class="muted">ffs2play ne s'est encore jamais connecté avec ce compte.</p>
    <?php endif; ?>
</section>

<div class="grid two">
    <section class="card">
        <h2>Adresse e-mail</h2>
        <form method="post" action="<?= e(url('/profile/email')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Nouvelle adresse
                <input type="email" name="email" value="<?= e($user['email']) ?>" required maxlength="191" autocomplete="email">
            </label>
            <label>Mot de passe actuel
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <button type="submit" class="button ghost">Modifier</button>
        </form>
    </section>
    <section class="card">
        <h2>Mot de passe</h2>
        <form method="post" action="<?= e(url('/profile/password')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Mot de passe actuel
                <input type="password" name="current_password" required autocomplete="current-password">
            </label>
            <label>Nouveau mot de passe
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label>Confirmation
                <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </label>
            <button type="submit" class="button ghost">Modifier</button>
        </form>
    </section>
</div>
