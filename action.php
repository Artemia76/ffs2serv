<?php
	define ('PROTECTIONINCLUDE','TRUE');

	//répertoire de stockage des slots de connexion (créé automatiquement)
	//et fichier de configuration, modifiables par variables d'environnement (tests)
	define ('SESSION_DIR', getenv ('FFS2SERV_SESSION_DIR') ?: __DIR__ . '/nodb/Session_mp');
	define ('CONFIG_FILE', getenv ('FFS2SERV_CONFIG') ?: __DIR__ . '/config/config_gen.php');

try
{
	//tentative de chargement de la configuration
	if (!@include_once(CONFIG_FILE)) throw new Exception ("Votre fichier de configuration est introuvable (générez-le avec : php tools/generate_config.php)");

	if (!defined ('debug_ffst')) {
		define ('debug_ffst', FALSE);
	}

	if (!extension_loaded ('openssl')) throw new Exception ("L'extension PHP openssl est requise");

	//tentative de chargement du protocole xml
	if (!@include_once(__DIR__ . "/class/xml.php")) throw new Exception ("Votre systéme XML est introuvable");

	//tentative de chargement des protocoles de cryptage
	if (!@include_once(__DIR__ . "/class/crypto.php")) throw new Exception ("Le systéme de cryptage simplifié est introuvable");

	//tentative de chargement des logs
	if (!@include_once(__DIR__ . "/class/erreur_log.php")) throw new Exception ("Le systéme d'inter-com est introuvable");

	//tentative de chargement des sessions
	if (!@include_once(__DIR__ . "/class/Session_mp.php")) throw new Exception ("Le systéme de sessions est introuvable");

	//création du répertoire des sessions
	if (!is_dir (SESSION_DIR) and !@mkdir (SESSION_DIR, 0700, TRUE)) throw new Exception ("Impossible de créer le répertoire des sessions " . SESSION_DIR);
}
catch (Exception $e)
{
	//en cas de soucis on affiche l'origine du dysfonctionnement
	echo $e->getMessage ();
	//on stop l'execution du programme
	die;
}

//initialisation de l'objet pour les messages de retour sur FFS2play
$inter_com = New Erreur();
$data_xml = array ();

date_default_timezone_set ("Europe/Brussels");

//si des données arrive par la méthode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
	//si l'ip est bien au format IP V4
	if (filter_var ($_SERVER["REMOTE_ADDR"], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false)
	{
		$data_xml["your_ip"] = $_SERVER["REMOTE_ADDR"];
		//on extrait les données XML
		$xml = xml_extract (file_get_contents ('php://input'));

		if ($xml === false)
		{
			$inter_com->addError (201, $msg); //Connexion interrompue à la demande du serveur
			$data_xml['loginStatus'] = '0';
		}
		else
		{
			//version envoyée par le client : ffs2play_<major>_<minor>_<build>
			//seuls les clients FFS2play 2 et suivants (cryptage AES-256-CFB) sont supportés
			$new_gen_aes = (preg_match ('/^ffs2play_(\d+)_/i', strval ($xml->version), $client_version) === 1
				&& intval ($client_version[1]) >= 2);

			$switch = strtolower (strval ($xml->switch->data));

			if (!$new_gen_aes and in_array ($switch, array ('verify', 'liveupdate', 'logout')))
			{
				$inter_com->addMessage (202, $msg); //Votre version n'est plus supportée
				$data_xml['loginStatus'] = '0';
			}
			// si le client fait une demande de cryptage dynamique
			elseif ($switch == "hello")
			{
				require __DIR__ . "/multi/hello_world.php";
			}
			// si le client fait une demande d'authentification
			elseif ($switch == "verify")
			{
				require __DIR__ . "/multi/verify.php";
			}
			// si le client fait une demande de mise à jours
			elseif ($switch == "liveupdate")
			{
				require __DIR__ . "/multi/liveupdate.php";
			}
			// si le client se déconnecte
			elseif ($switch == "logout")
			{
				require __DIR__ . "/multi/logout.php";
			}
		}
	}
	else
	{
		$inter_com->addMessage (219, $msg); //Votre ip ne correspond pas aux normes IPV4.
		$inter_com->addMessage (201, $msg); //Connexion interrompue...
		$inter_com->addMessage (2, $msg); //Bonne fin de journée
		$data_xml['loginStatus'] = '0'; //on refuse la connexion
	}
}

//Rajout des messages dans le XML
if ($inter_com->getMessage () != NULL)
{
	$data_xml["erreur"] = $inter_com->getMessage ();
}
if ($inter_com->getWarning () != NULL)
{
	$data_xml["erreur1"] = $inter_com->getWarning ();
}
if ($inter_com->getError () != NULL)
{
	$data_xml["erreur2"] = $inter_com->getError ();
}
if ($inter_com->getDebug () != NULL)
{
	$data_xml["erreur3"] = $inter_com->getDebug ();
}

header ('Content-Type: text/xml; charset=utf-8');
$data2 = pre_xml ($data_xml);
$xml   = new Array2XML();
print $xml->buildXML ($data2);
?>
