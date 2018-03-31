<?php
/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 11/02/2016
 * Time: 13:03
 */

//activation de la protection recursive pour les éléments qui seront intégrés
define ('PROTECTIONINCLUDE', TRUE);

//intégration de fichier de configuration général du site
require_once ($_SERVER["DOCUMENT_ROOT"] . '/n_core/config/config_gen.php');


//intégration des connecteurs du forum
define ('In_PHPBB', TRUE);
$phpbb_root_path = './../forum/';
$phpEx = substr (strrchr (__FILE__, '.'), 1);
include ($phpbb_root_path . 'common.' . $phpEx);
$request->enable_super_globals ();

//error_reporting (0);
//error_reporting(E_ERROR | E_PARSE);
error_reporting (E_ALL);


$data["Carriere"] = "true";
$data["Free_flight"] = "true";
$data["online"] = "true";

$data["AI_Management"] = "true";
$data["licence"]=licence;
$data["your_ip"]=$_SERVER["REMOTE_ADDR"];

//intégration du compilateur de sortie  XML et de ses outils
require_once (mark_5 . '/n_core/function/tracker.php');

//intégration du gestionnaire de class site
require_once (mark_5 . '/n_core/app/Auto_loader.php');

//intégration des messages d'erreur ou de communication normale
require_once ($_SERVER["DOCUMENT_ROOT"] . '/n_core/config/message.php');

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
	$user_ffst=$db_ffst->select( '`'.PF_MARK_5.'users`', $data_ts, $where, '', 'unique');

	
	//recherche des droits clients pour le non respect des versions
	$data_ts='`phpbb_profile_fields_data`.`pf_beta`';
	$where ="`phpbb_profile_fields_data`.`user_id`='".$user_ffst->user_id."'";
	$user_ffst = $db_ffst->select ('`' . PF_MARK_5 . 'profile_fields_data`', $data_ts,  $where, '', 'unique');
	
	//extraction de la version client de FFSTracker
	$used_version = $xml->version;
	$ver=str_replace('ffstracker_','',$used_version);
	$version_used=extract_FFST_ver($ver);
	$FFST_CURRENT = clean_FFST_files (URL_FFSTracker_current, '/FFSTracker_');


	$username = $xml->verify->pilotID;
	$password = $xml->verify->password;

		/**
		 * Demande d'authenfication
		 */
		if ($xml->switch->data == "verify")
		{
			if ($ver==$FFST_CURRENT)
			{
				$inter_com->addMessage (0, $msg, "\nVous utilisez la version ".$ver."\n");

				$inter_com->addMessage (0, $msg, "Derniére version ".$FFST_CURRENT."\n");

				require ($_SERVER["DOCUMENT_ROOT"] . '/n_trk/tks_verify_test.php');
			}

			elseif ($user_ffst->pf_beta==1)
			{
				$inter_com->addMessage (0, $msg, "\nVous utilisez la version ".$ver."\n");

				$inter_com->addMessage (0, $msg, "Mais voici la derniére version ".$FFST_CURRENT."\n");

				$inter_com->addMessage (0, $msg, "Vous êtes en mod BETA TESTEUR\n");

				require ($_SERVER["DOCUMENT_ROOT"] . '/n_trk/tks_verify_test.php');
			}

			else
			{
				$inter_com->addError (202, $msg);
			}
			
		}

		if ($xml->switch->data == "syncai" or $xml->switch->data == "sendai")
		{
			require ($_SERVER["DOCUMENT_ROOT"] . '/n_trk/tks_syncai.php');
		}

		if ($xml->switch->data == "askai")
		{
			require ($_SERVER["DOCUMENT_ROOT"] . '/n_trk/tks_askai.php');
		}


		/**
		 * Demande d'information sur une réservation définis
		 */
		if (strtolower ($xml->switch->data) == "getflight")
		{
			require ($_SERVER["DOCUMENT_ROOT"] . '/n_trk/tks_getflight.php');
			
		}

		/**
		 * Mise à jour de la position de l'appareil et Demande de joueur connecté
		 */
		if ($xml->switch->data == "liveupdate")
		{
			require ($_SERVER["DOCUMENT_ROOT"] . '/n_trk/tks_liveupdate.php');
		}
		/**
		 * Envoie du dossier
		 */
		if ($xml->switch->data == "pirep")
		{
			require ($_SERVER["DOCUMENT_ROOT"] . '/n_trk/tks_pirep.php');
		}
		/**
		 * Déconnection
		 */
		if ($xml->switch->data == "logout")
		{
			require ($_SERVER["DOCUMENT_ROOT"] . '/n_trk/tks_logout.php');
		}


	}
	if ($inter_com->getMessage () != NULL)
	{
		$data["erreur"] = $inter_com->getMessage ();//var_dump($inter_com->getMessage ());
	}
	if ($inter_com->getError () != NULL)
	{
		$data["erreur2"] = $inter_com->getError ();
	}

	sendXML ($data);
	?>