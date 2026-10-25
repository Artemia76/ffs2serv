<?php
declare(strict_types=1);

namespace Ffs2\Client;

/**
 * Cryptographie partagée avec le client FFS2play (tools/crypto.cpp) :
 *  - le client génère une clé AES-256 et la chiffre en RSA PKCS#1 v1.5 avec le certificat reçu au « hello »
 *  - mot de passe et IP des joueurs sont chiffrés en AES-256-CFB128 au format
 *    base64( chiffré . "-[--IV-[-" . IV de 16 octets )
 * Plus un chiffrement authentifié (AES-256-GCM) pour stocker les clés des clients en base.
 */
final class Crypto
{
    private const AES_METHOD = 'aes-256-cfb';
    private const IV_SIZE = 16;
    private const IV_SEPARATOR = '-[--IV-[-';

    /** Décrypte (RSA PKCS#1 v1.5, clé privée PEM) une donnée en base64 ; null en cas d'échec */
    public static function rsaDecrypt(string $base64, string $privateKeyPem): ?string
    {
        $data = base64_decode($base64, true);
        if ($data === false || $data === '') {
            return null;
        }
        $key = openssl_pkey_get_private($privateKeyPem);
        if ($key === false || !openssl_private_decrypt($data, $result, $key, OPENSSL_PKCS1_PADDING)) {
            return null;
        }
        return $result;
    }

    /** Chiffrement au format du client */
    public static function encrypt(string $text, string $key): ?string
    {
        if (strlen($key) !== 32) {
            return null;
        }
        $iv = random_bytes(self::IV_SIZE);
        $cipher = openssl_encrypt($text, self::AES_METHOD, $key, OPENSSL_RAW_DATA, $iv);
        return $cipher === false ? null : base64_encode($cipher . self::IV_SEPARATOR . $iv);
    }

    /** Déchiffrement au format du client ; null en cas d'échec */
    public static function decrypt(string $base64, string $key): ?string
    {
        if (strlen($key) !== 32) {
            return null;
        }
        $data = base64_decode($base64, true);
        if ($data === false) {
            return null;
        }
        // l'IV a une taille fixe et se trouve à la fin : le chiffré peut contenir n'importe quel octet
        $pos = strlen($data) - self::IV_SIZE - strlen(self::IV_SEPARATOR);
        if ($pos < 0 || substr($data, $pos, strlen(self::IV_SEPARATOR)) !== self::IV_SEPARATOR) {
            return null;
        }
        $plain = openssl_decrypt(substr($data, 0, $pos), self::AES_METHOD, $key, OPENSSL_RAW_DATA, substr($data, -self::IV_SIZE));
        return $plain === false ? null : $plain;
    }

    /** Chiffrement authentifié pour le stockage (clé applicative) */
    public static function seal(string $plain, string $appKey): string
    {
        $iv = random_bytes(12);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $appKey, OPENSSL_RAW_DATA, $iv, $tag);
        return base64_encode($iv . $tag . $cipher);
    }

    public static function unseal(string $sealed, string $appKey): ?string
    {
        $data = base64_decode($sealed, true);
        if ($data === false || strlen($data) < 28) {
            return null;
        }
        $plain = openssl_decrypt(substr($data, 28), 'aes-256-gcm', $appKey, OPENSSL_RAW_DATA, substr($data, 0, 12), substr($data, 12, 16));
        return $plain === false ? null : $plain;
    }
}
