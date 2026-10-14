<?php
declare(strict_types=1);

namespace Ffs2\Domain;

/**
 * Paire RSA du serveur : le certificat est envoyé aux clients (« hello »), qui l'utilisent pour
 * chiffrer leur clé AES. Les clients le récupèrent à chaque connexion : régénérer la paire
 * ne nécessite aucune mise à jour côté client.
 */
final class KeyManager
{
    public function __construct(private string $dir)
    {
    }

    public function exists(): bool
    {
        return is_file($this->dir . '/server.key') && is_file($this->dir . '/server.crt');
    }

    public function certificate(): string
    {
        return $this->read('server.crt');
    }

    public function privateKey(): string
    {
        return $this->read('server.key');
    }

    /** Date d'expiration du certificat (UTC, Y-m-d H:i:s) */
    public function expiresAt(): ?string
    {
        if (!$this->exists()) {
            return null;
        }
        $info = openssl_x509_parse($this->certificate());
        return $info ? gmdate('Y-m-d H:i:s', (int) $info['validTo_time_t']) : null;
    }

    /** Génère une nouvelle paire RSA 2048 bits et un certificat auto-signé */
    public function generate(string $commonName = 'FFS2play', int $days = 3650): void
    {
        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        if ($key === false) {
            throw new \RuntimeException('Génération de la clé RSA impossible : ' . openssl_error_string());
        }
        $csr = openssl_csr_new(['commonName' => mb_substr($commonName, 0, 64), 'organizationName' => 'FFS2play'], $key, ['digest_alg' => 'sha256']);
        $cert = $csr === false ? false : openssl_csr_sign($csr, null, $key, $days, ['digest_alg' => 'sha256'], random_int(1, PHP_INT_MAX));
        if ($cert === false || !openssl_x509_export($cert, $certPem) || !openssl_pkey_export($key, $keyPem)) {
            throw new \RuntimeException('Génération du certificat impossible : ' . openssl_error_string());
        }
        if (!is_dir($this->dir) && !mkdir($this->dir, 0750, true) && !is_dir($this->dir)) {
            throw new \RuntimeException('Impossible de créer ' . $this->dir);
        }
        file_put_contents($this->dir . '/server.key', $keyPem, LOCK_EX);
        file_put_contents($this->dir . '/server.crt', $certPem, LOCK_EX);
        @chmod($this->dir . '/server.key', 0640);
    }

    private function read(string $file): string
    {
        $content = @file_get_contents($this->dir . '/' . $file);
        if ($content === false) {
            throw new \RuntimeException("Clé du serveur absente : {$this->dir}/$file");
        }
        return $content;
    }
}
