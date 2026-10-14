<?php /** @var array $flights */ ?>
<?php if ($flights): ?>
    <table class="table">
        <thead><tr><th>Départ</th><th>Appareil</th><th>Durée</th><th>Distance</th><th>Alt. max</th><th>Serveur</th><th>État</th></tr></thead>
        <tbody>
        <?php foreach ($flights as $f): ?>
            <tr>
                <td><a href="<?= e(url('/flights/' . $f['id'])) ?>"><?= e(format_datetime($f['started_at'])) ?></a></td>
                <td><?= e($f['aircraft'] ?: '—') ?></td>
                <td><?= e(format_duration($f['duration'])) ?></td>
                <td><?= e(number_format((float) $f['distance_nm'], 0, ',', ' ')) ?> NM</td>
                <td><?= e(number_format((int) $f['max_altitude'], 0, ',', ' ')) ?> ft</td>
                <td><?= e($f['server_name'] ?? '—') ?></td>
                <td><?= match ($f['status']) {
                        'completed' => '<span class="badge online">terminé</span>',
                        'in_progress' => '<span class="badge live">en vol</span>',
                        default => '<span class="badge offline">interrompu</span>',
                    } ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php else: ?>
    <p class="muted">Aucun vol pour le moment : les vols sont enregistrés automatiquement du décollage à l'atterrissage.</p>
<?php endif; ?>
