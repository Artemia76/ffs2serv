<?php /** @var array $flight */ /** @var array $track */ /** @var array $tiles */ ?>
<header class="page-header">
    <h1>Vol de <a href="<?= e(url('/pilots/' . rawurlencode($flight['callsign']))) ?>"><?= e($flight['callsign']) ?></a></h1>
    <p class="muted"><?= e($flight['aircraft'] ?: 'Appareil inconnu') ?><?= $flight['sim'] !== '' ? ' · ' . e($flight['sim']) : '' ?>
        <?= $flight['server_name'] ? ' · serveur ' . e($flight['server_name']) : '' ?></p>
</header>
<section class="card">
    <ul class="stats">
        <li><strong><?= e(format_datetime($flight['started_at'], 'd/m H:i')) ?></strong><span>décollage</span></li>
        <li><strong><?= e($flight['ended_at'] ? format_datetime($flight['ended_at'], 'd/m H:i') : '—') ?></strong><span>atterrissage</span></li>
        <li><strong><?= e(number_format((float) $flight['distance_nm'], 0, ',', ' ')) ?> NM</strong><span>distance</span></li>
        <li><strong><?= e(number_format((int) $flight['max_altitude'], 0, ',', ' ')) ?> ft</strong><span>altitude max</span></li>
    </ul>
</section>
<div id="flight-map" class="map tall"
     data-track="<?= e(json_encode($track)) ?>"
     data-departure="<?= e(json_encode([(float) $flight['dep_latitude'], (float) $flight['dep_longitude']])) ?>"
     data-arrival="<?= e(json_encode($flight['arr_latitude'] !== null ? [(float) $flight['arr_latitude'], (float) $flight['arr_longitude']] : null)) ?>"
     data-tiles="<?= e($tiles['url']) ?>"
     data-attribution="<?= e($tiles['attribution']) ?>"></div>
