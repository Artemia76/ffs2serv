<?php
//activation de la protection recursive pour les éléments qui seront intégrés
define ('PROTECTIONINCLUDE', TRUE);

//intégration de fichier de configuration général du site
require_once ('./config/config_gen.php');

//intégration de fichier de configuration général du site
require_once ('./config/config_aux.php');

//intégration du compilateur de sortie  XML et de ses outils
require_once ('./core/function/tracker.php');

//intégration du gestionnaire de class site
require_once ('./core/app/Auto_loader.php');

//intégration des messages d'erreur ou de communication normale
require_once ('./config/message.php');

//chargeur d'objet PHP
Auto_loader::register ();
//création instance de l'objet de message d'erreur ou normaux
$inter_com= New Erreur();
//création de la variable principale de communication XML
$data=array();

$data["AI_Management"] = "true";
$data["Carriere"] = "false";
$data["Free_flight"] = "false";
$data["online"] = "true";

$licence='A9S/F5qjZILJ+XBN8JFAZvXTX5Rf+T4h7Pmk70PvUKU2CZRCSMYB+o89BN1bHmolt4QMuE1KIdttwRxloUxHZMBLeITg/w17ElCuO2zqBnPnJFWi8eybM9T4uKgzEkhtL4y8W2EeexKVe';

//ouverture et décompilation du fichier log
$log=unserialize(file_get_contents('./bd_light/log.php'));
//ouverture et décompilation de la liste des joueurs
$list_player=unserialize(file_get_contents('./bd_light/list_player.php'));
//ouverture et décompilation de la liste des joueurs bannis
$players_ban=unserialize(file_get_contents('./bd_light/ban_players.php'));


if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
	$xml = xml_extract (file_get_contents ('php://input'));
	$username =  strval ($xml->verify->pilotID);
	$password = $xml->verify->password;
	$ban=false;

	foreach ($players_ban as $key => $value)//parcours de la liste des joueurs bannis à la recherche d'une consigne de banissement
	{
		if(trim($value['serial'])==trim(strval($xml->serial)))//si le numéro de série du disque dur est en mémoire
		{
			$ban=true;//le joueur est banni
		}
	}

	if ($ban==false)//si le joueur n'est pas banni
	{
		if ($xml->switch->data == "verify")//si on demande une authentification
		{
			require ('./tks_verify.php');
		}

		if ($xml->switch->data == "liveupdate")//si on demande une mise à jours de la position
		{
			require ('./tks_liveupdate.php');
		}

		if (($xml->switch->data == "syncai" or $xml->switch->data == "sendai") and defined ('FFS2_ask'))//si on demande à synchroniser les appareils et que l'option est active
		{
			require ('./tks_syncai.php');
		}
		else
		{
			$data['empty'] = '0';
		}

		if ($xml->switch->data == "askai" and defined ('FFS2_ask'))//si on demande à remplacer les appareils et que l'option est active
		{
			require ('./tks_askai.php');
		}
		else
		{
			$data['empty'] = '0';
		}
	}
	else//sinon
	{
		$data['loginStatus'] = '0';//on refuse la connexion
		$inter_com->addError (502, $msg);//on préviens le client de la raison de refus de connexion
	}
	
}
if ($inter_com->getMessage () != NULL)//si des messages de com doivent être envoyés
{
	$data["erreur"] = $inter_com->getMessage ();// on les stocks dans la variable de communication xml
}
if ($inter_com->getError () != NULL)//si des messages d'erreur' doivent être envoyés
{
	$data["erreur2"] = $inter_com->getError ();// on les stocks dans la variable de communication xml
}

sendXML ($data);
//compilation et enregistrement du fichier log
file_put_contents('./bd_light/log.php',serialize($log));
//compilation et enregistrement de la liste des joueurs bannis
file_put_contents('./bd_light/list_player.php',serialize($list_player));

?>