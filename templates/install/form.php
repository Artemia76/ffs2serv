<?php /** @var array $requirements */ /** @var array $errors */ /** @var array $input */ ?>
<section class="card narrow">
    <h1>Installation du portail FFS2play</h1>
    <h2>Prérequis</h2>
    <ul class="install-checks">
        <?php foreach ($requirements as $r): ?>
            <li class="<?= $r['ok'] ? 'ok' : 'ko' ?>"><?= e($r['label']) ?></li>
        <?php endforeach; ?>
    </ul>
    <?= $view->partial('partials/errors', ['errors' => $errors]) ?>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <h2>Base de données MariaDB / MySQL</h2>
        <p class="muted small">La base doit déjà exister (créée depuis le panneau de votre hébergeur).</p>
        <label>Serveur <input name="db_host" value="<?= e($input['db_host']) ?>" required></label>
        <label>Port <input type="number" name="db_port" value="<?= e($input['db_port']) ?>" required></label>
        <label>Nom de la base <input name="db_name" value="<?= e($input['db_name']) ?>" required></label>
        <label>Utilisateur <input name="db_user" value="<?= e($input['db_user']) ?>" required autocomplete="off"></label>
        <label>Mot de passe <input type="password" name="db_pass" autocomplete="new-password"></label>
        <label>Préfixe des tables <input name="db_prefix" value="<?= e($input['db_prefix']) ?>" required pattern="[a-z][a-z0-9_]{0,15}"></label>

        <h2>Site</h2>
        <label>Nom du site <input name="site_name" value="<?= e($input['site_name']) ?>" required maxlength="64"></label>
        <label>Adresse publique du site <small>facultatif ; à renseigner si le site est derrière un proxy (ex. https://ffs2play.fr)</small>
            <input type="url" name="base_url" value="<?= e($input['base_url']) ?>" placeholder="https://"></label>

        <h2>Compte administrateur</h2>
        <label>Indicatif <input name="admin_callsign" value="<?= e($input['admin_callsign']) ?>" required minlength="2" maxlength="32"></label>
        <label>E-mail <input type="email" name="admin_email" value="<?= e($input['admin_email']) ?>" required></label>
        <label>Mot de passe <input type="password" name="admin_password" required minlength="8" autocomplete="new-password"></label>

        <button type="submit" class="button">Installer</button>
    </form>
</section>
