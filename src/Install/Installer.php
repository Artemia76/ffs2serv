<?php
declare(strict_types=1);

namespace Ffs2\Install;

use Ffs2\Auth\AuthService;
use Ffs2\Auth\UserRepository;
use Ffs2\Core\App;
use Ffs2\Core\Config;
use Ffs2\Core\Database;
use Ffs2\Core\Migrator;
use Ffs2\Domain\KeyManager;
use Ffs2\Domain\ServerRepository;

/**
 * Installation du portail : prérequis, configuration, clés, schéma, compte administrateur.
 * Utilisé par l'installeur web (public/install) et par bin/install.php.
 */
final class Installer
{
    /** @return array<int, array{label: string, ok: bool}> */
    public static function requirements(): array
    {
        $configDir = dirname(Config::path());
        $storage = FFS2_ROOT . '/storage';
        // les dossiers sont créés s'il le faut : c'est le test d'écriture le plus fiable
        foreach ([$configDir, $storage] as $dir) {
            if (!is_dir($dir)) {
                @mkdir($dir, 0750, true);
            }
        }
        return [
            ['label' => 'PHP 8.1 ou plus récent (actuel : ' . PHP_VERSION . ')', 'ok' => PHP_VERSION_ID >= 80100],
            ['label' => 'Extension pdo_mysql', 'ok' => extension_loaded('pdo_mysql')],
            ['label' => 'Extension openssl', 'ok' => extension_loaded('openssl')],
            ['label' => 'Extension mbstring', 'ok' => extension_loaded('mbstring')],
            ['label' => 'Extensions dom, simplexml et xmlwriter', 'ok' => extension_loaded('dom') && extension_loaded('simplexml') && extension_loaded('xmlwriter')],
            ['label' => "Dossier $configDir accessible en écriture", 'ok' => is_dir($configDir) && is_writable($configDir)],
            ['label' => "Dossier $storage accessible en écriture", 'ok' => is_dir($storage) && is_writable($storage)],
        ];
    }

    public static function requirementsMet(): bool
    {
        foreach (self::requirements() as $requirement) {
            if (!$requirement['ok']) {
                return false;
            }
        }
        return true;
    }

    /**
     * @param array{db_host: string, db_port: string|int, db_name: string, db_user: string, db_pass: string, db_prefix: string,
     *              site_name: string, base_url?: string, admin_callsign: string, admin_email: string, admin_password: string,
     *              pretty_urls?: bool, mail_transport?: ?string, storage_dir?: string} $input
     * @return string[] erreurs (vide : installation réussie)
     */
    public static function install(array $input): array
    {
        if (Config::exists()) {
            return ['Le portail est déjà installé (config/config.php existe).'];
        }
        $errors = [];
        $prefix = (string) $input['db_prefix'];
        if (!preg_match('/^[a-z][a-z0-9_]{0,15}$/', $prefix)) {
            $errors[] = 'Préfixe des tables invalide (lettres minuscules, chiffres et _, 16 caractères maximum).';
        }
        if (!UserRepository::isValidCallsign((string) $input['admin_callsign'])) {
            $errors[] = "Indicatif de l'administrateur invalide (2 à 32 caractères : lettres, chiffres, _ - . é è à).";
        }
        if (filter_var($input['admin_email'], FILTER_VALIDATE_EMAIL) === false) {
            $errors[] = "Adresse e-mail de l'administrateur invalide.";
        }
        if (mb_strlen((string) $input['admin_password']) < AuthService::MIN_PASSWORD_LENGTH) {
            $errors[] = 'Le mot de passe administrateur doit contenir au moins ' . AuthService::MIN_PASSWORD_LENGTH . ' caractères.';
        }
        if (trim((string) $input['site_name']) === '') {
            $errors[] = 'Le nom du site est obligatoire.';
        }
        if ($errors) {
            return $errors;
        }

        $dbConfig = [
            'host' => trim((string) $input['db_host']) ?: 'localhost',
            'port' => (int) ($input['db_port'] ?: 3306),
            'name' => trim((string) $input['db_name']),
            'user' => trim((string) $input['db_user']),
            'pass' => (string) $input['db_pass'],
            'prefix' => $prefix,
        ];
        try {
            $db = new Database($dbConfig);
            $version = (string) $db->fetchValue('SELECT VERSION()');
        } catch (\PDOException $e) {
            return ['Connexion à la base de données impossible : ' . $e->getMessage()];
        }
        if ((int) $db->fetchValue("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :t", ['t' => $prefix . 'users']) > 0) {
            return ["Des tables « {$prefix}* » existent déjà dans cette base : choisissez un autre préfixe ou videz la base."];
        }

        $configFile = Config::path();
        $values = [
            'db' => $dbConfig,
            'app_key' => base64_encode(random_bytes(32)),
            'base_url' => rtrim(trim((string) ($input['base_url'] ?? '')), '/'),
            'pretty_urls' => (bool) ($input['pretty_urls'] ?? true),
            'debug' => false,
        ];
        if (!empty($input['mail_transport'])) {
            $values['mail_transport'] = $input['mail_transport'];
        }
        $storage = !empty($input['storage_dir']) ? (string) $input['storage_dir'] : FFS2_ROOT . '/storage';
        if (!empty($input['storage_dir'])) {
            $values['paths'] = ['storage' => $storage];
        }

        try {
            (new Migrator($db))->migrate();

            $siteName = mb_substr(trim((string) $input['site_name']), 0, 64);
            $keys = new KeyManager(dirname($configFile) . '/keys');
            if (!$keys->exists()) {
                $keys->generate($siteName);
            }

            $users = new UserRepository($db);
            $adminId = $users->create((string) $input['admin_callsign'], (string) $input['admin_email'], (string) $input['admin_password'], 'active', 'admin');
            $servers = new ServerRepository($db);
            $servers->create('Public', 'Serveur ouvert à tous les pilotes', false, $adminId, true);

            $db->execute('INSERT INTO {settings} (name, value) VALUES (:name, :value)', ['name' => 'site_name', 'value' => $siteName]);
            // sans transport d'e-mail sûr, les comptes sont actifs dès l'inscription (réglable ensuite)
            if (empty($input['mail_transport']) && !function_exists('mail')) {
                $db->execute('INSERT INTO {settings} (name, value) VALUES (:name, :value)', ['name' => 'require_email_verification', 'value' => '0']);
            }

            // la configuration est écrite en dernier : elle verrouille l'installeur
            Config::write($configFile, $values);
            self::protectDirectory(dirname($configFile));
            self::protectDirectory($storage);
        } catch (\Throwable $e) {
            return ["Installation interrompue (MariaDB/MySQL $version) : " . $e->getMessage()];
        }
        App::boot(Config::load());
        return [];
    }

    /** Dossier non servi par le web (Apache) et non listé */
    private static function protectDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0750, true);
        }
        if (is_dir($dir) && !is_file($dir . '/.htaccess')) {
            @file_put_contents($dir . '/.htaccess', "Require all denied\n");
        }
    }
}
