<?php 
//activation de la protection recursive pour les éléments qui seront intégrés
define ('PROTECTIONINCLUDE', TRUE);

//intégration de fichier de configuration général du site
require_once ('./config_gen.php');

$ban_players=unserialize(file_get_contents(mark_5.'/bd_light/ban_players.php'));

if($_GET['action']=='ban')
{
	if(isset($_GET['name']))
	{
		echo mark_5.'/bd_light/player/'.$_GET['name'].'.php';
		$player=require(mark_5.'/bd_light/player/'.$_GET['name'].'.php');

		$to_ban= array(
			'name' => $player['name'], 
			'serial' => trim($player['serial']), 
			);


		var_dump($player);

		$in_ban_list=false;
		foreach ($ban_players as $key => $value)
		{
			if (trim($player['serial'])==trim($value['serial']))
			{
				$in_ban_list=true;
			}
		}

		if ($in_ban_list==false)
		{
			$ban_players[]=$to_ban;
		}
	}
	if(isset($_GET['hist']))
	{
		$players=unserialize(file_get_contents(mark_5.'bd_light/list_player.php'));//ouverture du fichier liste des joueurs
		
		$in_ban_list=false;


		foreach ($players as $key => $value)
		{
			echo trim('<br>**'.$value["serial"]).'//'.trim($_GET['hist']).'**';
			if(trim($value["serial"])==trim($_GET['hist']))
			{
				$to_ban= array(
					'name' => $value['name'], 
					'serial' => trim($value['serial']), 
					);
				echo 'je ban<br>';
			}
			foreach ($ban_players as $key2 => $value2)
			{
				if (trim($value['serial'])==trim($value2['serial']) and (trim($value['serial'])==trim($_GET['hist'])))
				{
					$in_ban_list=true;
					echo 'Mais il est déja ban<br>';
				}
			}
		}

		if ($in_ban_list===false)
		{
			$ban_players[]=$to_ban;
			echo 'ban en cours<br>';
		}


	}

	

}

if($_GET['action']=='uban')
{
	var_dump($ban_players);
	unset($ban_players[$_GET['id']]);
	echo '<br>';
	var_dump($ban_players);
}




file_put_contents(mark_5.'/bd_light/ban_players.php',serialize($ban_players));

echo '<meta http-equiv="refresh" content="1; URL=./admin.php?pan=3">';
?>