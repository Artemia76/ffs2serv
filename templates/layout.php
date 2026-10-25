<?php
/** @var string $content */
/** @var string $title */
use Ffs2\Core\App;

$app = App::get();
$site = $app->settings()->get('site_name');
$currentUser = $app->auth()->user();
$flashes = $app->session()->takeFlash();
$path = $app->router()->currentPath();
$active = fn (string $prefix) => ($prefix === '/' ? $path === '/' : str_starts_with($path, $prefix)) ? ' aria-current="page"' : '';
?><!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title !== '' ? "$title · $site" : $site) ?></title>
    <meta name="description" content="<?= e($app->settings()->get('site_description')) ?>">
    <link rel="icon" href="<?= e(asset('img/plane.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e(asset('css/app.css')) ?>">
    <?php if (!empty($withMap)): ?>
        <link rel="stylesheet" href="<?= e(asset('vendor/leaflet/leaflet.css')) ?>">
    <?php endif; ?>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= e(url('/')) ?>">
            <img src="<?= e(asset('img/plane.svg')) ?>" alt="" width="28" height="28">
            <span><?= e($site) ?></span>
        </a>
        <input type="checkbox" id="nav-toggle" class="nav-toggle" aria-label="Menu">
        <label for="nav-toggle" class="nav-burger" aria-hidden="true"><span></span></label>
        <nav class="nav">
            <a href="<?= e(url('/')) ?>"<?= $active('/') ?>>En direct</a>
            <a href="<?= e(url('/servers')) ?>"<?= $active('/servers') ?>>Serveurs</a>
            <a href="<?= e(url('/teams')) ?>"<?= $active('/teams') ?>>Équipes</a>
            <?php if ($currentUser !== null): ?>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="<?= e(url('/admin')) ?>"<?= $active('/admin') ?>>Administration</a>
                <?php endif; ?>
                <a href="<?= e(url('/profile')) ?>"<?= $active('/profile') ?> class="nav-user"><?= e($currentUser['callsign']) ?></a>
                <form method="post" action="<?= e(url('/logout')) ?>" class="inline">
                    <?= csrf_field() ?>
                    <button type="submit" class="link">Déconnexion</button>
                </form>
            <?php else: ?>
                <a href="<?= e(url('/login')) ?>"<?= $active('/login') ?>>Connexion</a>
                <a href="<?= e(url('/register')) ?>" class="button small">Inscription</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="container">
    <?php foreach ($flashes as $flash): ?>
        <div class="alert alert-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
    <?= $content ?>
</main>
<footer class="footer">
    <p><?= e($site) ?> · propulsé par <a href="https://github.com/Artemia76/ffs2play">FFS2play</a>, logiciel libre GPLv3</p>
</footer>
<?php if (!empty($withMap)): ?>
    <script src="<?= e(asset('vendor/leaflet/leaflet.js')) ?>"></script>
<?php endif; ?>
<script src="<?= e(asset('js/app.js')) ?>"></script>
<?php foreach ($scripts ?? [] as $script): ?>
    <script src="<?= e(asset($script)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
