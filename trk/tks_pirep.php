<?php
/**
 * Created by Sublimetxt3.
 * User: Flo
 * Date: 04/12/2016
 * Time: 12:30
 */
$key = New Key('', $xml->switch->key);

$pilote = new Pilote($key->pilot_id);

//$TS3=new Channel;
//$TS3->Message_on_server($pilote->pilotname.' vient de terminer un vol',5);

//initialisation d'une boite noire et intégration de la premiére  et chargement des données déjà enregistrés
$black_box = New Black_box();
$black_box->load_bb ($key);

//si il y a des informations à mettre à jour
if($xml->liveupdate!=NULL)
{
	//si la boite noire n'est pas cloturé au second niveau
	if ($black_box->closed != 2)
	{
		//on cloture la boite en niveau 1
		$black_box->setClosed (1);
		
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
	//sinon
	else
	{
		//aucune action spécifique
	}
}

//initialisation d'in pirep
$pirep = New Pirep();
//remplissage du pirep
$pirep->generate($key,$xml);
//si toutes les infos sont correctes
if($pirep->err_obj==NULL)
{
	$inter_com->addMessage (8, $msg);//Création du pireps distant réussi.
	//si l'enregistrement du pirep sur la BDD c'est bien déroulé
	if($pirep->auto_create()==1)
	{
		$inter_com->addMessage (8, $msg);//Envoie du dossier au serveur distant réussi.\nVous pouvez quitter FFSTracker si vous le souhaitez.
		//on signal au tracker client que l'enregistrement du pirep c'est bien déroulé
		$data['pirepStatus']= '1';
	}
	//sinon
	else
	{
		$inter_com->addMessage (9, $msg);//Envoie du dossier au serveur distant impossible. Sauvegarder le dossier sur votre pc et envoyé ...
		//on signal au tracker client que l'enregistrement du pirep ne c'est pas bien déroulé
		$data['pirepStatus']= '0';
	}
}
else
{
	$inter_com->addMessage (9, $msg);//Envoie du dossier au serveur distant impossible. Sauvegarder le dossier sur votre pc et envoyé ...
	
	//on parcours les erreurs que le générateur de pirep à relevé
	foreach($pirep->err_obj as $value)
	{
		$inter_com->addMessage (0, $msg,"Erreur du générateur de dossier =>:".$value."\n");
	}

	//on signal au tracker client que l'enregistrement du pirep ne c'est pas bien déroulé
	$data['pirepStatus']= '0';
}
