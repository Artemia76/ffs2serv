<?php /** @var string $error */ /** @var string $login */ ?>
<section class="card narrow">
    <h1>Connexion</h1>
    <?php if ($error !== ''): ?>
        <div class="alert alert-error" role="alert"><?= e($error) ?></div>
    <?php endif; ?>
    <form method="post" action="<?= e(url('/login')) ?>" class="form">
        <?= csrf_field() ?>
        <label>Indicatif ou adresse e-mail
            <input name="login" value="<?= e($login) ?>" required autocomplete="username" autofocus>
        </label>
        <label>Mot de passe
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="button">Se connecter</button>
    </form>
    <p class="muted">
        <a href="<?= e(url('/forgot')) ?>">Mot de passe oublié ?</a> ·
        <a href="<?= e(url('/verify/resend')) ?>">Renvoyer le lien de validation</a> ·
        <a href="<?= e(url('/register')) ?>">Créer un compte</a>
    </p>
</section>
