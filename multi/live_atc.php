<?php 	
//initialisiation des slots et recherche d'un slot personnel
$slot=New Session_mp($xml->switch->key,'key_f');

//initialisation des positions radars
$map=New Data_map($slot->pilotname);

if($map->pilotname!=NULL)
{
	//var_dump($slot->pilotname);
	$map->update_map_atc($slot->pilotname,$xml);
}
?>
