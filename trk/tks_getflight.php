<?php
/**
 * Created by Sublimetxt3.
 * User: Flo
 * Date: 02/12/2016
 * Time: 20:00
 */
//on récupére la clé du client et on la décompose
$dcp_key = explode("-", $xml->switch->key);

//si la second partis de la clé contient 0000 et non pas un id de reservation de la BDD
if ($dcp_key[1] == "0000")
{
	$inter_com->addMessage (212,$msg);//Impossible de trouver ou de charger votre réservation
	$inter_com->addMessage (213,$msg);//Connexion avec FFSTracker maintenue
	$inter_com->addMessage (5,$msg);//Vous pouvez faire un vol libre ou simplement voler entre amis
}
//Sinon si elle contien un identifiant numérique fournis par la BDD
else
{
	//initialisation et recupération de la reservation du client
	$resa = NEW Resa();
	//récuparation grace au numéro du joeur ([0]) et au numéro de réservation ([1])
	$resa->load_resa($dcp_key[0], $dcp_key[1]);

	//si il n'y a aucun soucis technique avec la réservation
	if ($resa->err_obj === NULL)
	{
		//on initalise le pilote et on récupére ses informations pour une diffusion de message
		$pilote = new Pilote($dcp_key[0]);
		$TS3=new Channel;
		$TS3->Message_on_server($pilote->username.' vient de charger un vol',5);

		//on envoie au client toutes les informartions utiles concernant sa réservation
		$data=$resa->send_resa();
		
		$data["key"] = $xml->switch->key;//et on confirme la clé
	}
	//sinon
	else
	{
		$inter_com->addMessage (212,$msg);//Impossible de trouver ou de charger votre réservation
		$inter_com->addMessage (213,$msg);//Connexion avec FFSTracker maintenue
		$inter_com->addMessage (5,$msg);//Vous pouvez faire un vol libre ou simplement voler entre amis

		//initialisation du systéme de clé de rappel du client
		$key = New Key($dcp_key[0]);
		//on détruit cette clé invalide
		$key->delete($dcp_key[0]);

		if ($key->generate () != 0)
		{
			$inter_com->addMessage (0, $msg, "Création d'une nouvelle clé " . $key->key_f . "\n\n");
			$data["key"] = $key->key_f;//on retourne la clé du client
		}
		else
		{
			$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
			$inter_com->addMessage (211, $msg);//Impossible de créer une position radar
			$inter_com->addMessage (2,$msg);//Bonne fin de journée
		}
	}
}
?>