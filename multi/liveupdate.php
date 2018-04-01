<?php
	function Get_FFST_Files ($folder, $ext = array ('ser'), $recursif = TRUE)
	{

		$files = array ();
		$dir   = opendir ($folder);
		while ($file = readdir ($dir)) {
			if ($file == '.' || $file == '..') {
				continue;
			}
			if (is_dir ($folder . '/' . $file)) {
				if ($recursif == TRUE) {
					$files = array_merge ($files, Get_FFST_Files ($folder . '/' . $file, $ext));
				}
			}
			else {
				foreach ($ext as $v) {
					if (strtolower ($v) == strtolower (substr ($file, -strlen ($v)))) {
						$files[] = $folder . '/' . $file;
						break;
					}
				}
			}
		}
		closedir ($dir);
		return $files;
	}

	//récupération de tous les fichiers joueurs
	$players = Get_FFST_Files ('./nodb/Session_mp/', array ("ser"), $recursif = TRUE);

	$key_a = 0;

	foreach ($players as $player) {
		//$player_data = file_get_contents($player);
		$data_uns = unserialize (file_get_contents ($player));

		if ($data_uns['key_f'] == $xml->switch->key) {
			$slot = New Session_mp($data_uns['pilotname'], 'pilotname');
			$slot->update_my_slot ();

		}
	}

	foreach ($players as $player) {
		if (filemtime ($player) < (time () - 40)) //on vérifie que ce fichier et moins vieux que 60 seconde
		{
			//unlink ($player);
		}
		else{

			$key_a++;
			//$player_data = file_get_contents($player);
			$data_uns = unserialize (file_get_contents ($player));

			if ($data['key_f'] != $xml->switch->key) {


				$data_whazzup[$key_a]["name"] = $data_uns['pilotname'];
				//cryptage des ip
				$data_whazzup[$key_a]["ip"] = encrypt ($data_uns["ip"], base64_decode ($slot->pu_key), $new_gen_aes);
				//info de l'IP local
				$data_whazzup[$key_a]["local_ip"]   = $data_uns["local_ip"];
				$data_whazzup[$key_a]["port"]       = $data_uns["port"];
				$data_whazzup[$key_a]["server"]     = $data_uns["server"];
				$data_whazzup[$key_a]["lastupdate"] = $data_uns["lastupdate"];
				$data_whazzup[$key_a]['position']   = "icao";


			}
		}




		//$slot = New Session_mp($username);


		//echo $data;

		//var_dump ($data);


		if (filemtime ($value) < (time () - 40)) //on vérifie que ce fichier et moins vieux que 60 seconde
		{
			//$data_multiplayer[$key] = require ($value);
			//$nb_player++;
		}

		else//sinon on l'efface
		{
			//unlink ($player);
		}
	}

	$data_xml["whazzup"]=$data_whazzup;
	//initialisiation des slots et recherche d'un slot personnel
	/*$slot = New Session_mp($xml->switch->key, 'key_f');

	//si un slot au nom du pilote existe
	if ($slot->id != NULL) {
		$slot->update_my_slot ();
		//$slot->update_my_slot('none',base64_encode($send_AES_key))== TRUE
		//renvoie des slot des autres joueurs présents
		$data_xml["whazzup"] = $slot->get_other_slot ($slot->server, base64_decode ($slot->pu_key), $new_gen_aes, $slot->tx_mod);


	}

	/*
	//initialisation des positions radars
	$map = New Data_map($slot->pilotname);
	//var_dump($map);
	if ($map->pilotname == NULL) {
		$map->create_map ($slot->pilot_id, $slot->pilotname, $xml);
	}
	else {
		$map->update_map ($slot->pilotname, $xml);
	}*/
	//var_dump($map);
?>
