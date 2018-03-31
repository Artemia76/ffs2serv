<?php
/**
 * Created by Sublimetxt3.
 * User: Flo
 * Date: 04/12/2016
 * Time: 11:30
 */

//initialisation des clés du pilote
$key = New Key('', $xml->switch->key);
//mise à jours de sa date de validité
$key->update ();

//initialisation et recupération du slot pour le multijoueur
$session_mp = new Session_mp();
$session_mp->get_my_slot ($key->pilot_id);
//mise à jours de la date de refresh du slot
$session_mp->update_my_slot ($key->pilot_id);

//demande la liste d'autre clients aussi connectés
$data["whazzup"] = $session_mp->get_other_slot ('4');

//initialisation et recupération du de la position du client
$map = new Data_map();
$map->load ($key->pilot_id);

//si la position du client existe
if ($map->pilot_id != NULL)
{
	//si on arrive à actualiser la position du client
	if($map->update_map ($xml->liveupdate, $key)!= 0)
	{
		//aucune action spécifique
	}
	else
	{
		$inter_com->addMessage (104, $msg);//Impossible de mettre à jour votre posistion GPS
		$inter_com->addMessage (213, $msg);//Connexion avec FFSTracker maintenue
	}

}
//sinon si elle est absente
else
{
	//initialisation et recupération des données pilote
	$pilote = new Pilote($key->pilot_id);

	//Suppression de l'ancienne position ou de reliquat
	$map->clear_map ($key->pilot_id);
	$inter_com->addMessage (312, $msg);//Création d'une position primaire

	//on créer une nouvelle position client
	$map->create_map ($pilote, $xml->version);
	$inter_com->addMessage (310, $msg);//Position radar établi
}

//initialisation d'une boite noire et intégration de la premiére  et chargement des données déjà enregistrés
$black_box = New Black_box();
$black_box->load_bb ($key);

//si la boite noire existe bien pour ce vol et ce client
if ($black_box->pilot_id != NULL)
{
	//si la boite noire n'a pas été cloturé
	if($black_box->closed==0)
	{	//si le client est en attente
		if ($xml->liveupdate->status == 'Attente')
		{
			//si la boite noire est vierge
			if ($black_box->record == NULL)
			{
				//enregistrement des données
				$black_box->update_bb ($xml->liveupdate, $key);
				$inter_com->addMessage (317, $msg);//Démarrage de l'enregistrement
			}
		}
		//sinon si le client est en Embarquement
		elseif ($xml->liveupdate->status == 'Embarquement')
		{
			//si l'embraquement n'a pas été inscrit dans la boite noire
			if (stripos ($black_box->record, 'Embarquement') === FALSE)
			{
				//si la boite noire est vierge
				if ($black_box->record == NULL)
				{
					$inter_com->addMessage (317, $msg);//Démarrage de l'enregistrement
				}
				//enregistrement des données
				$black_box->update_bb ($xml->liveupdate, $key);
			}
		}
		//sinon si le client est en débarquement
		elseif ($xml->liveupdate->status == 'Débarquement')
		{
			//si la boite noire n'est pas rattaché à un vol enregistré
			if($key->attached == 0)
			{
				//on cloture la boite noire
				$black_box->setClosed (1);
				//on met à jour la boite noire
				$black_box->update_bb ($xml->liveupdate, $key);

				$inter_com->addMessage (318, $msg);//Fin du vol détecté, cloture de la boîte noire

				//si la réécriture de la boite noire réussi
				if ($black_box->auto_clean () == 1)
				{
					$inter_com->addMessage (318, $msg);//Défragmentaion de la boîte noire réussi
				}
				//sinon
				else
				{
					$inter_com->addMessage (214, $msg);//Echec de la défragmentaion de la boîte noire
				}
			}
			//si la boite noire est rattaché à un vol enregistré
			if($key->attached != 0)
			{
				//si la boite noire n'est pas cloturé au second niveau
				if ($black_box->closed != 2)
				{
					//on cloture la boite en niveau 1
					$black_box->setClosed (1);
					//on met à jour la boite noire
					$black_box->update_bb ($xml->liveupdate, $key);

					//si la réécriture de la boite noire réussi
					if ($black_box->auto_clean () == 1)
					{
						$inter_com->addMessage (318, $msg);//Défragmentaion de la boîte noire réussi
					}
					else
					{
						$inter_com->addMessage (214, $msg);//Echec de la défragmentaion de la boîte noire
					}
				}
			}

		}
		//sinon si le client signal un crash de l'appareil
		elseif ($xml->liveupdate->status == 'Crash' and $key->attached == 0)
		{
			//on dit que la boite noire est vide
			$black_box->setPoints (0);
			//on cloture la boite en niveau2
			$black_box->setClosed (2);
			//on met à jour la boite noire
			$black_box->update_bb ($xml->liveupdate, $key);

			$inter_com->addMessage (0, $msg, "Cela est toujours un plaisir de vous voir piloter monsieurs ;)\n");
		}
		//sinon on fait simplement un rajout de donnée dans la boite noire
		else
		{
			$black_box->update_bb ($xml->liveupdate, $key);
		}
	}
	else
	{
		//aucune action spécifique
	}
}
//si la boite noire n'existe pas pour ce vol et ce client
else
{
	//initialisation d'une boite noire
	$black_box = New Black_box();
	//creation d'une boite noire dans la BDD
	$black_box->create_bb ($key, $xml);

	$inter_com->addMessage (320, $msg);//Création d'un fichier d'enregistrement
	
	//si la boite noire est vierge et que le client est en attente
	if ($black_box->record == NULL and $xml->liveupdate->status != 'Attente')
	{
		//on met à jour la boite noire
		$black_box->update_bb ($xml->liveupdate, $key);
		$inter_com->addMessage (317, $msg);//Démarrage de l'enregistrement
	}
}