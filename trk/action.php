<?php
/**
 * Created by Sublimetxt3.
 * User: Flo
 * Date: 01/12/2016
 * Time: 7:00
 */

//activation de la protection recursive pour les éléments qui seront intégrés
define ('PROTECTIONINCLUDE', TRUE);

//intégration de fichier de configuration général du site
require_once ($_SERVER["DOCUMENT_ROOT"] . '/core/config/config_gen.php');

//intégration du gestionnaire de class site
require_once (mark_5 . '/core/app/Auto_loader.php');

//intégration des messages d'erreur ou de communication normale
require_once (mark_5 . '/core/config/message.php');


//intégration des connecteurs du forum
define ('IN_PHPBB', TRUE);
$phpbb_root_path = './../forum/';
$phpEx = substr (strrchr (__FILE__, '.'), 1);
include ($phpbb_root_path . 'common.' . $phpEx);
$request->enable_super_globals ();

$data = array();

//error_reporting(E_ALL);
//error_reporting(E_ERROR | E_WARNING | E_PARSE | E_NOTICE);
error_reporting(E_ERROR | E_WARNING | E_PARSE);
define ('CARRIERE', 1);
define ('BLACK_BOX', false);
define ('ATC', true);
define ('PIREP', false);
define ('AI_MANAGEMENT', false);
$data["Carriere"] = "true";
$data["atc"] = "true";
$data["AI_Management"] = "true";
$data["licence"]=licence;
$data["your_ip"]=$_SERVER["REMOTE_ADDR"];

//intégration du compilateur de sortie  XML et de ses outils
require_once (mark_5 . '/core/function/tracker.php');

//intégration du gestionnaire de class site
require_once (mark_5 . '/core/app/Auto_loader.php');

//intégration des messages d'erreur ou de communication normale
require_once ($_SERVER["DOCUMENT_ROOT"] . '/core/config/message.php');

//Chargement du prg d'autochargement des classes
Auto_loader::register ();

//initialisation de l'objet pour les messages de retour sur FFSTracker
$inter_com = New Erreur();

//si des données sont envoyées par la methode POST
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
	//récupération des données XML
	$xml = xml_extract (file_get_contents ('php://input'));

	//recherche de l'id du client, !!ce n'est pas une authentifacation
	$db_ffst = Db_mark5::getInstance('MARK_5');
	$data_ts = '`phpbb_users`.`user_id`';
	$where =" `phpbb_users`.`username_clean` ='". strtolower ($xml->verify->pilotID)."'";
	$client_ffst=$db_ffst->select( '`'.PF_MARK_5.'users`', $data_ts, $where, '', 'unique');

	//recherche des droits clients pour le non respect des versions
	$data_ts='`phpbb_profile_fields_data`.`pf_beta`';
	$where ="`phpbb_profile_fields_data`.`user_id`='".$client_ffst->user_id."'";
	$b_client_rules = $db_ffst->select ('`' . PF_MARK_5 . 'profile_fields_data`', $data_ts,  $where, '', 'unique');

	//extraction de la version client de FFSTracker
	$ver=str_replace('ffstracker_','',$xml->version);
	$version_used=extract_FFST_ver($ver);

	//extraction de la version release de FFSTracker
	$version_current = clean_FFST_files (URL_FFSTracker_current, '/FFSTracker_');
	$version_current=extract_FFST_ver($version_current);

	//login et mdp du client
	$username = $xml->verify->pilotID;
	$password = $xml->verify->password;

	// si le client fait une demande d'authenfication
	if (strtolower ($xml->switch->data) == "verify")
	{
		//comparaisons des versions
		$ver_diff=array_diff ($version_used,$version_current);

		//si les numéro de version corresponde parfaitement
		if(count($ver_diff)==0)
		{
			$inter_com->addMessage (1,$msg);//Bienvenue sur le serveur de...
			$inter_com->addMessage(301,$msg,"Vous utilisez la derniére version de FFSTracker\nMerci de nous suivre pendant cette aventure\n");//Vous utilisez la derniére version de FFSTracker...
			
			//lancement de la séquence d'authentification
			require ($_SERVER["DOCUMENT_ROOT"] . '/trk/tks_verify.php');
		}
		
		//si seulement la partie build differt
		elseif(!key_exists('major',$ver_diff) and !key_exists('minor',$ver_diff)  and key_exists('build',$ver_diff))
		{
			$inter_com->addMessage (1,$msg);//Bienvenue sur le serveur de...
			$inter_com->addMessage (101,$msg);//Attention votre version n'est pas à jour
			$inter_com->addMessage (102,$msg);//Voici la derniére version

			//lancement de la séquence d'authentification
			require ($_SERVER["DOCUMENT_ROOT"] . '/trk/tks_verify.php');
		}
		
		//sinon
		else
		{
			//Si le client a le droit d'utiliser une version différentes
			if ($b_client_rules->pf_beta==1)
			{
				
				$inter_com->addMessage (1,$msg);//Attention votre version n'est pas à jour
				$inter_com->addMessage (103,$msg);//Vous avez été identifié comme Beta Testeur
				$inter_com->addMessage (102,$msg);//Voici la derniére version

				//lancement de la séquence d'authentification
				require ($_SERVER["DOCUMENT_ROOT"] . '/trk/tks_verify.php');
			}
			//sinon
			else
			{
				$inter_com->addMessage (1,$msg);//Bienvenue sur le serveur de...
				$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
				$inter_com->addMessage (202,$msg);//Votre version de FFSTracker n'est plus supporté par le serveur pirep
				$inter_com->addMessage (2,$msg);//Bonne fin de journée
				$data['loginStatus'] = '0';//on refuse la connexion
			}
		}
	}
	
	//si le client fait une demande de synchronisation de la bd générale des avions
	if (strtolower ($xml->switch->data) == "syncai" or $xml->switch->data == "sendai")
	{
		//lancement de la séquence de synchronisation
		require ($_SERVER["DOCUMENT_ROOT"] . '/trk/tks_syncai.php');
	}
	
	//si le client fait une demande d'information sur une réservation existante
	if (strtolower ($xml->switch->data) == "getflight")
	{
		//on lance la recherche de réservation active
		require ($_SERVER["DOCUMENT_ROOT"] . '/trk/tks_getflight.php');
	}

	//si le client fait une demande mise à jour de la position du client de l'appareil et interrogation de la liste  des joueurs connectés
	if (strtolower ($xml->switch->data) == "liveupdate")
	{
		require ($_SERVER["DOCUMENT_ROOT"] . '/trk/tks_liveupdate.php');
	}

	//si le client fait une demande mise à jour de la position du client pour l'atc
	if (strtolower ($xml->switch->data) == "atc")
	{
		require ($_SERVER["DOCUMENT_ROOT"] . '/trk/tks_atc_send.php');
	}

	//si le client fait une demande d'enregistrement du rapport de vol
	if (strtolower ($xml->switch->data) == "pirep")
	{
		require ($_SERVER["DOCUMENT_ROOT"] . '/trk/tks_pirep.php');
	}

	//Déconnection
	if (strtolower ($xml->switch->data == "logout"))
	{
		require ($_SERVER["DOCUMENT_ROOT"] . '/trk/tks_logout.php');
	}
}

//Rajout des messages dans le XML
if ($inter_com->getMessage () != NULL)
{
	$data["erreur"] = $inter_com->getMessage ();
}
if ($inter_com->getWarning () != NULL)
{
	$data["erreur1"] = $inter_com->getWarning ();
}
if ($inter_com->getError () != NULL)
{
	$data["erreur2"] = $inter_com->getError ();
}
if ($inter_com->getDebug () != NULL)
{
	$data["erreur3"] = $inter_com->getDebug ();
}


$data2=pre_xml($data);
//var_dump($data2);
$xml = new Array2XML();
print  $xml->buildXML($data2);

$db_ffst->clear();
//$out_xml=new Array2XML();

//sendXML ($data);
?>