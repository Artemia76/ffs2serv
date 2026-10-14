<?php /** @var bool $already */ ?>
<section class="card narrow center">
    <?php if ($already): ?>
        <h1>Portail déjà installé</h1>
        <p>Pour réinstaller, supprimez le fichier <code>config/config.php</code> (et videz la base de données).</p>
    <?php else: ?>
        <h1>Installation terminée</h1>
        <p>Connectez-vous avec le compte administrateur, puis configurez l'envoi d'e-mails dans <em>Administration › Réglages</em>.</p>
    <?php endif; ?>
    <p><a class="button" href="<?= e(url('/login')) ?>">Se connecter</a></p>
</section>
