<?php
/*
 * Mini framework de test sans dépendance (voir tests/run.php)
 */
declare(strict_types=1);

$GLOBALS['test_failures'] = 0;
$GLOBALS['test_count'] = 0;

// toute alerte PHP est une erreur de test (sauf alertes masquées par @)
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function check(bool $condition, string $label): void
{
    $GLOBALS['test_count']++;
    if ($condition) {
        echo "  ok   $label\n";
        return;
    }
    $GLOBALS['test_failures']++;
    echo "  FAIL $label\n";
}

function check_equal(mixed $expected, mixed $actual, string $label): void
{
    check($expected === $actual, $label . ($expected === $actual ? '' : ' (attendu ' . var_export($expected, true) . ', obtenu ' . var_export($actual, true) . ')'));
}

function temp_dir(string $prefix): string
{
    $dir = sys_get_temp_dir() . '/' . $prefix . bin2hex(random_bytes(4));
    mkdir($dir, 0700, true);
    return $dir;
}

function remove_dir(string $dir): void
{
    foreach (glob($dir . '/{,.}[!.,!..]*', GLOB_BRACE) ?: [] as $file) {
        is_dir($file) ? remove_dir($file) : unlink($file);
    }
    @rmdir($dir);
}

/**
 * Client HTTP minimal avec cookies (session web du portail)
 */
final class Browser
{
    public array $cookies = [];
    public int $status = 0;
    public string $location = '';
    public string $body = '';

    public function __construct(private string $base, private string $source = '127.0.0.1')
    {
    }

    public function get(string $path): self
    {
        return $this->request('GET', $path);
    }

    /** POST d'un formulaire, avec le jeton CSRF de la dernière page affichée (sauf $csrf = false) */
    public function post(string $path, array $data = [], bool $csrf = true): self
    {
        if ($csrf && !isset($data['_csrf'])) {
            $data['_csrf'] = $this->csrf();
        }
        return $this->request('POST', $path, http_build_query($data), 'application/x-www-form-urlencoded');
    }

    public function raw(string $path, string $body, string $type): self
    {
        return $this->request('POST', $path, $body, $type);
    }

    public function csrf(): string
    {
        return preg_match('/name="_csrf" value="([0-9a-f]+)"/', $this->body, $m) ? $m[1] : '';
    }

    /** Suit une redirection */
    public function follow(): self
    {
        $path = $this->location;
        if (str_starts_with($path, 'http')) {
            $path = (string) parse_url($path, PHP_URL_PATH) . (parse_url($path, PHP_URL_QUERY) ? '?' . parse_url($path, PHP_URL_QUERY) : '');
        }
        return $this->get($path);
    }

    private function request(string $method, string $path, string $body = '', string $type = ''): self
    {
        $headers = ["User-Agent: FFS2Play-tests"];
        if ($this->cookies) {
            $headers[] = 'Cookie: ' . implode('; ', array_map(fn ($k, $v) => "$k=$v", array_keys($this->cookies), $this->cookies));
        }
        if ($type !== '') {
            $headers[] = "Content-Type: $type";
        }
        $context = stream_context_create([
            'http' => ['method' => $method, 'header' => implode("\r\n", $headers), 'content' => $body,
                'ignore_errors' => true, 'follow_location' => 0, 'timeout' => 20],
            'socket' => ['bindto' => $this->source . ':0'],
        ]);
        $this->body = (string) file_get_contents($this->base . $path, false, $context);
        $responseHeaders = $http_response_header ?? [];
        $this->status = isset($responseHeaders[0]) && preg_match('/\s(\d{3})\s?/', $responseHeaders[0], $m) ? (int) $m[1] : 0;
        $this->location = '';
        foreach ($responseHeaders as $header) {
            if (stripos($header, 'Location:') === 0) {
                $this->location = trim(substr($header, 9));
            }
            if (stripos($header, 'Set-Cookie:') === 0 && preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $m)) {
                $this->cookies[$m[1]] = $m[2];
            }
        }
        return $this;
    }
}

/** Dernier e-mail écrit par le transport « fichier » */
function last_mail(string $storage): string
{
    $files = glob($storage . '/mail/*.eml') ?: [];
    sort($files);
    if (!$files) {
        return '';
    }
    $raw = (string) file_get_contents(end($files));
    [$headers, $body] = explode("\r\n\r\n", $raw, 2) + ['', ''];
    return $headers . "\n\n" . base64_decode(str_replace(["\r", "\n"], '', $body));
}
