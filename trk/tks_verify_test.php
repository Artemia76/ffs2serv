<?php
/**
 * Created by PhpStorm.
 * User: Flo
 * Date: 11/02/2016
 * Time: 13:41
 */
$password = decrypt ($password, key);
$result = $auth->login ($username, $password, TRUE, 1, 0);

$inter_com->addMessage (0, $msg);
$conflict=FALSE;

$now = $date = date ("Y-m-d H:i:s");
$date = new DateTime($now);
$date->add (new DateInterval('PT30S'));
$time = $date->format ("Y-m-d H:i:s");

if ($result['status'] === LOGIN_SUCCESS)
{
	//initialisation et recupération du pilote
	$pilote = new Pilote($result["user_row"]['user_id']);
	if ($pilote->user_email != NULL)
	{
		if($pilote->user_posts>1)
		{
			$session_mp = new Session_mp();
			$all_players=$session_mp->get_other_slot ();



			foreach ($players_slot as $key => $value)
			{
				if($_SERVER["REMOTE_ADDR"]!=decrypt($value['ip'], key) and $pilote->username==$value['name'] and $time<$value['lastupdate'])
				{
					$inter_com->addMessage (20, $msg);//Alerte:Le serveur signal que vous êtes déjà connecté ailleurs.
					$inter_com->addMessage (22, $msg);//Patientez 30 secondes avant la prochaine tentative.
					$inter_com->addMessage (501, $msg);
					$data["used_port"] = $session_mp->port;
					$conflict=TRUE;
					break;
				}
			}



			if($conflict==FALSE)
			{
				//$inter_com->addMessage (0, $msg, "avant L: ". __line__."\n\n");

				if ($session_mp->pilot_id != NULL)
				{
					if ($session_mp->clear_slot ($pilote->user_id))
					{
						$session_mp = new Session_mp();
					}
				}
				//Suppression d'ancien Slot de connexion
				if ($session_mp->clear_slot ($pilote->user_id) == TRUE or $session_mp->pilot_id == NULL)
				{
					$session_mp->create_slot ($pilote, $xml);
					if ($session_mp->pilot_id != NULL)
					{
						$inter_com->addMessage (0, $msg, "Bienvenue " . $username . "\n\n");

						$session_mp->get_my_slot ($pilote->user_id);
						$data["whazzup"]=$session_mp->get_other_slot ();

						$TS3=new Ts3;
						$TS3->send_msg($session_mp->pilotname.' vient de se connecter à FFStracker',5,FALSE);

						$map = new Data_map();
						$map->load ($pilote->user_id);

						//destruction des données actueles du marker
						if ($map->pilotname != NULL)
						{
							if ($map->clear_map ($pilote->user_id) != TRUE)
							{
								$inter_com->addMessage (5, $msg);//Purge des anciennes données impossible
							}
							else
							{
								//création des données actueles du marker
								$map->create_map ($pilote, $xml->version);
								if ($map->pilotname == NULL)
								{
									$inter_com->addMessage (6, $msg);//Création de la fiche dans le tableau impossible
								}
							}
						}


						//initialisation du systéme de clé de rappel
						$key = New Key($pilote->user_id);

						if ($key->key_f != NULL)
						{
							$key->delete($pilote->user_id);
						}

						//initialisation du bloc réservation propre au pilote
						$resa = new Resa();
						//recherche d'une réservation faite juste avant par le pilote
						$resa->search_recent ($pilote->user_id);

						//initialisation du systéme de clé de rappel
						if ($resa->flightnum != NULL)
						{
							$data['flightNumber'] = $resa->flightnum;
							if ($key->generate ($resa->id) != 0)
							{
								$inter_com->addMessage (0, $msg, "Création de la clé " . $key->key_f . "\n\n");
								$data["key"] = $key->key_f;
								$data['loginStatus'] = '1';
							}
							else
							{
								$inter_com->addMessage (112, $msg);//Impossible d'inscrire une nouvelle clé
								$data['loginStatus'] = '0';
							}

						}
						else
						{
							$inter_com->addMessage (9, $msg);//Aucune réservation disponible!!\nVous pouvez faire un vol libre ou charger un réservation manuellement
							if ($key->generate () != 0)
							{
								$inter_com->addMessage (0, $msg, "Création de la clé " . $key->key_f . "\n\n");
								$data["key"] = $key->key_f;
								$data['loginStatus'] = '1';
							}
							else
							{
								$inter_com->addError (112, $msg);//Impossible d'inscrire une nouvelle clé Echec de l'authentification
								$data['loginStatus'] = '0';
							}
						}

						$black_box = New Black_box();
						$black_box->create_bb ($key, $xml);
					}
					else
					{
						$inter_com->addMessage (110, $msg);//ccés impossible au systéme Peer to Peer
						$data['loginStatus'] = '0';
					}
				}
				else
				{
					$inter_com->addMessage (110, $msg);//ccés impossible au systéme Peer to Peer
					$data['loginStatus'] = '0';
				}
			}
			else
			{
				$inter_com->addMessage (113, $msg);//Votre dossier de pilote est incomplé, veuillez contacter un administrateur
				$data['loginStatus'] = '0';
			}

		}
		else
		{
			$inter_com->addMessage	 (120, $msg);//Votre dossier de pilote est incomplé, prez...
			$data['loginStatus'] = '0';
		}
	}
	else
	{
		$inter_com->addMessage (113, $msg);//Votre dossier de pilote est incomplé, veuillez contacter un administrateur
		$data['loginStatus'] = '0';
	}
}
else
{
	$inter_com->addMessage (114, $msg);//Impossible d'inscrire une nouvelle clé
	$data['loginStatus'] = '0';
}
