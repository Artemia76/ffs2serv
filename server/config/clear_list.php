<?php 
//activation de la protection recursive pour les éléments qui seront intégrés
define ('PROTECTIONINCLUDE', TRUE);

	//intégration de fichier de configuration général du site
require_once ('./config_gen.php');

file_put_contents(mark_5.'/bd_light/list_player.php','');//vidage du fichier joueur

echo '<meta http-equiv="refresh" content="1; URL=./admin.php?pan=3">';
?>