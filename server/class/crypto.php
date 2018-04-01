<?php 
function create_rand_key($length) {
	$string = "";
	$chaine = "abcdefghijklmnpqrstuvwxy0123456789";
	srand((double)microtime()*1000000);
	for($i=0; $i<$length; $i++) {
		$string .= $chaine[rand()%strlen($chaine)];
	}
	return $string;
}

/**
 * Décrypte des données selon la méthode AES
 *
 * @param STRING $text , contenu à décrypter
 * @param STRING $>key , clé de crypage
 * @param STRING $IB64 , indication du pré traitement avec la base 64: E=Encode D=decode N=None
 * @param STRING $OB64 , indication du pré traitement avec la base 64: E=Encode D=decode N=None
 *
 * @return STRING contenu crypté 
 *
 */
function RSA_decrypt($text, $key,$IB64="N",$OB64="N")
{
	$rsa = new \phpseclib\Crypt\RSA();
	extract($rsa->createKey());
	$rsa->setEncryptionMode(phpseclib\Crypt\RSA::ENCRYPTION_PKCS1);
	$rsa->loadKey($key);
	
	if ($IB64==="N")
	{
		$result=$rsa->decrypt($text);
	}
	elseif ($IB64==="D")
	{
		$result=$rsa->decrypt(base64_decode($text));
	}
	elseif ($IB64==="E")
	{
		$result=$rsa->decrypt(base64_encode($text));
	}
	else
	{
		return false;
	}

	if ($OB64==="N")
	{
		return $result;
	}
	elseif ($OB64==="D")
	{
		return base64_decode($result);
	}
	elseif ($OB64==="E")
	{
		return base64_encode($result);
	}
	else
	{
		return false;
	}
}





/**
 * Crypte des données selon la méthode AES
 *
 * @param STRING $text , contenu à crypter
 * @param STRING $pkey , clé de crypage à 32 caractéres
 *
 * @return STRING contenu crypté avec la clé secondaire de décryptage confondu dans le contenu
 *
 */
function encrypt($text, $pkey,$new_gen=true)
{
	if($new_gen===false)
	{
		$IV = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC), MCRYPT_RAND);

		return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $pkey, $text, MCRYPT_MODE_CBC, $IV) . "-[--IV-[-" . $IV);
	}
	elseif($new_gen===true)
	{
		$method = 'aes-256-cfb';
		$IV = openssl_random_pseudo_bytes(16);
		return  base64_encode( openssl_encrypt ($text, $method, $pkey, OPENSSL_RAW_DATA, $IV). "-[--IV-[-" . $IV);
	}
}

/**
 * Dérypte des données selon la méthode AES
 *
 * @param STRING $text , contenu à décrypter avec la clé secondaire de décryptage confondu dans le contenu
 * @param STRING $pkey , clé de décrypage à 32 caractéres
 * 
 * @return STRING contenu décrypté
 *
 */
function decrypt($text, $pkey,$new_gen=false)
{
	$text = base64_decode($text);
	$IV = substr($text, strrpos($text, "-[--IV-[-") + 9);
	$text = str_replace("-[--IV-[-" . $IV, "", $text);
	
	if($new_gen===false)
	{
		return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $pkey, $text, MCRYPT_MODE_CBC, $IV), "\0");
	}
	elseif($new_gen===true)
	{
		$method = 'aes-256-cfb';
		return  openssl_decrypt( $text, $method, $pkey,OPENSSL_RAW_DATA, $IV);
	}
}

?>