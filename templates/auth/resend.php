<section class="card narrow">
    <h1>Renvoyer le lien de validation</h1>
    <p class="muted">Vous n'avez pas reçu l'e-mail de validation, ou le lien a expiré ? Pensez à vérifier vos courriers indésirables.</p>
    <form method="post" action="<?= e(url('/verify/resend')) ?>" class="form">
        <?= csrf_field() ?>
        <label>Adresse e-mail
            <input type="email" name="email" required autocomplete="email" autofocus>
        </label>
        <button type="submit" class="button">Renvoyer</button>
    </form>
</section>
