<?php
/*
 * Fonctions utilitaires globales utilisées par les templates
 */
declare(strict_types=1);

use Ffs2\Core\App;

/** Échappement HTML : à utiliser pour toute donnée affichée */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** URL absolue (chemin) d'une page du portail */
function url(string $path = '/', array $query = []): string
{
    return App::get()->router()->url($path, $query);
}

/** URL d'un fichier statique de public/assets */
function asset(string $path): string
{
    $public = defined('FFS2_PUBLIC') ? FFS2_PUBLIC : FFS2_ROOT . '/public';
    $file = $public . '/assets/' . ltrim($path, '/');
    $version = is_file($file) ? '?v=' . substr(md5((string) filemtime($file)), 0, 8) : '';
    return App::get()->router()->basePath() . '/assets/' . ltrim($path, '/') . $version;
}

/** Champ caché portant le jeton CSRF */
function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(App::get()->csrf()->token()) . '">';
}

/** Date UTC (Y-m-d H:i:s) affichée dans le fuseau du portail */
function format_datetime(?string $utc, string $format = 'd/m/Y H:i'): string
{
    if ($utc === null || $utc === '') {
        return '—';
    }
    $date = new DateTimeImmutable($utc, new DateTimeZone('UTC'));
    return $date->setTimezone(new DateTimeZone(App::get()->settings()->get('timezone')))->format($format);
}

/** Durée en secondes → « 3 h 05 » */
function format_duration(int|float|null $seconds): string
{
    $seconds = (int) $seconds;
    if ($seconds < 60) {
        return $seconds > 0 ? '< 1 min' : '0 min';
    }
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return $hours > 0 ? sprintf('%d h %02d', $hours, $minutes) : sprintf('%d min', $minutes);
}

/** Instant présent en UTC au format SQL */
function now_utc(int $offsetSeconds = 0): string
{
    return gmdate('Y-m-d H:i:s', time() + $offsetSeconds);
}
