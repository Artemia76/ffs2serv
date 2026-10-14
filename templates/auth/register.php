<?php /** @var array $errors */ /** @var array $old */ /** @var bool $open */ ?>
<section class="card narrow">
    <h1>Créer un compte pilote</h1>
    <?php if (!$open): ?>
        <p>Les inscriptions sont actuellement fermées.</p>
    <?php else: ?>
        <p class="muted">Votre indicatif et votre mot de passe servent aussi à connecter le logiciel ffs2play.</p>
        <?= $view->partial('partials/errors', ['errors' => $errors]) ?>
        <form method="post" action="<?= e(url('/register')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Indicatif (callsign)
                <input name="callsign" value="<?= e($old['callsign'] ?? '') ?>" required minlength="2" maxlength="32"
                       pattern="[A-Za-z_éèà][A-Za-z0-9_.\-éèà]{1,31}" autocomplete="username" autofocus>
                <small>2 à 32 caractères : lettres, chiffres, _ - . é è à ; commence par une lettre. Visible par les autres pilotes.</small>
            </label>
            <label>Adresse e-mail
                <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required maxlength="191" autocomplete="email">
            </label>
            <label>Mot de passe
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
            </label>
            <label>Confirmation du mot de passe
                <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
            </label>
            <button type="submit" class="button">Créer mon compte</button>
        </form>
        <p class="muted">Déjà inscrit ? <a href="<?= e(url('/login')) ?>">Connectez-vous</a>.</p>
    <?php endif; ?>
</section>
