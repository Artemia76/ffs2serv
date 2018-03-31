<?php 
//création d'une trame pour une ligne de log de tentative de connexion
$to_log=array(
	'date'	 =>date ("Y-m-d H:i:s"),//date d'émission du log
	'name'	 =>$username,//joueur étant à l'origine de cette émission
	'ip'	 =>encrypt($_SERVER["REMOTE_ADDR"], key),//ip du joueur
	'op'	 =>'Appel de connexion',
	);

//pré intégration dans le log
$log[]=$to_log;

$data["licence"]=licence;
if(decrypt($xml->verify->password, key)==session_password)//vérification du mot du passe de session
{
	$to_log=array(
		'date'	 =>date ("Y-m-d H:i:s"),//date d'émission du log
		'name'	 =>$username,//joueur étant à l'origine de cette émission
		'ip'	 =>encrypt($_SERVER["REMOTE_ADDR"], key),//ip du joueur
		'op'	 =>'Tentative de connexion',
	);
	
	//pré intégration dans le log
	$log[]=$to_log;

	//création de la trame du joueur se connectant
	$temp_data_player=New stdClass();
	$temp_data_player->name=$username;
	$temp_data_player->ip=encrypt($_SERVER["REMOTE_ADDR"], key);
	$temp_data_player->port=strval($xml->verify->port);
	$temp_data_player->online='1';
	$temp_data_player->level='0';
	$temp_data_player->statut='5';
	$temp_data_player->time=time();
	$temp_data_player->serial=trim(strval($xml->serial));

	//création de l'objet joueur
	$me= New Player($temp_data_player);
	
	//recherche de tous les joueurs potentiellement connecté par présence de fichier
	$players=Get_FFST_Files ('./bd_light/player',  array ("php"), $recursif = true);
	//création du tableau multijoueur
	$data_multiplayer=array();

	$nb_player=0;
	foreach ($players as $key => $value)//analyse de tous les fichiers joeurs existant
	{
		$data_multiplayer=array();

		if ($value!='./bd_light/player/'.$username.'.php') //si ce n'est pas le fichier du joueur se connectant
		{
			if(filemtime($value)>(time()-40)) //on vérifie que ce fichier et moins vieux que 60 seconde
			{
				$data_multiplayer[$key]=require($value);
				$nb_player++;
			}

			else//sinon on l'efface
			{
				unlink($value);

				$to_log=array(
					'date'	 =>date ("Y-m-d H:i:s"),//date d'émission du log
					'name'	 =>$data_one_player->name,//joueur étant à l'origine de cette émission
					'ip'	 =>encrypt($_SERVER["REMOTE_ADDR"], key),//ip du joueur
					'op'	 =>'Déconnexion',

					);
				//pré intégration dans le log
				$log[]=$to_log;
				

				$to_log=array(
					'date'	 =>date ("Y-m-d H:i:s"),//date d'émission du log
					'name'	 =>$username,//joueur étant à l'origine de cette émission
					'ip'	 =>encrypt($_SERVER["REMOTE_ADDR"], key),//ip du joueur
					'op'	 =>'Purge de fichier',

					);
				//pré intégration dans le log
				$log[]=$to_log;
			}
		}


	}
	if ($nb_player>=max_player)
	{
		$inter_com->addError (10, $msg);
		$data['loginStatus'] = '0';
		
		$to_log=array(
			'date'	 =>date ("Y-m-d H:i:s"),//date d'émission du log
			'name'	 =>$username,//joueur étant à l'origine de cette émission
			'ip'	 =>encrypt($_SERVER["REMOTE_ADDR"], key),//ip du joueur
			'op'	 =>'Ejection par manque de places',

			);
		//pré intégration dans le log
		$log[]=$to_log;
	}
	else
	{
		$me->w_file();//création de la fiche du joeur se connectant


		if (file_exists('./bd_light/player/'.$username.'.php'))
		{
			$data['loginStatus'] = '1';
			$inter_com->addMessage (0, $msg);
			$inter_com->addMessage (0, $msg,"Fermeture des serveurs micro cette semaine.\n, merci de consulter cette page ".htmlentities("http://ffsimulateur2.fr/forum_free_2_play/viewtopic.php?f=209&t=14308");
			
			$inter_com->addMessage (0, $msg,'il y a '.($nb_player+1).' pilote(s) connecté(s) sur les '.max_player.' autorisés');
			$data['key']=$username.'%'.time();
			$data["whazzup"] = $data_multiplayer;
			
			$to_log=array(
				'date'	 =>date ("Y-m-d H:i:s"),//date d'émission du log
				'name'	 =>$username,//joueur étant à l'origine de cette émission
				'ip'	 =>encrypt($_SERVER["REMOTE_ADDR"], key),//ip du joueur
				'op'	 =>'Connexion établie sur le port '.strval($xml->verify->port),

				);
			
			$log[]=$to_log;
			
			$to_list_player=array(
				'date'			=>date ("Y-m-d H:i:s"),//date d'émission du log
				'name'			=>$username,//joueur étant à l'origine de cette émission
				'ip'			=>encrypt($_SERVER["REMOTE_ADDR"], key),//ip du joueur
				'serial' 		=>trim(strval($xml->serial)),

				);

			$all_ready_exist=false;

			if(is_array($list_player))
			{
				foreach ($list_player as $key => $value)
				{
					if(decrypt($to_list_player['ip'],key)===decrypt($value['ip'],key) and $to_list_player['name']===$value['name'] and $to_list_player['serial']===$value['serial'])
					{
						$all_ready_exist=true;
					}
				}
			}

			if($all_ready_exist==false)
			{
				$list_player[]=$to_list_player;
			}
		}
		else 
		{
			$inter_com->addError (12, $msg);
			$to_log=array(
				'date'	 =>date ("Y-m-d H:i:s"),//date d'émission du log
				'name'	 =>$username,//joueur étant à l'origine de cette émission
				'ip'	 =>encrypt($_SERVER["REMOTE_ADDR"], key),//ip du joueur
				'op'	 =>'Création fichier impossible',

				);
			
			$log[]=$to_log;
			$data['loginStatus'] = '0';
		}
	}
}
else
{
	$inter_com->addError (11, $msg);
	$to_log=array(
		'date'	 =>date ("Y-m-d H:i:s"),//date d'émission du log
		'name'	 =>$username,//joueur étant à l'origine de cette émission
		'ip'	 =>encrypt($_SERVER["REMOTE_ADDR"], key),//ip du joueur
		'op'	 =>'Mauvais mot de passe fournis',

		);
	
	$log[]=$to_log;
	$data['loginStatus'] = '0';
}
?>