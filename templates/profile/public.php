<?php
/** @var array $pilot */ /** @var array $stats */ /** @var array $flights */ /** @var bool $online */ /** @var ?array $team */
?>
<header class="page-header">
    <h1><?= e($pilot['callsign']) ?> <?= $online ? '<span class="badge online">en ligne</span>' : '' ?></h1>
    <p class="muted">Membre depuis le <?= e(format_datetime($pilot['created_at'], 'd/m/Y')) ?>
        <?php if ($team): ?> · équipe <a href="<?= e(url('/teams/' . $team['id'])) ?>"><?= e($team['name']) ?></a><?php endif; ?></p>
</header>
<section class="card">
    <ul class="stats">
        <li><strong><?= (int) $stats['flights'] ?></strong><span>vols</span></li>
        <li><strong><?= e(format_duration($stats['flight_seconds'])) ?></strong><span>de vol</span></li>
        <li><strong><?= e(number_format($stats['distance_nm'], 0, ',', ' ')) ?></strong><span>NM parcourus</span></li>
        <li><strong><?= e(number_format($stats['ceiling'], 0, ',', ' ')) ?></strong><span>ft plafond</span></li>
    </ul>
</section>
<section class="card">
    <h2>Vols récents</h2>
    <?= $view->partial('partials/flights', ['flights' => $flights]) ?>
</section>
