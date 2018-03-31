<?php
/**
 * Created by Sublimetxt3.
 * User: Flo
 * Date: 02/12/2016
 * Time: 13:15
 */

//si le client demande à vérifier la presence de ses avions
if ($xml->switch->data == "syncai")
{
	$inter_com->addMessage (314, $msg);//Collect de CFG en cours

	//extraction des informations des avions envoyé au format JSON
	$checksum_list = json_decode ($xml->syncai->md5list);

	//création de la liste qui recevra les avions manquants
	$checksum_return = array ();

	//on parcours la liste des tous les avions informés par le client
	foreach ($checksum_list as $key => $value)
	{
		//initialitation d'un objet avion multijoueur et recherche de son existance dans la BDD
		$data_aircraft = New Mp_aircraft('hash', str_ireplace('md5_','',$value));
		
		//si le titre de l'appareil n'existe pas et que donc cet appareil n'est pas dans la BDD
		if ($data_aircraft->title == null)
		{
			//on stock les infos dans la liste des avions manquants
			$checksum_return[] = $value;

			//on initialise une demande de création d'appareil et on recherche son existance dans la BDD
			$requested_aircraft = New Mp_aircraft_request('chekcsum', str_ireplace('md5_','',$value));

			//si le checksum de la demande n'existe pas et que donc la demande n'est pas déja en cours par un autre client
			if ($requested_aircraft->chekcsum == null)
			{
				//on récupére la clé du client
				$key_pilote = New Key('', $xml->switch->key);

				//on prépare les données pour créer une demande d'intégration dans la BDD
				$data2 = array (
					'pilot_id'     => $key_pilote->pilot_id,
					'chekcsum'     => str_ireplace('md5_','',$value),
					'request_date' => time (),
					);
				//on envoie les données de la demande d'intégration
				$requested_aircraft->auto_create ($data2);
			}
			//sinon la demande existe déjà et n'a pas été résolu
			else
			{
				//on détruit l'ancienne demande
				$requested_aircraft->destroy_requested_aircraft (str_ireplace('md5_','',$value));

				//on récupére la clé du client
				$key_pilote = New Key('', $xml->switch->key);

				//on prépare les données pour créer une demande d'intégration dans la BDD
				$data2 = array (
					'pilot_id'     => $key_pilote->pilot_id,
					'chekcsum'     => str_ireplace('md5_','',$value),
					'request_date' => time (),
					);
				//on envoie les données de la demande d'intégration
				$requested_aircraft->auto_create ($data2);

			}
		}
	}
	//si un appareil est manquant dans la BDD
	if (count($checksum_return)>0)
	{
		//on renvoie au client la liste de tous les appareils manquants
		$data['no_found'] = json_encode ($checksum_return);
	}
	else
	{
		$inter_com->addMessage (315, $msg);//Fin de la récupération des CFG
	}
}

//si le client envoie ses avions a intégrer dans la BDD
if ($xml->switch->data == "sendai")
{
	//on récupére la clé du client
	$key_pilote = New Key('', $xml->switch->key);
	


	//on parcour la liste des avions a intégrer et on créer un tableau d'intégration
	$ai_tab = array ();
	foreach ($xml->sendai as $key => $value)
	{
		foreach ($value as $key2 => $value2)
		{
			$ai_tab[] = array (
				'checksum' => $key2,
				'data'     => $value2,
				);
		}
	}

	//on parcour ce nouveau tableau d'intégration
	foreach ($ai_tab as $key => $value)
	{
		//initialitation d'un objet avion multijoueur et recherche de son existance dans la BDD
		$data_aircraft = New Mp_aircraft('hash', str_ireplace('md5_','',$value['checksum']));
		//on initialise une demande de création d'appareil
		$requested_aircraft = New Mp_aircraft_request();
		//si le titre de l'appareil n'existe pas et que donc cet appareil n'est pas dans la BDD
		if ($data_aircraft->hash === null)
		{

			$text_of_airplane = gzinflate (base64_decode($value['data']));
			if ($data_aircraft->make_aircraft ($text_of_airplane, str_ireplace('md5_','',$value['checksum']),$key_pilote->pilot_id))
			{

				$requested_aircraft->destroy_requested_aircraft (str_ireplace('md5_','',$value['checksum']));
			}
		}
		//sinon si l'appareil à déjà été intégré par un autre client pendants ce laps de temps
		else
		{
			//on détruit systématiquement toutes demande de création concernant cet avion
			$requested_aircraft->destroy_requested_aircraft (str_ireplace('md5_','',$value['checksum']));
		}


	}

	//on récupére la clé du client
	$key_pilote = New Key('', $xml->switch->key);

	//on détruite la précédente demande de création d'appareil
	unset($requested_aircraft);

	//on initialise une demande de création d'appareil
	$requested_aircraft = New Mp_aircraft_request();

	//on regarde si toute les demandes restante faites par le client
	$list=$requested_aircraft->requested_aircraft($key_pilote->pilot_id);

	//on parcour cet liste des avions non intégrés
	foreach ($list as $key_2=>$value_2)
	{
		//on stock tous les avions qui n'ont pas été intégré
		$checksum_return[] = $value_2->chekcsum;
	}

	if (count($checksum_return)>0)
	{
		//on renvoie au client la liste de tous les appareils manquants
		$data['no_found'] = json_encode ($checksum_return);
	}
	else
	{
		$inter_com->addMessage (315, $msg);//Fin de la récupération des CFG
	}
}
