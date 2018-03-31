<?php 
$log=unserialize(file_get_contents(mark_5.'bd_light/log.php'));//ouverture du fichier log

foreach ($log as $key => $value) {
	$ip=strval($value['ip']);
	echo $key.':&nbsp;&nbsp;&nbsp;&nbsp; Nom: '.$value['name'].'&nbsp;&nbsp;&nbsp;&nbsp; ip: '.decrypt($ip,key).'&nbsp;&nbsp;&nbsp;&nbsp; date: '.$value['date'].'&nbsp;&nbsp;&nbsp;&nbsp; action: '.$value['op'].'<br>';
}


?>
<a href="./clear_log.php" class="button">Effacer Log du Serveur</a>