<?php
/**
 * Created by Sublimetxt3.
 * User: Flo
 * Date: 02/12/2016
 * Time: 13.00
 */

//décriptage du mot de passe
$password = decrypt ($password, key);
//appel de la méthode d'authentification du forum
$result = $auth->login ($username, $password, TRUE, 1, 0);

//si le forum valide l'authentification
if ($result['status'] === LOGIN_SUCCESS)
{
	$inter_com->addMessage (3, $msg);//Bonjour commandant

	//initialisation et recupération des données pilote
	$pilote = new Pilote($result["user_row"]['user_id']);
	//si l'émail est trouvé (obligatoirement présent)
	if ($pilote->user_email != NULL)
	{
		//recherche de la présentation du client sur le forum numéro 191 (nouveau sujet de présentation)
		$data_forum="count(`topic_id`) as count";
		$where="`forum_id` = '191' AND `topic_first_poster_name` ='".$pilote->username."'";
		$prez=$db_ffst->select( '`'.PF_MARK_5.'topics`', $data_forum, $where, '', 'unique');
		
		//recherche acceptation réglement par le client dans le forum 189 (réponse au sujet)
		$data_forum="count(`post_id`) as count";
		$where="`forum_id` = '189' AND `poster_id` ='".$pilote->user_id."'";
		$r_accepted=$db_ffst->select( '`'.PF_MARK_5.'posts`', $data_forum, $where, '', 'unique');
		
		//si le client est présenté sur le forum
		if($prez->count>0)
		{
			//si le client à accepté le réglement
			if($r_accepted->count>0)
			{
				//initialisation et recupération du slot pour le multijoueur
				$session_mp = new Session_mp();
				$session_mp->get_my_slot ($pilote->user_id);

				$inter_com->addMessage (302, $msg);//Connection au tableau multi-joueurs

				//si le pilote à laissé une trace de session
				if ($session_mp->pilot_id != NULL)
				{
					//si la trace a bien  été détruite
					if ($session_mp->clear_slot ($pilote->user_id) == TRUE)
					{
						$inter_com->addMessage (303, $msg);//Suppression d'ancien Slot de connexion

						create_slot://repére de goto si aucun slot existe
						
						//création d'un nouveau slot de connexion
						if($session_mp->create_slot ($pilote, $xml)== TRUE)
						{
							$inter_com->addMessage (305, $msg);//Création d'un nouveau Slot de connexion
							$session_mp->get_my_slot ($pilote->user_id);
							
							//demande la liste d'autre clients aussi connectés
							$players_slot=$data["whazzup"] = $session_mp->get_other_slot ('4');

							//si des slot de connection d'autre clients existent
							if($players_slot!==null)
							{
								$inter_com->addMessage (316, $msg);//Création du Slot réussi
								//on parcour tous les slot clients
								foreach ($players_slot as $key => $value)
								{
									if($session_mp->ip==decrypt($value['ip'], key) and $session_mp->port==$value['port'] and $session_mp->pilotname!=$value['name'])
									{
										$inter_com->addMessage (20, $msg);//Le serveur signal un echo sur votre connexion
										$inter_com->addMessage (307, $msg, "Conflit possible entre  " . $session_mp->pilotname ." et ".$value['name']. "\n\n");
										$data["used_port"] = $session_mp->port;
									}
								}
							}
							
							$TS3=new Channel;
							$TS3->Message_on_server($session_mp->pilotname.' vient de se connecter à FFStracker',5);

							//initialisation et recupération du de la position du client
							$map = new Data_map();
							$map->load ($pilote->user_id);

							//si une ancienne position est présente
							if ($map->pilotname != NULL)
							{
								$inter_com->addMessage (308, $msg);//Suppression de l'ancienne position
								
								//si cette position à bien été supprimée
								if ($map->clear_map ($pilote->user_id) == TRUE)
								{
									create_map_point://repére de goto si pas d'ancienne position
									$inter_com->addMessage (309, $msg);//Création du contact radar
									$map->create_map ($pilote, $xml->version);//on créer une nouvelle position client
									$map->load ($pilote->user_id);//recupération du de la position du client
									//si la position du client existe
									if ($map->pilotname != NULL)
									{
										$inter_com->addMessage (310, $msg);//Position radar établi

										//initialisation du systéme de clé de rappel
										$key = New Key($pilote->user_id);

										if ($key->key_f != NULL)
										{
											$key->delete($pilote->user_id);

											create_key://repére de goto si aucun clé existe
											
											//initialisation du bloc réservation propre au pilote
											$resa = new Resa();
											//recherche d'une réservation faite juste avant par le pilote
											$resa->search_recent ($pilote->user_id);

											//initialisation du systéme de clé de rappel
											if ($resa->flightnum != NULL)
											{
												//$inter_com->addMessage (8, $msg);
												$data['flightNumber'] = $resa->flightnum;
												if ($key->generate ($resa->id) != 0)
												{
													$inter_com->addMessage (0, $msg, "Création de la clé " . $key->key_f . "\n\n");
													$data["key"] = $key->key_f;//on retourne la clé du client
													
													//initialisation d'une boite noire et intégration de la premiére donnée
													$black_box = New Black_box();
													$black_box->create_bb ($key, $xml);

													$data['loginStatus'] = '1';

													
												}
												else
												{
													$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
													$inter_com->addMessage (211, $msg);//Impossible de créer une position radar
													$inter_com->addMessage (2,$msg);//Bonne fin de journée
													$data['loginStatus'] = '0';//on refuse la connexion
												}

											}
											else
											{
												$inter_com->addMessage (4, $msg);//Aucune réservation disponible!! nVous pouvez faire un vol libre ou...
												if ($key->generate () != 0)
												{
													$inter_com->addMessage (0, $msg, "Création de la clé " . $key->key_f . "\n\n");
													$data["key"] = $key->key_f;//on retourne la clé du client
													
													//initialisation d'une boite noire et intégration de la premiére donnée
													$black_box = New Black_box();
													$black_box->create_bb ($key, $xml);
													
													$data['loginStatus'] = '1';//on autorise la connexion
												}
												else
												{
													$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
													$inter_com->addMessage (211, $msg);//Impossible d'inscrire une nouvelle clé
													$inter_com->addMessage (2,$msg);//Bonne fin de journée
													$data['loginStatus'] = '0';//on refuse la connexion
												}
											}
										}
										else
										{
											$inter_com->addMessage (313,$msg);//Création d'une clé primaire
											goto create_key;
										}
									}
									else
									{
										$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
										$inter_com->addMessage (210, $msg);//Impossible de créer une position radar
										$inter_com->addMessage (2,$msg);//Bonne fin de journée
										$data['loginStatus'] = '0';//on refuse la connexion
									}
								}
								else
								{
									$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
									$inter_com->addMessage (208, $msg);//Impossible de supprimer l'anciene position
									$inter_com->addMessage (2,$msg);//Bonne fin de journée
									$data['loginStatus'] = '0';//on refuse la connexion
								}
							}
							//sinon comme aucune postion n'existe à ce moment
							else
							{
								$inter_com->addMessage (312,$msg);//Création d'une position primaire
								goto create_map_point;
							}
						}
						//sinon comme sa nouvelle session ne veut pas se créer
						else
						{
							$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
							$inter_com->addMessage (207, $msg);//Impossible de créer un nouveau Slot de connexion
							$inter_com->addMessage (2,$msg);//Bonne fin de journée
							$data['loginStatus'] = '0';//on refuse la connexion
						}
					}
					//sinon comme son ancienne session ne s'efface pas
					else
					{
						$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
						$inter_com->addMessage (206, $msg);//Impossible de supprimer l'ancien Slot de connexion
						$inter_com->addMessage (2,$msg);//Bonne fin de journée
						$data['loginStatus'] = '0';//on refuse la connexion
					}
				}
				//sinon comme il n'y a pas d'ancienne session du joueur
				else
				{
					$inter_com->addMessage (311,$msg);//Ouvertue d'un slot primaire
					goto create_slot;
				}

			}
			//sinon comme il n'a pas accepté le réglement
			else
			{
				$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
				$inter_com->addMessage (203,$msg);//Vous n'avez pas accepté le réglement...
				$inter_com->addMessage (204,$msg,$r_accepted->count."\n");//Vous ne vous êtes pas présenté...
				$inter_com->addMessage (2,$msg);//Bonne fin de journée
				$data['loginStatus'] = '0';//on refuse la connexion
			}
		}
		//sinon comme il n'est pas présenté
		else
		{
			$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
			$inter_com->addMessage (204,$msg);//Vous ne vous êtes pas présenté...
			$inter_com->addMessage (204,$msg,$prez->count."\n");//Vous ne vous êtes pas présenté...
			$inter_com->addMessage (2,$msg);//Bonne fin de journée
			$data['loginStatus'] = '0';//on refuse la connexion
		}
	}
	//sinon comme ses identifiant sont inccorect
	else
	{
		$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
		$inter_com->addMessage (209,$msg);//Utilisateur non reconnu par le forum
		$inter_com->addMessage (2,$msg);//Bonne fin de journée
		$data['loginStatus'] = '0';//on refuse la connexion
	}
}
else
{
	$inter_com->addMessage (201,$msg);//Connexion avec FFSTracker interrompue à la demande du serveur pirep
	$inter_com->addMessage (205,$msg);//Echec de l'authentification
	$inter_com->addMessage (2,$msg);//Bonne fin de journée
	$data['loginStatus'] = '0';//on refuse la connexion
}