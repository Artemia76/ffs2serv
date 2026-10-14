<?php
/*
 * Compatibilité de class/crypto.php avec le client FFS2play (tools/crypto.cpp, OpenSSL EVP)
 */
require_once dirname (__DIR__) . '/class/crypto.php';

echo "crypto\n";

// Vecteur de référence produit par la ligne de commande OpenSSL (même EVP_aes_256_cfb128 que le client) :
// printf 'Mot de passe éèà 192.168.1.10' | openssl enc -aes-256-cfb -K 0001..1f -iv f0e1..0f
$key = hex2bin ('000102030405060708090a0b0c0d0e0f101112131415161718191a1b1c1d1e1f');
$iv = hex2bin ('f0e1d2c3b4a5968778695a4b3c2d1e0f');
$plain = 'Mot de passe éèà 192.168.1.10';
$client_message = base64_encode (hex2bin ('43f04c39119f61d58db624866173f3ca167e5f5ada4e7489ab489bd2e0ee4a94') . '-[--IV-[-' . $iv);
check_equal ($plain, decrypt ($client_message, $key, TRUE), 'décryptage d\'un message au format du client');

// Aller-retour, y compris avec des octets nuls et le séparateur dans le contenu
foreach (array ('', '127.0.0.1', "\0\0binaire\0-[--IV-[-\xff", str_repeat ('0123456789abcdef', 64)) as $i => $text) {
	$ok = TRUE;
	for ($n = 0; $n < 20; $n++) {
		$ok = $ok && decrypt (encrypt ($text, $key, TRUE), $key, TRUE) === $text;
	}
	check ($ok, "aller-retour AES #$i");
}

// Le format produit par encrypt() est celui attendu par le client : chiffré . séparateur . IV de 16 octets
$raw = base64_decode (encrypt ('192.168.1.10', $key, TRUE));
check_equal (12 + 9 + 16, strlen ($raw), 'taille du message crypté');
check_equal ('-[--IV-[-', substr ($raw, 12, 9), 'position du séparateur');

// Entrées invalides
check_equal (false, decrypt ('', $key, TRUE), 'message vide refusé');
check_equal (false, decrypt ('bm90IGVuY3J5cHRlZA==', $key, TRUE), 'message sans IV refusé');
check_equal (false, decrypt ('###', $key, TRUE), 'base64 invalide refusé');
check_equal (false, decrypt ($client_message, 'courte', TRUE), 'clé de mauvaise taille refusée');
check_equal (false, encrypt ('x', $key, FALSE), 'ancien cryptage mcrypt non supporté');

// RSA : le client crypte sa clé AES de 32 octets en PKCS#1 v1.5 avec le certificat du serveur
list ($cert_pem, $key_pem) = make_rsa_pair ();
$aes_key = random_bytes (32);
openssl_public_encrypt ($aes_key, $encrypted, openssl_pkey_get_public ($cert_pem), OPENSSL_PKCS1_PADDING);
check_equal ($aes_key, RSA_decrypt (base64_encode ($encrypted), $key_pem, 'D', 'N'), 'décryptage RSA de la clé AES');
check_equal (base64_encode ($aes_key), RSA_decrypt (base64_encode ($encrypted), $key_pem, 'D', 'E'), 'décryptage RSA avec sortie base64');
check_equal (false, RSA_decrypt ('', $key_pem, 'D', 'N'), 'RSA : entrée vide refusée');
check_equal (false, RSA_decrypt (base64_encode ($encrypted), 'pas une clé', 'D', 'N'), 'RSA : clé invalide refusée');
?>
