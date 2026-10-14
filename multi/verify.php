<?php
	if (!defined ('PROTECTIONINCLUDE')) {
		die('Accès Interdit');
	}

	$inter_com->addMessage (1, $msg);//Bienvenue sur le serveur de FFS2play
	$data_xml['loginStatus'] = '0';//connexion refusée par défaut

	$username = strval ($xml->verify->pilotID);

	//le pseudo sert de nom de fichier et de balise XML : il doit être valide
	if (!Session_mp::is_valid_pilotname ($username)) {
		$inter_com->addMessage (221, $msg);//Pseudo invalide
		return;
	}

	//récupération de la clé AES envoyée par le logiciel client, cryptée avec notre certificat
	$send_AES_key = RSA_decrypt ($xml->verify->AES, PrivateRSAKey, 'D', 'N');
	if ($send_AES_key === false or strlen ($send_AES_key) !== 32) {
		$inter_com->addMessage (215, $msg);//Echec de l'échange clé de cryptage
		return;
	}

	$password = decrypt ($xml->verify->password, $send_AES_key, $new_gen_aes);

	//si le mot de passe de la session est incorrect
	if ($password === false or !hash_equals (session_password, $password)) {
		$inter_com->addMessage (205, $msg);//Echec de l'authentification
		return;
	}
	$inter_com->addMessage (10, $msg);//Vous avez fournis le bon mot de passe

	//le pseudo est-il déjà utilisé par un autre joueur ?
	$slot = new Session_mp($username);
	if ($slot->is_active () and $slot->ip !== $_SERVER["REMOTE_ADDR"]) {
		$inter_com->addMessage (218, $msg);//Ce pseudo est déja en cours d'utilisation
		return;
	}

	$slot = new Session_mp();
	if (!$slot->create_slot ('0', $username, $xml, '1', base64_encode ($send_AES_key), '0')) {
		$inter_com->addMessage (207, $msg);//Impossible de créer un nouveau Slot de connexion
		return;
	}
	if (debug_ffst == TRUE) {
		$inter_com->addMessage (302, $msg);//Connection au tableau multi-joueurs
		$inter_com->addMessage (305, $msg);//Création d'un nouveau Slot de connexion
	}

	//on retourne la clé d'identification rapide et la liste des joueurs déjà présents
	$data_xml["key"]         = $slot->key_f;
	$data_xml['loginStatus'] = '1';//on accepte la connexion
	$data_xml["whazzup"]     = Session_mp::get_whazzup ($slot->server, $send_AES_key);
?>
