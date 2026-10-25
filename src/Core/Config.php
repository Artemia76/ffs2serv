<?php
declare(strict_types=1);

namespace Ffs2\Core;

/**
 * Configuration technique (accès base de données, clé applicative, chemins).
 * Générée par l'installeur dans config/config.php ; jamais versionnée.
 * Les réglages modifiables depuis le back-office sont dans la table settings (classe Settings).
 */
final class Config
{
    private array $values;

    private function __construct(array $values, private string $file)
    {
        $this->values = $values;
    }

    /** Fichier de configuration : variable d'environnement FFS2_CONFIG (tests) ou config/config.php */
    public static function path(): string
    {
        $env = getenv('FFS2_CONFIG');
        return $env !== false && $env !== '' ? $env : FFS2_ROOT . '/config/config.php';
    }

    public static function exists(): bool
    {
        return is_file(self::path());
    }

    public static function load(): self
    {
        $file = self::path();
        $values = is_file($file) ? require $file : [];
        if (!is_array($values)) {
            throw new \RuntimeException("Configuration invalide : $file");
        }
        return new self($values, $file);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $value = $this->values;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return $default;
            }
            $value = $value[$part];
        }
        return $value;
    }

    /** Dossier des clés RSA du serveur */
    public function keysDir(): string
    {
        return (string) $this->get('paths.keys', dirname($this->file) . '/keys');
    }

    /** Dossier des données de travail (journaux, e-mails du transport « fichier ») */
    public function storageDir(): string
    {
        return (string) $this->get('paths.storage', FFS2_ROOT . '/storage');
    }

    /** Écrit un fichier de configuration PHP (installeur) */
    public static function write(string $file, array $values): void
    {
        $content = "<?php\n// Configuration générée par l'installeur FFS2play — contient des secrets, ne pas versionner.\nreturn "
            . var_export($values, true) . ";\n";
        if (!is_dir(dirname($file)) && !mkdir(dirname($file), 0750, true)) {
            throw new \RuntimeException('Impossible de créer le dossier ' . dirname($file));
        }
        if (file_put_contents($file, $content, LOCK_EX) === false) {
            throw new \RuntimeException("Impossible d'écrire $file");
        }
        @chmod($file, 0640);
    }
}
