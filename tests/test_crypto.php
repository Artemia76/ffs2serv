<?php
/*
 * Compatibilité de src/Client/Crypto.php avec le client FFS2play (tools/crypto.cpp, OpenSSL EVP)
 */
use Ffs2\Client\Crypto;

echo "crypto\n";

// Vecteur de référence produit par la ligne de commande OpenSSL (même EVP_aes_256_cfb128 que le client) :
// printf 'Mot de passe éèà 192.168.1.10' | openssl enc -aes-256-cfb -K 0001..1f -iv f0e1..0f
$key = hex2bin('000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f');
$iv = hex2bin('f0e1d2c3b4a5968778695a4b3c2d1e0f');
$clientMessage = base64_encode(hex2bin('43f04c39119f61d58db624866173f3ca167e5f5ada4e7489ab489bd2e0ee4a94') . '-[--IV-[-' . $iv);
check_equal('Mot de passe éèà 192.168.1.10', Crypto::decrypt($clientMessage, $key), "déchiffrement d'un message au format du client");

$ok = true;
foreach (['', '127.0.0.1', "\0\0binaire\0-[--IV-[-\xff", str_repeat('0123456789abcdef', 64)] as $text) {
    for ($n = 0; $n < 10; $n++) {
        $ok = $ok && Crypto::decrypt((string) Crypto::encrypt($text, $key), $key) === $text;
    }
}
check($ok, 'aller-retour AES (octets nuls, séparateur dans le contenu, message long)');
$raw = base64_decode((string) Crypto::encrypt('192.168.1.10', $key));
check_equal('-[--IV-[-', substr($raw, 12, 9), 'format chiffré . séparateur . IV');
check_equal(null, Crypto::decrypt('bm90IGVuY3J5cHRlZA==', $key), 'message sans IV refusé');
check_equal(null, Crypto::decrypt($clientMessage, 'courte'), 'clé de mauvaise taille refusée');

// RSA : le client chiffre sa clé AES en PKCS#1 v1.5 avec le certificat du serveur
$keys = $app->keys();
$aesKey = random_bytes(32);
openssl_public_encrypt($aesKey, $encrypted, openssl_pkey_get_public($keys->certificate()), OPENSSL_PKCS1_PADDING);
check_equal($aesKey, Crypto::rsaDecrypt(base64_encode($encrypted), $keys->privateKey()), 'déchiffrement RSA de la clé AES du client');
check_equal(null, Crypto::rsaDecrypt('', $keys->privateKey()), 'RSA : entrée vide refusée');
check($keys->expiresAt() !== null && strtotime($keys->expiresAt() . ' UTC') > time() + 9 * 365 * 86400, 'certificat généré pour 10 ans');

// stockage des clés des clients en base
$appKey = $app->appKey();
check_equal($aesKey, Crypto::unseal(Crypto::seal($aesKey, $appKey), $appKey), 'chiffrement authentifié des clés stockées');
$sealed = base64_decode(Crypto::seal($aesKey, $appKey));
$sealed[30] = chr(ord($sealed[30]) ^ 1);
check_equal(null, Crypto::unseal(base64_encode($sealed), $appKey), 'clé stockée altérée détectée');
