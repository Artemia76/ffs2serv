<?php
$data['key']=strval ($xml->switch->key);

$name_of_file=explode('%', $data['key']);

//echo './bd_light/player/'.$name_of_file[0].'.php';

$temp_data_player=require('./bd_light/player/'.$name_of_file[0].'.php');



$temp_data_player['time']=time();

$me= New Player($temp_data_player);
$me->w_file();//maintient de la fiche du joeur se connectant

$data['loginStatus'] = '1';

$players=Get_FFST_Files ('./bd_light/player',  array ("php"), $recursif = true);

$data_multiplayer=array();//création du tableau multijoeur
foreach ($players as $key => $value)//analyse de tous les fichiers joeurs existants
{
	if ($value!='./bd_light/player/'.$name_of_file[0].'.php') //si ce n'est pas le fichier du joueur se connectant
	{
		if(filemtime($value)>(time()-60)) //on vérifie que ce fichier et moins vieux que 60 secondes
		{
			$data_multiplayer[$key]=require($value);
		}
		else//sinon on l'efface
		{
			unlink($value);
		}
	}
}


$temp_data_map_player=New stdClass();
$temp_data_map_player->name=$name_of_file[0];
$temp_data_map_player->latitude=strval($xml->liveupdate->latitude);
$temp_data_map_player->longitude=strval($xml->liveupdate->longitude);
$temp_data_map_player->heading=strval($xml->liveupdate->heading);
$temp_data_map_player->altitude=strval($xml->liveupdate->altitude);
$temp_data_map_player->iaspeed=strval($xml->liveupdate->iaspeed);
$temp_data_map_player->status=strval($xml->liveupdate->status);
$temp_data_map_player->registration=strval($xml->liveupdate->registration);
$temp_data_map_player->time=date ("Y-m-d H:i:s");

$my_position= New Position($temp_data_map_player);
$my_position->w_file();//mise à jours de la posistion du joueur

//file_put_contents('./bd_light/map/'.$name_of_file[0].'.txt',serialize($temp_data_map_player));//création de la fiche du joeur se connectant

$to_log=array(
	'date'	 =>date ("Y-m-d H:i:s"),
	'name'	 =>$name_of_file[0],
	'ip'	 =>encrypt($_SERVER["REMOTE_ADDR"], key),
	'op'	 =>'Actualisation de la position',

	);

$log[]=$to_log;

//$log=$log."\n\n".date ("Y-m-d H:i:s").":    ".$name_of_file[0]." actualise sa position. IP:    ".encrypt($_SERVER["REMOTE_ADDR"], key);

$players_on_map=Get_FFST_Files ('./bd_light/map',  array ("php"), $recursif = true);

foreach ($players_on_map as $key => $value)//analyse de tous les fichiers joeurs existant
{
	if(filemtime($value)>(time()-60)) //on vérifie que ce fichier et moins vieux que 60 seconde
	{}
	else//sinon on l'efface
	{
		unlink($value);
			//$log=$log."\n\n".date ("Y-m-d H:i:s").":    ".$value." Fichier détruit.";

		$to_log=array(
			'date'	 =>date ("Y-m-d H:i:s"),
			'name'	 =>$name_of_file[0],
			'ip'	 =>encrypt($_SERVER["REMOTE_ADDR"], key),
			'op'	 =>'Nettoyage des fichiers',

			);

		$log[]=$to_log;
	}
	
}

$data["whazzup"] = $data_multiplayer;

?>