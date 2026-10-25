<?php /** @var string $token */ /** @var array $errors */ ?>
<section class="card narrow">
    <h1>Nouveau mot de passe</h1>
    <?= $view->partial('partials/errors', ['errors' => $errors]) ?>
    <form method="post" action="<?= e(url('/reset')) ?>" class="form">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <label>Nouveau mot de passe
            <input type="password" name="password" required minlength="8" autocomplete="new-password" autofocus>
        </label>
        <label>Confirmation
            <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password">
        </label>
        <button type="submit" class="button">Enregistrer</button>
    </form>
</section>
