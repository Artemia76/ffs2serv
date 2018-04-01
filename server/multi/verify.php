<?php
	$inter_com->addMessage (1, $msg);//Bienvenue sur le serveur de FFS2play

	$username = $xml->verify->pilotID;
	$password = $xml->verify->password;

	//recherche de la clé de décryptage
	$PrivateRSAKey = PrivateRSAKey;


	//récuperation de la clé AES envoyer par le logiciel client
	$send_AES_key = RSA_decrypt ($xml->verify->AES, $PrivateRSAKey, 'D', 'N');

	$password = decrypt ($password, $send_AES_key, $new_gen_aes);

	$file_Session = './nodb/Session_mp/' . $username . '.ser';

	//si le mot de passe de votre session est correcte
	if ($password == session_password) {
		$inter_com->addMessage (10, $msg);//Vous avez fournis le bon mot de passe
		$inter_com->addMessage (302, $msg);//Connection au tableau multi-joueurs

		$slot = New Session_mp($username);
		$slot->create_slot ('0', $username, $xml, '1', base64_encode ($send_AES_key), '0');
		$inter_com->addMessage (305, $msg);//Création d'un nouveau Slot de connexion
		//on retourne la clé d'identification rapide
		$data_xml["key"]         = $slot->key_f;
		$data_xml['loginStatus'] = '1';//on accepte la connexion


	}
	else {
		$inter_com->addMessage (205, $msg);//Echec de l'authentification
	}
	/*
		//si un fichier concernant ce pseudo existe
		if (file_exists ($file_Session)) {
			//on initialise un joueur
			$usr = new userlogin();
			//on vérifie qu'il existe bien avec ses identifiants

			if (debug_ffst==TRUE)$inter_com->addMessage (302, $msg,"mdp:" .$password);
			if (debug_ffst==TRUE)$inter_com->addMessage (302, $msg,"aes:" .$send_AES_key);




			if ($usr->login ($username, $password, '0', '', TRUE, FALSE) != FALSE) {
				$inter_com->addMessage (10, $msg);//Vous avez été identifé avec un compte valide
				if (debug_ffst == TRUE)
					$inter_com->addMessage (302, $msg);//Connection au tableau multi-joueurs


				//initialisiation des slots et recherche d'un slot personnel
				$slot = New Session_mp($username);

				//si un slot au nom du pilote existe
				if ($slot->id != NULL) {
					if (debug_ffst == TRUE)
						$inter_com->addMessage (307, $msg);//Mise à jours du slot
					$serveur_info = $sql->retrieve ("tks_serveur", "serveur_name", "serveur_id='" . $slot->server . "'");

					$inter_com->addMessage (0, $msg, "\nvous êtes auto-reconnecté sur le serveur: \"" . $serveur_info . "\"\n");

					$slot_update = array ('ip' => $_SERVER["REMOTE_ADDR"], 'local_ip' => $xml->verify->local_ip, 'port' => $xml->verify->port,);
					//si on arrive à le mettre à jour avec une nouvelle clé
					if ($slot->update_my_slot ($slot_update, base64_encode ($send_AES_key)) == TRUE) {
						//on renvoie la clé de rappel
						$data_xml["key"]         = $slot->key_f;
						$data_xml['loginStatus'] = '1';//on accepte la connexion
						//renvoie des slot des autres joueurs présents
						$data_xml["whazzup"] = $slot->get_other_slot ($slot->server, base64_decode ($slot->pu_key), $new_gen_aes);
					}
					//sinon
					else {
						$inter_com->addMessage (220, $msg);//Impossible d'actualiser votre Slot de connexion...
						$inter_com->addMessage (201, $msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
						$inter_com->addMessage (2, $msg);//Bonne fin de journée
						$data_xml['loginStatus'] = '0';//on refuse la connexion
						goto stop_verify;//on bypasse tous le reste du programme, car echec
					}

				}
				//sinon
				else {
					//création d'un nouveau slot de connexion
					//si la création du slot réussie
					if ($slot->create_slot ($s_member["user_id"], $username, $xml, '1', base64_encode ($send_AES_key), '0') == TRUE) {
						if (debug_ffst == TRUE)
							$inter_com->addMessage (305, $msg);//Création d'un nouveau Slot de connexion
						//on retourne la clé d'identification rapide
						$data_xml["key"]         = $slot->key_f;
						$data_xml['loginStatus'] = '1';//on accepte la connexion
						//renvoie des slot des autres joueurs présents
						$data_xml["whazzup"] = $slot->get_other_slot ($slot->server, base64_decode ($slot->pu_key), $new_gen_aes);


					}
					//sinon son slot ne veut pas se créer, on lui refuse la connexion
					else {
						$inter_com->addMessage (201, $msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
						$inter_com->addMessage (207, $msg);//Impossible de créer un nouveau Slot de connexion
						$inter_com->addMessage (2, $msg);//Bonne fin de journée
						$data_xml['loginStatus'] = '0';//on refuse la connexion
						goto stop_verify;//on bypasse tous le reste du programme, car echec
					}

				}
			}
			else {
				$inter_com->addMessage (205, $msg);//Echec de l'authentification
				goto redirect_ser_pub;//on redirige vers le serveur public
			}
			////$data['loginStatus'] = '0';//on refuse la connexion
			goto stop_verify;//on bypasse tous le reste du programme, car echec
		}
		else {
			$inter_com->addMessage (216, $msg);//L'utilisateur ne semble ne pas exister.
			$inter_com->addMessage (11, $msg);//Vous avez été connecté en tant que visiteur

			redirect_ser_pub://repére de goto si le client n'est pas reconnu (prb de mot de passe ou d'identifiant)

			//recherche d'une utilisation en cours d'un même pseudo il y a moin de 5 minutes
			$login_pub_member = $sql->retrieve ("tks_mp", "lastupdate", "pilotname='" . $username . "' and lastupdate>'" . $m_35_secondes . "'");


			if ($login_pub_member == NULL) {
				$inter_com->addMessage (217, $msg);//Vous allez être redirigé vers le serveur public
				//initialisiation des slots et recherche d'un slot personnel
				$slot = New Session_mp($username);

				//si un slot existe à ce nom là
				if ($slot->id != NULL) {

					if (debug_ffst == TRUE)
						$inter_com->addMessage (307, $msg);//Mise à jours du slot

					$serveur_info = $sql->retrieve ("tks_serveur", "serveur_name", "serveur_id='" . $slot->server . "'");

					$inter_com->addMessage (0, $msg, "\nvous êtes auto-reconnecté sur le serveur: \"" . $serveur_info . "\"\n");

					$slot_update = array ('ip' => $_SERVER["REMOTE_ADDR"], 'local_ip' => $xml->verify->local_ip, 'port' => $xml->verify->port,);
					//si on arrive à le mettre à jour avec une nouvelle clé
					if ($slot->update_my_slot ($slot_update, base64_encode ($send_AES_key)) == TRUE) {
						//on retourne la clé d'identification rapide
						$data_xml["key"] = $slot->key_f;

						//renvoie des slot des autres joueurs présents
						$data_xml["whazzup"] = $slot->get_other_slot ($slot->server, base64_decode ($slot->pu_key), $new_gen_aes);

						$data_xml['loginStatus'] = '1';//on accepte la connexion
					}
					//sinon
					else {
						$inter_com->addMessage (220, $msg);//Impossible d'actualiser votre Slot de connexion...
						$inter_com->addMessage (201, $msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
						$inter_com->addMessage (2, $msg);//Bonne fin de journée
						$data_xml['loginStatus'] = '0';//on refuse la connexion
						goto stop_verify;//on bypasse tous le reste du programme, car echec
					}
				}
				//sinon
				else {
					//création d'un nouveau slot de connexion
					//si la création du slot réussie

					if ($slot->create_slot ("0000", $username, $xml, '1', base64_encode ($send_AES_key), '0') == TRUE) {
						if (debug_ffst == TRUE)
							$inter_com->addMessage (305, $msg);//Création d'un nouveau Slot de connexion

						//on retourne la clé d'identification rapide
						$data_xml["key"] = $slot->key_f;

						//renvoie des slot des autres joueurs présents
						$data_xml["whazzup"] = $slot->get_other_slot ($slot->server, base64_decode ($slot->pu_key), $new_gen_aes);

						$data_xml['loginStatus'] = '1';//on accepte la connexion
					}
					//sinon son slot ne veut pas se créer, on lui refuse la connexion
					else {
						$inter_com->addMessage (201, $msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
						$inter_com->addMessage (207, $msg);//Impossible de créer un nouveau Slot de connexion
						$inter_com->addMessage (2, $msg);//Bonne fin de journée
						$data_xml['loginStatus'] = '0';//on refuse la connexion
						goto stop_verify;//on bypasse tous le reste du programme, car echec
					}
				}
			}
			else {
				$inter_com->addMessage (218, $msg);//Ce pseudo est déja en cours d'utilisation. Patientez...
				$inter_com->addMessage (201, $msg);//Connexion avec FFSTracker interrompue...
				$inter_com->addMessage (2, $msg);//Bonne fin de journée
				$data_xml['loginStatus'] = '0';//on refuse la connexion
				goto stop_verify;//on bypasse tous le reste du programme, car echec
			}
		}
		stop_verify:*/
	//on bypasse tous le reste du programme, car le joueur ou le systéme ont un soucis technique ne permettant pas l'interconnexion
?>