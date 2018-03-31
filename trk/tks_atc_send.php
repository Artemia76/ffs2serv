<?php
/**
 * Created by Sublimetxt3.
 * User: Flo
 * Date: 04/12/2016
 * Time: 12:30
 */

//initialisation des clés du pilote
$key = New Key('', $xml->switch->key);
//mise à jours de sa date de validité
//$key->update ();

//initialisation et recupération du slot pour le multijoueur
//$session_mp = new Session_mp();
//$session_mp->get_my_slot ($key->pilot_id);
//mise à jours de la date de refresh du slot
//$session_mp->update_my_slot ($key->pilot_id);

//initialisation et recupération du de la position du client
$map = new Data_map();
$map->load ($key->pilot_id);

//si la position du client existe
if ($map->pilot_id != NULL)
{
	//si on actualise la position du client
	$map->update_map_atc ($xml->atc, $key);
}
//sinon si elle est absente
else
{
	//aucune action particuliére, on abandonne
}