<?php /** @var string $title */ /** @var string $message */ ?>
<section class="card narrow center">
    <h1><?= e($title) ?></h1>
    <p><?= e($message) ?></p>
    <p><a class="button" href="<?= e(url('/')) ?>">Retour à l'accueil</a></p>
</section>
