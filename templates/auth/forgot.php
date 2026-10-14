<?php /** @var bool $mail */ ?>
<section class="card narrow">
    <h1>Mot de passe oublié</h1>
    <?php if (!$mail): ?>
        <p>L'envoi d'e-mails n'est pas configuré sur ce site : contactez un administrateur pour réinitialiser votre mot de passe.</p>
    <?php else: ?>
        <p class="muted">Indiquez l'adresse e-mail de votre compte : vous recevrez un lien pour choisir un nouveau mot de passe.</p>
        <form method="post" action="<?= e(url('/forgot')) ?>" class="form">
            <?= csrf_field() ?>
            <label>Adresse e-mail
                <input type="email" name="email" required autocomplete="email" autofocus>
            </label>
            <button type="submit" class="button">Envoyer le lien</button>
        </form>
    <?php endif; ?>
</section>
