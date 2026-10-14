<?php
if (!defined ('PROTECTIONINCLUDE')) {
	die('Accès Interdit');
}

/*
 * Cryptographie partagée avec le client FFS2play (tools/crypto.cpp)
 *
 * - le client génère une clé AES-256 aléatoire et la chiffre en RSA PKCS#1 v1.5
 *   avec le certificat que le serveur lui envoie en réponse au "hello"
 * - les données sensibles (mot de passe, IP des joueurs) sont ensuite chiffrées en
 *   AES-256-CFB128 au format : base64( chiffré . "-[--IV-[-" . IV de 16 octets )
 *
 * Seule l'extension openssl de PHP est utilisée (phpseclib et mcrypt ne sont plus nécessaires).
 */

define ('AES_METHOD', 'aes-256-cfb');
define ('AES_IV_SIZE', 16);
define ('AES_IV_SEPARATOR', '-[--IV-[-');

/**
 * Décrypte des données chiffrées en RSA PKCS#1 v1.5 avec la clé privée du serveur
 *
 * @param STRING $text , contenu à décrypter
 * @param STRING $key , clé privée RSA au format PEM
 * @param STRING $IB64 , pré traitement de l'entrée avec la base 64: E=Encode D=decode N=None
 * @param STRING $OB64 , post traitement de la sortie avec la base 64: E=Encode D=decode N=None
 *
 * @return STRING contenu décrypté, false en cas d'échec
 */
function RSA_decrypt($text, $key, $IB64 = "N", $OB64 = "N")
{
	$text = strval ($text);
	if ($IB64 === "D") {
		$text = base64_decode ($text, TRUE);
	}
	elseif ($IB64 === "E") {
		$text = base64_encode ($text);
	}
	elseif ($IB64 !== "N") {
		return false;
	}
	if ($text === false or $text === '') {
		return false;
	}

	$private_key = openssl_pkey_get_private ($key);
	if ($private_key === false) {
		return false;
	}
	if (!openssl_private_decrypt ($text, $result, $private_key, OPENSSL_PKCS1_PADDING)) {
		return false;
	}

	if ($OB64 === "N") {
		return $result;
	}
	elseif ($OB64 === "D") {
		return base64_decode ($result);
	}
	elseif ($OB64 === "E") {
		return base64_encode ($result);
	}
	return false;
}

/**
 * Crypte des données selon la méthode AES-256-CFB
 *
 * @param STRING $text , contenu à crypter
 * @param STRING $pkey , clé de crypage de 32 octets
 * @param BOOL $new_gen , conservé pour compatibilité : seul le cryptage FFS2play (true) est supporté
 *
 * @return STRING contenu crypté avec le vecteur d'initialisation confondu dans le contenu, false en cas d'échec
 */
function encrypt($text, $pkey, $new_gen = true)
{
	if ($new_gen !== true or strlen ($pkey) !== 32) {
		return false;
	}
	$IV = random_bytes (AES_IV_SIZE);
	$cipher = openssl_encrypt (strval ($text), AES_METHOD, $pkey, OPENSSL_RAW_DATA, $IV);
	if ($cipher === false) {
		return false;
	}
	return base64_encode ($cipher . AES_IV_SEPARATOR . $IV);
}

/**
 * Décrypte des données selon la méthode AES-256-CFB
 *
 * @param STRING $text , contenu à décrypter avec le vecteur d'initialisation confondu dans le contenu
 * @param STRING $pkey , clé de décrypage de 32 octets
 * @param BOOL $new_gen , conservé pour compatibilité : seul le cryptage FFS2play (true) est supporté
 *
 * @return STRING contenu décrypté, false en cas d'échec
 */
function decrypt($text, $pkey, $new_gen = true)
{
	if ($new_gen !== true or strlen ($pkey) !== 32) {
		return false;
	}
	$data = base64_decode (strval ($text), TRUE);
	if ($data === false) {
		return false;
	}
	// le vecteur a une taille fixe et se trouve à la fin : le contenu crypté peut contenir n'importe quel octet
	$pos = strlen ($data) - AES_IV_SIZE - strlen (AES_IV_SEPARATOR);
	if ($pos < 0 or substr ($data, $pos, strlen (AES_IV_SEPARATOR)) !== AES_IV_SEPARATOR) {
		return false;
	}
	$IV = substr ($data, -AES_IV_SIZE);
	return openssl_decrypt (substr ($data, 0, $pos), AES_METHOD, $pkey, OPENSSL_RAW_DATA, $IV);
}
?>
