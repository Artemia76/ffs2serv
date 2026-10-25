<?php
/** @var array $stats */ /** @var array $latest */ /** @var ?array $user */ /** @var array $tiles */
?>
<section class="hero">
    <div>
        <h1>Pilotes en direct</h1>
        <p class="muted">Positions mises à jour toutes les 5 secondes par les clients ffs2play connectés.</p>
    </div>
    <ul class="stats">
        <li><strong id="stat-online"><?= (int) $stats['online'] ?></strong><span>en ligne</span></li>
        <li><strong><?= (int) $stats['pilots'] ?></strong><span>pilotes inscrits</span></li>
        <li><strong><?= (int) $stats['flights_today'] ?></strong><span>vols aujourd'hui</span></li>
        <li><strong><?= (int) $stats['flight_hours'] ?></strong><span>heures de vol</span></li>
    </ul>
</section>

<section class="live" id="live"
         data-api="<?= e(url('/api/live')) ?>"
         data-pilot-url="<?= e(url('/pilots/')) ?>"
         data-tiles="<?= e($tiles['url']) ?>"
         data-attribution="<?= e($tiles['attribution']) ?>"
         data-icon="<?= e(asset('img/plane.svg')) ?>">
    <div class="tabs" id="live-tabs" role="tablist"></div>
    <div class="live-grid">
        <div id="live-map" class="map" aria-label="Carte des pilotes"></div>
        <div class="card table-card">
            <table class="table" id="live-table">
                <thead>
                <tr><th>Pilote</th><th>Appareil</th><th>Phase</th><th>Alt.</th><th>Vit.</th><th>Cap</th><th>Simulateur</th></tr>
                </thead>
                <tbody><tr><td colspan="7" class="muted">Chargement…</td></tr></tbody>
            </table>
            <p class="muted small" id="live-updated"></p>
        </div>
    </div>
</section>

<?php if ($user === null): ?>
    <section class="card cta">
        <h2>Rejoignez les pilotes</h2>
        <p>Créez un compte, installez <a href="<?= e(url('/telechargements')) ?>">ffs2play</a> et volez à plusieurs
            sur MSFS, Prepar3D, FSX, X-Plane ou FlightGear, en pair à pair.</p>
        <p><a class="button" href="<?= e(url('/register')) ?>">Créer un compte</a> <a class="button ghost" href="<?= e(url('/login')) ?>">Se connecter</a></p>
    </section>
<?php endif; ?>

<?php if ($latest): ?>
    <section class="card">
        <h2>Derniers vols</h2>
        <table class="table">
            <thead><tr><th>Pilote</th><th>Appareil</th><th>Arrivée</th><th>Durée</th><th>Distance</th></tr></thead>
            <tbody>
            <?php foreach ($latest as $flight): ?>
                <tr>
                    <td><a href="<?= e(url('/pilots/' . rawurlencode($flight['callsign']))) ?>"><?= e($flight['callsign']) ?></a></td>
                    <td><?= e($flight['aircraft'] ?: '—') ?></td>
                    <td><a href="<?= e(url('/flights/' . $flight['id'])) ?>"><?= e(format_datetime($flight['ended_at'])) ?></a></td>
                    <td><?= e(format_duration($flight['duration'])) ?></td>
                    <td><?= e(number_format((float) $flight['distance_nm'], 0, ',', ' ')) ?> NM</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
