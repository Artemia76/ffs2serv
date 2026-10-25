<?php
declare(strict_types=1);

namespace Ffs2\Core;

use Ffs2\Auth\Auth;
use Ffs2\Auth\UserRepository;
use Ffs2\Domain\KeyManager;
use Ffs2\Domain\ReleaseCatalog;

/**
 * Conteneur de l'application : services créés à la demande, partagés pour la requête.
 */
final class App
{
    private static ?self $instance = null;

    private ?Database $db = null;
    private ?Settings $settings = null;
    private ?Session $session = null;
    private ?Csrf $csrf = null;
    private ?Router $router = null;
    private ?View $view = null;
    private ?Mailer $mailer = null;
    private ?Auth $auth = null;
    private ?KeyManager $keys = null;

    private function __construct(private Config $config)
    {
    }

    public static function boot(?Config $config = null): self
    {
        self::$instance = new self($config ?? Config::load());
        return self::$instance;
    }

    public static function get(): self
    {
        return self::$instance ?? self::boot();
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function installed(): bool
    {
        return $this->config->get('db') !== null;
    }

    public function db(): Database
    {
        return $this->db ??= new Database((array) $this->config->get('db', []));
    }

    public function settings(): Settings
    {
        return $this->settings ??= new Settings($this->installed() ? $this->db() : null);
    }

    public function session(): Session
    {
        return $this->session ??= new Session();
    }

    public function csrf(): Csrf
    {
        return $this->csrf ??= new Csrf($this->session());
    }

    public function router(): Router
    {
        return $this->router ??= new Router((bool) $this->config->get('pretty_urls', true));
    }

    public function view(): View
    {
        return $this->view ??= new View();
    }

    public function mailer(): Mailer
    {
        return $this->mailer ??= new Mailer($this->settings(), $this->config->storageDir(), $this->config->get('mail_transport'));
    }

    public function limiter(): RateLimiter
    {
        return new RateLimiter($this->db());
    }

    public function users(): UserRepository
    {
        return new UserRepository($this->db());
    }

    public function auth(): Auth
    {
        return $this->auth ??= new Auth($this->session(), fn () => $this->users());
    }

    public function keys(): KeyManager
    {
        return $this->keys ??= new KeyManager($this->config->keysDir());
    }

    /** Releases GitHub des logiciels (mises à jour, page de téléchargement) */
    public function releases(): ReleaseCatalog
    {
        return new ReleaseCatalog(
            $this->config->storageDir() . '/cache',
            (string) $this->config->get('github.token', ''),
            (string) $this->config->get('github.api', 'https://api.github.com')
        );
    }

    /** Clé applicative (32 octets) : chiffrement des clés AES des clients stockées en base */
    public function appKey(): string
    {
        $key = base64_decode((string) $this->config->get('app_key', ''), true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('Clé applicative absente ou invalide dans la configuration');
        }
        return $key;
    }

    /** URL absolue de la racine du portail (aide de connexion ffs2play) */
    public function baseUrl(): string
    {
        $configured = (string) $this->config->get('base_url', '');
        return $configured !== '' ? rtrim($configured, '/') : Request::origin() . $this->router()->basePath();
    }

    /** URL absolue d'une page (liens envoyés par e-mail) */
    public function absoluteUrl(string $path, array $query = []): string
    {
        $relative = $this->router()->url($path, $query);
        $basePath = $this->router()->basePath();
        if ($basePath !== '' && str_starts_with($relative, $basePath)) {
            $relative = substr($relative, strlen($basePath));
        }
        return $this->baseUrl() . $relative;
    }
}
