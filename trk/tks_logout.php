<?php
/**
 * Created by Sublimetxt3.
 * User: Flo
 * Date: 04/12/2016
 * Time: 11:45
 */

//initialisation des clés du pilote
$key = New Key('', $xml->switch->key);

//initialisation et recupération du slot pour le multijoueur
$session_mp = new Session_mp();
$session_mp->get_my_slot ($key->pilot_id);

//si la trace a bien  été détruite
if ($session_mp->clear_slot ($key->pilot_id) == TRUE)
{
	$inter_com->addMessage (2,$msg);//Bonne fin de journée
	$data['loginStatus'] = '0';//on refuse la connexion
}