<?php
/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 11/02/2016
 * Time: 13:02
 */

/**
 * calcul le temps en seconde entre 2 évenement
 *
 * @param STRING $timeS1
 * @param STRING $timeS2
 *
 * @return Int le temps en seconde
 *
 */
function delay_time($timeS1, $timeS2)
{
	$track = explode("]", $timeS1);
	$begin = substr($track[0], 1);
	$begin = explode(":", $begin);

	$track = explode("]", $timeS2);
	$end = substr($track[0], 1);
	$end = explode(":", $end);

	$beg = intval($begin[0] * 3600 + $begin[1] * 60 + $begin[2]);

	$end = intval($end[0] * 3600 + $end[1] * 60 + $end[2]);
	if ($end < $beg) {
		$end += 3600 * 24;
	}

	return $timer = $end - $beg;
}

/**
 * Crypte des données selon la péthode AES
 *
 * @param STRING $text , contenu à crypter
 * @param STRING $pkey , clé de crypage à 32 caractéres
 *
 * @return STRING contenu crypté avec la clé secondaire de décryptage confondu dans le contenu
 *
 */
/*
function encrypt($text, $pkey)
{
	$key = $pkey;
	$IV = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_CBC), MCRYPT_RAND);

	return base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $key, $text, MCRYPT_MODE_CBC, $IV) . "-[--IV-[-" . $IV);
}
*/
/**
 * Dérypte des données selon la péthode AES
 *
 * @param STRING $text , contenu à décrypter avec la clé secondaire de décryptage confondu dans le contenu
 * @param STRING $pkey , clé de décrypage à 32 caractéres
 *
 * @return STRING contenu décrypté
 *
 */
/*
function decrypt($text, $pkey)
{
	$key = $pkey;
	$text = base64_decode($text);
	$IV = substr($text, strrpos($text, "-[--IV-[-") + 9);
	$text = str_replace("-[--IV-[-" . $IV, "", $text);

	return rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $key, $text, MCRYPT_MODE_CBC, $IV), "\0");
}
*/

/**
 * Compile les données de vol et d'appareil
 *
 * @param ARRAY $param , tableau contenant les réponses à transmettre
 *
 * @return XML, transmet un fichier XML
 *
 */
/*
function sendXML($params)
{
	$xml = new SimpleXMLElement("<?xml version='1.0' encoding='UTF-8'?><sitedata  />");

	$info_xml = $xml->addChild('info');
	foreach ($params as $name => $value) {
		if (($name == 'erreur' || $name == 'erreur0') and $value != '') {
			$info_xml->addChild('erreur', $value);
			$info_xml->erreur->addAttribute('error', '0');
		} elseif ($name == 'erreur1' and $value != '') {
			if (isset($info_xml->erreur)) {
				$info_xml->addChild('test', $value);
			} else {
				$info_xml->addChild('erreur', $value);
				$info_xml->erreur->addAttribute('error', '1');
			}

		} else if ($name == 'erreur2' and $value != '') {
			if (isset($info_xml->erreur)) {
				$info_xml->addChild('test', $value);
			} else {
				$info_xml->addChild('erreur', $value);
				$info_xml->erreur->addAttribute('error', '2');
			}
		} elseif ($name == 'depICAO' or $name == 'arrICAO') {
			if (is_object($value) === TRUE) {
				$icao = $value->icao;
				$info_xml->addChild($name, $icao);
				$info_xml->$name->addAttribute("lat", $value->lat);
				$info_xml->$name->addAttribute("lon", $value->lng);
			} else {
				$info_xml->addChild($name, $value);
			}
		} elseif ($name == 'version') {
			$info_xml->addChild('version', '');
			$version = explode('_', $value);
			$info_xml->$name->addAttribute("Major", $version[1]);
			$info_xml->$name->addAttribute("Minor", $version[2]);
			$info_xml->$name->addAttribute("Build", $version[3]);
		} elseif ($name == 'whazzup') {
			$info_xml->addChild('whazzup');
			if ($value != NULL and $value != '') {
				foreach ($value as $key2 => $value2) {
					$info_xml->whazzup->addChild($value2["name"]);
					$info_xml->whazzup->$value2["name"]->addAttribute("ip", $value2["ip"]);
					$info_xml->whazzup->$value2["name"]->addAttribute("port", $value2["port"]);
					$info_xml->whazzup->$value2["name"]->addAttribute("online", $value2["online"]);
					$info_xml->whazzup->$value2["name"]->addAttribute("level", $value2["level"]);
					$info_xml->whazzup->$value2["name"]->addAttribute("statut", $value2["statut"]);
				}
			}

		} else {
			$info_xml->addChild($name, $value);
		}
	}

	$xml_string = $xml->asXML();
	echo $xml_string;

	return;
}



/**
 * Crypte des données selon la péthode AES
 *
 * @param STRING $text , contenu à crypter
 * @param STRING $pkey , clé de crypage à 32 caractéres
 *
 * @return STRING contenu crypté avec la clé secondaire de décryptage confondu dans le contenu
 *
 */
/*
function xml_extract($text)
{
	//$text = file_get_contents('php://input');
	$encoding = mb_detect_encoding($text);
	$rec_xml = trim(iconv($encoding, "UTF-8", $text));

	return simplexml_load_string($rec_xml);
}
*/
function Get_FFST_Files ($folder, $ext = array ('txt'), $recursif = true)
{

	$files = array ();
	$dir = opendir ($folder);
	while ($file = readdir ($dir))
	{
		if ($file == '.' || $file == '..')
		{
			continue;
		}
		if (is_dir ($folder . '/' . $file))
		{
			if ($recursif == true)
			{
				$files = array_merge ($files, Get_FFST_Files ($folder . '/' . $file, $ext));
			}
		}
		else
		{
			foreach ($ext as $v)
			{
				if (strtolower ($v) == strtolower (substr ($file, - strlen ($v))))
				{
					$files[] = $folder . '/' . $file;
					break;
				}
			}
		}
	}
	closedir ($dir);
	return $files;
}
function clean_FFST_files($url,$to_del)
{
	$files=Get_FFST_Files ($url, array ("zip"), TRUE);
	foreach($files as $file)
	{
		if(strpos ($file, $to_del)>0)
		{
			$file = str_replace ($url, "", $file);
			$file = str_replace ($to_del, "", $file);
			$file = str_replace (".zip", "", $file);
			return $file;
		}
	}
}

function extract_FFST_ver($files)
{
	$ver=explode('_', $files);
	$version['major']=$ver[0];
	$version['minor']=$ver[1];
	$version['build']=$ver[2];
	return $version;
}
?>