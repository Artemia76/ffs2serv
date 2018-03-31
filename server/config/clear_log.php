<?php 
//activation de la protection recursive pour les éléments qui seront intégrés
define ('PROTECTIONINCLUDE', TRUE);

	//intégration de fichier de configuration général du site
require_once ('./config_gen.php');

$arrayName = array();
file_put_contents(mark_5.'/bd_light/log.php',$arrayName);//vidage du fichier log

echo '<meta http-equiv="refresh" content="1; URL=./admin.php?pan=2">';
?>