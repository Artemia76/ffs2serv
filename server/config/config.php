<?php 
if(isset($_POST["mod"]) and $_POST["mod"]=='config')
{
	//activation de la protection recursive pour les éléments qui seront intégrés
	define ('PROTECTIONINCLUDE', TRUE);

	//intégration de fichier de configuration général du site
	require_once ('./config_gen.php');

	$header="<?php
	if (!defined ('PROTECTIONINCLUDE'))
	{
		die('Accès Interdit');
	}\n\n";

	$footer="?>";

	if (isset($_POST['serv_root']))
	{
		$this_server='define (\'serv_root\', "'.$_POST['serv_root'].'");//adresse http du serveur (automatique)\n';
	}
	else
	{
		$this_server='define (\'serv_root\', "'.serv_root.'");  //adresse http du serveur (automatique)';
	}

	if (isset($_POST['mark_5_path']))
	{
		$this_server=$this_server."\n".'define (\'mark_5_path\', "'.$_POST['mark_5_path'].'");//répertoire d\'installation du mark_5';
	}
	else
	{
		$this_server=$this_server."\n".'define (\'mark_5_path\', "'.mark_5_path.'");  //répertoire d\'installation du mark_5';
	}

	$this_server=$this_server."\n".'define (\'mark_5\', serv_root . mark_5_path);  //adresse compléte du mark_5';



	if (isset($_POST['key']))
	{
		$this_key='define (\'key\', "'.$_POST['key'].'");//Clé primaire de cryptage et décryptage';
	}
	else
	{
		$this_key='define (\'key\', "'.key.'");//Clé primaire de cryptage et décryptage';
	}
	if (isset($_POST['licence']))
	{
		$this_key=$this_key."\n".'define (\'licence\', "'.$_POST['licence'].'");//licence d\'utilisation';
	}
	else
	{
		$this_key=$this_key."\n".'define (\'licence\', "'.key.'");//licence d\'utilisation';
	}

	if (isset($_POST['root_acces']))
	{
		$this_server=$this_server."\n".'define (\'root_acces\', "'.$_POST['root_acces'].'");//code pour l\'administration\n';
	}
	else
	{
		$this_server=$this_server."\n".'define (\'root_acces\', "'.root_acces.'");  //code pour l\'administration\n';
	}

	if (isset($_POST['session_password']))
	{
		$this_session='define (\'session_password\', "'.$_POST['session_password'].'");//mot de passe de la session';
	}
	else
	{
		$this_session='define (\'session_password\', "'.session_password.'");//mot de passe de la session';
	}

	if (isset($_POST['view_pass']))
	{
		$this_session=$this_session."\n".'define (\'view_pass\', '.$_POST['view_pass'].');//Afficher ou pas le mot de passe sur une page';
	}
	else
	{
		$this_session=$this_session."\n".'define (\'view_pass\', '.view_pass.');//Afficher ou pas le mot de passe sur une page';
	}

	if (isset($_POST['max_player']))
	{
		$this_session=$this_session."\n".'define (\'max_player\', "'.$_POST['max_player'].'");//nombre maximum de joueur';
	}
	else
	{
		$this_session=$this_session."\n".'define (\'max_player\', '.max_player.');//nombre maximum de joueur';
	}

	if (isset($_POST['serveur_name']))
	{
		$this_session=$this_session."\n".'define (\'serveur_name\', "'.$_POST['serveur_name'].'");//nom du serveur de jeux';
	}
	else
	{
		$this_session=$this_session."\n".'define (\'serveur_name\', "'.serveur_name.'");//nom du serveur de jeux';
	}

	if ($_POST['debug']==1)
	{
		$this_debug='error_reporting(E_ERROR | E_WARNING | E_PARSE);//débugage activé';
		$this_debug=$this_debug."\n".'define (\'debug\', true);//débugage activé';
	}
	elseif ($_POST['debug']==0)
	{
		$this_debug='error_reporting(0);//débugage désactivé';
		$this_debug=$this_debug."\n".'define (\'debug\', false);//débugage activé';
	}


	

	$config=$this_server."\n\n".$this_key."\n\n".$this_session."\n\n".$this_debug."\n\n";

	$file_config=$header."".$config.""."".$footer;
	
	file_put_contents('./config_gen.php',$file_config);//création de la fiche du joueur se connectant

	echo '<meta http-equiv="refresh" content="5; URL=./admin.php">';
}

else
{
	
	?>
	<form action="./config.php" method="post">
		<div style="color: #ff6600;font-size: 1.25em;">Localisation du logiciel</div>
		<div style="border-style: solid;border-color: darkgray;padding: 5px 5px 0px 5px;margin-bottom: 15px;">
			Adresse http du serveur (automatique) <br><input type="text" name="serv_root" value="<?=$_SERVER["DOCUMENT_ROOT"] ?>" size="100">
			Répertoire d'installation du mark_5<br><input type="text" name="mark_5_path" value="<?=mark_5_path ?>">
			Adresse compléte du mark_5 <br><input type="text" name="" value="<?=mark_5 ?>">
		</div>

		<div style="color: #ff6600;font-size: 1.25em;">Sécurité</div>
		<div style="border-style: solid;border-color: darkgray;padding: 5px 5px 0px 5px;margin-bottom: 15px;">
			Clé primaire de cryptage et décryptage<br><input type="text" name="key" value="<?=key ?>" size="100">
			Code pour l'administration<br>
			<input type="text" name="root_acces" value="<?=root_acces ?>">
			Licence<br><textarea rows="2" cols="100" name="licence"><?=licence ?></textarea>
		</div>


		<div style="color: #ff6600;font-size: 1.25em;">Réglage de la session</div>
		<div style="border-style: solid;border-color: darkgray;padding: 5px 5px 0px 5px;margin-bottom: 15px;">
			Nom du serveur de jeux<br><input type="text" name="serveur_name" value="<?=serveur_name ?>">
			Mot de passe de la session <br><input type="text" name="session_password" value="<?=session_password ?>">
			Afficher ou pas le mot de passe sur une page <br>
			<select name="view_pass">	
				<option value="true" <?php if(view_pass==true){echo 'selected';} ?>>oui</option>
				<option value="false" <?php if(view_pass==false){echo 'selected';} ?>>non</option>
			</select>
			Nombre maximum de joueurs<br>
			<select name="max_player">	
				<option value="5" <?php if(max_player==5){echo 'selected';} ?>>5</option>
				<option value="10" <?php if(max_player==10){echo 'selected';} ?>>10</option>
				<option value="15" <?php if(max_player==15){echo 'selected';} ?>>15</option>
				<option value="20" <?php if(max_player==20){echo 'selected';} ?>>20</option>
				<option value="25" <?php if(max_player==25){echo 'selected';} ?>>25</option>
				<option value="30" <?php if(max_player==30){echo 'selected';} ?>>30</option>
			</select>
		</div>

		<div style="color: #ff6600;font-size: 1.25em;">Add_on</div>
		<div style="border-style: solid;border-color: darkgray;padding: 5px 5px 0px 5px;margin-bottom: 15px;">
			Utilisation de FFS2 pour la reconnaissance des avions<br>
			<select name="FFS2_ask">	
				<option value="true" <?php if( FFS2_ask==true){echo 'selected';} ?>>oui</option>
				<option value="false" <?php if(FFS2_ask==false){echo 'selected';} ?>>non</option>
			</select>
			Clé de FFS2 pour la reconnaissance des avions<br><input type="text" name="FFS2_key" value="<?=FFS2_key ?>">
			URL de FFS2 pour la reconnaissance des avions<br><input type="text" name="FFS2_URL" value="<?=FFS2_URL ?>" size="100">
		</div>

		<div style="color: #ff6600;font-size: 1.25em;">Outils</div>
		<div style="border-style: solid;border-color: darkgray;padding: 5px 5px 0px 5px;margin-bottom: 15px;">
			Débugage<br>
			<select name="debug">	
				<option value="1" <?php if(debug===true){echo 'selected';} ?>>oui</option>
				<option value="0" <?php if(debug===false){echo 'selected';} ?>>non</option>
			</select>
		</div>

		<input type="hidden" name="mod" value="config">

		<button class="button" type="submit">Modifier la configuration</button>
	</form>

	<?php
}
?>