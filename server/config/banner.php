<div style="color: #ff6600;font-size: 1.25em;">Tous les joueurs s'étant connectés</div>
<div style="border-style: solid;border-color: darkgray;padding: 5px 5px 0px 5px;margin-bottom: 15px;">
	<?php 
	$players=unserialize(file_get_contents(mark_5.'bd_light/list_player.php'));//ouverture du fichier log

	foreach ($players as $key => $value) {
		$ip=strval($value['ip']);
		echo $key.':&nbsp;&nbsp;&nbsp;&nbsp; Nom: '.$value['name'].'&nbsp;&nbsp;&nbsp;&nbsp; ip: '.decrypt($ip,key).'&nbsp;&nbsp;&nbsp;&nbsp;  id: '.$value['serial'].'&nbsp;&nbsp;&nbsp;&nbsp;<a href="./ban.php?action=ban&hist='.$value['serial'].'" class="button tiny alert ">Bannir ce joueur</a><br>';
	}
	?>

	<a href="./clear_list.php" class="button">Effacer la liste des joueurs</a>
</div>
<div style="color: #ff6600;font-size: 1.25em;">Tous les joueurs connectés</div>
<div style="border-style: solid;border-color: darkgray;padding: 5px 5px 0px 5px;margin-bottom: 15px;">
	<?php 
	$players=Get_FFST_Files (mark_5.'bd_light/player',  array ("php"), $recursif = true);

	$data_multiplayer=array();//création du tableau multijoeur
	if (is_array($data_multiplayer))
	{
		foreach ($players as $key => $value)//analyse de tous les fichiers joeurs existants
		{
			if(filemtime($value)>(time()-60)) //on vérifie que ce fichier et moins vieux que 60 secondes
			{
				$name=str_replace(mark_5.'bd_light/player/', '', $value);
				$name=str_replace('.php', '', $name);
				$player=require($value);

				echo $key.':&nbsp;&nbsp;&nbsp;&nbsp; Nom: '.$name.'&nbsp;&nbsp;&nbsp;&nbsp;<a href="./ban.php?action=ban&name='.$name.'" class="button tiny alert ">Bannir ce joueur</a><br>';
			}
			else//sinon on l'efface
			{
				unlink($value);
			}

		}
	}

	?>
</div>
<div style="color: #ff6600;font-size: 1.25em;">Tous les joueurs bannis</div>
<div style="border-style: solid;border-color: darkgray;padding: 5px 5px 0px 5px;margin-bottom: 15px;">
	<?php 
	$players_ban=unserialize(file_get_contents(mark_5.'bd_light/ban_players.php'));//ouverture du fichier log

	if (is_array($players_ban))
	{
		foreach ($players_ban as $key => $value)
		{
			echo 'Nom: '.$value['name'].'&nbsp;&nbsp;&nbsp;&nbsp;   id: '.$value['serial'].'&nbsp;&nbsp;&nbsp;&nbsp;<a href="./ban.php?action=uban&id='.$key.'" class="button tiny success ">Débannir ce joueur</a><br>';
		}
	}

	
	?>


</div>