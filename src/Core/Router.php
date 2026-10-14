<?php
declare(strict_types=1);

namespace Ffs2\Core;

/**
 * Routeur minimal : routes « GET /profil/{callsign} » vers un callable.
 * Fonctionne avec la réécriture d'URL (.htaccess, nginx try_files) ou sans (index.php/chemin).
 */
final class Router
{
    /** @var array<int, array{method: string, regex: string, handler: callable}> */
    private array $routes = [];
    private string $basePath;

    public function __construct(private bool $prettyUrls = true)
    {
        // dossier d'installation (le portail peut être dans un sous-dossier)
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
        $dir = rtrim(dirname($script), '/');
        if (str_ends_with($dir, '/install')) {
            $dir = substr($dir, 0, -8);
        }
        // site servi depuis la racine du dépôt : le .htaccess racine réécrit tout vers public/
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        if (str_ends_with($dir, '/public') && !str_starts_with($uri, $dir . '/') && $uri !== $dir) {
            $dir = substr($dir, 0, -7);
        }
        $this->basePath = $dir === '.' ? '' : $dir;
    }

    public function basePath(): string
    {
        return $this->basePath;
    }

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, callable $handler): void
    {
        $regex = '#^' . preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', rtrim($pattern, '/') ?: '/') . '$#u';
        $this->routes[] = ['method' => $method, 'regex' => $regex, 'handler' => $handler];
    }

    /** Chemin demandé, relatif au dossier d'installation */
    public function currentPath(): string
    {
        if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO'] !== '') {
            $path = (string) $_SERVER['PATH_INFO'];
        } else {
            $path = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
            if ($this->basePath !== '' && str_starts_with($path, $this->basePath)) {
                $path = substr($path, strlen($this->basePath));
            }
            if (str_starts_with($path, '/index.php')) {
                $path = substr($path, 10);
            }
        }
        $path = '/' . trim(rawurldecode($path), '/');
        return $path;
    }

    /** @return bool false si aucune route ne correspond */
    public function dispatch(string $method, string $path): bool
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed = true;
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
            ($route['handler'])(...$params);
            return true;
        }
        if ($allowed) {
            http_response_code(405);
        }
        return false;
    }

    public function url(string $path = '/', array $query = []): string
    {
        $path = '/' . ltrim($path, '/');
        $prefix = $this->basePath . ($this->prettyUrls || $path === '/' ? '' : '/index.php');
        $url = $prefix . ($path === '/' && $prefix !== '' ? '/' : $path);
        return $query ? $url . '?' . http_build_query($query) : $url;
    }
}
