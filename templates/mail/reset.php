<?php /** @var array $user */ /** @var string $link */ /** @var string $site */ ?>
Bonjour <?= $user['callsign'] ?>,

Une réinitialisation du mot de passe de votre compte <?= $site ?> a été demandée.
Pour choisir un nouveau mot de passe, ouvrez ce lien (valable 1 heure) :

<?= $link ?>


Si vous n'êtes pas à l'origine de cette demande, ignorez ce message : votre mot de passe reste inchangé.
