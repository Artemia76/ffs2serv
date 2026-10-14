<?php
	if (!defined ('PROTECTIONINCLUDE')) {
		die('Accès Interdit');
	}

	//suppression des slots abandonnés
	Session_mp::purge ();

	//recherche du slot du joueur grâce à sa clé de rappel
	$slot = Session_mp::find_by_key ($xml->switch->key);

	if ($slot === NULL) {
		//le client se déconnecte sur une erreur de niveau 2 et pourra se reconnecter
		$inter_com->addError (220, $msg);//Impossible d'actualiser votre Slot de connexion
		return;
	}

	$slot->update_my_slot ();

	//renvoi des autres joueurs présents, avec leur IP cryptée par la clé AES du demandeur
	$data_xml["whazzup"] = Session_mp::get_whazzup ($slot->server, $slot->pilotname, base64_decode ($slot->pu_key));
?>
