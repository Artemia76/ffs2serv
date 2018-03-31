<?php 
session_start();

//activation de la protection recursive pour les éléments qui seront intégrés
define ('PROTECTIONINCLUDE', TRUE);

//intégration de fichier de configuration général du site
require_once ('./config_gen.php');

//intégration du compilateur de sortie  XML et de ses outils
require_once (mark_5.'./core/function/tracker.php');

//intégration du gestionnaire de class site
require_once (mark_5.'./core/app/Auto_loader.php');

?>
<html class="" lang="fr">
<head>
	<meta charset="utf-8">
	<title>Configuration du serveur <?=serveur_name ?></title>

	<meta name="description" content="La Simulation autrement" />

	<!--CSS de foundation -->
	<link rel="stylesheet" href="<?=mark_5_path ?>include/foundation-6/css/foundation.css"/>
	<!--Icone des boutons de foundation -->
	<link rel="icon" href="<?=mark_5_path ?>/include/foundation-6/assets/img/icons/favicon.ico" type="image/x-icon">
	<!--Correctif additionel du CSS de foundation -->
	<link rel="stylesheet" href="<?=mark_5_path ?>/include/css/style1.css"/>
	<!--CSS pour les tableaux à trie dynamique -->
	<link rel="stylesheet" href="<?=mark_5_path ?>/include/css/tab_sort.css"/>
	<!--CSS pour la gestion des Icone des boutons de foundation -->
	<link rel="stylesheet" href="<?=mark_5_path ?>/include/foundation-6/icone/foundation-icons.css"/>
	<!--script pour jquery -->
	<script type="text/javascript" src="<?=mark_5_path ?>/include/foundation-6/js/vendor/jquery.min.js"></script>
</head>
<?php

//demande de déconnexion
if (isset($_GET["state"]) && $_GET["state"] == "logout")
{
	$_SESSION = array();
	session_destroy();
	setcookie('auth', NULL, -1);
	echo "<meta http-equiv='refresh' content='0; URL=./admin.php'>";
}

// si l'utilisateur n'est pas connecté
if (!$_SESSION['Connect'] && !isset($_POST['login'])&& !isset($_COOKIE['auth']))
{
	$_SESSION['Connect']=FALSE;
}
else if(isset($_POST['login']) && isset($_POST['password']))
{
	if($_POST['login']=='admin' and $_POST['password']==root_acces)
	{
		$admin=New stdClass();
		$admin->id='0';
		$admin->username=$_POST['login'];
		$admin->password=$_POST['password'];

		$_SESSION['Auth'] = (array)$admin;
		$_SESSION['Connect']=TRUE;
		if(isset($_POST['remember']))
		{
			setcookie('auth', $admin->id . '-----' . sha1($admin->username . $admin->password ), time() + 3600 * 24 * 3, '/', '', false, true);
		}
	}
}
else if(isset($_COOKIE['auth']))
{
	$auth = $_COOKIE['auth'];
	$auth = explode('-----', $auth);
	
	$admin=New stdClass();
	$admin->id='0';
	$admin->username='admin';
	$admin->password=root_acces;

	$key = sha1('admin' . root_acces );
	if($key == $auth[1])
	{
		echo "<br>connexion possible";
		$_SESSION['Auth'] = (array)$user;
		$_SESSION['Connect']=TRUE;
	}

}
if ($_SESSION['Connect']==TRUE)
{
	?>
	<body>
		<style type="text/css">
			body {
				background: rgb(0, 0, 0) ;
			}
		</style>
		<div id="main_content_ps" >
			<div class="main">
				<div class="row">
					<div class="medium-12 columns">
						<ul class="tabs" data-tabs id="example-tabs">
							<li class="tabs-title <?php if (($_GET['pan']==1) or !isset($_GET['pan'])){echo 'is-active';} ?>"><a href="#panel1" aria-selected="true">Configuration principale</a></li>
							<li class="tabs-title <?php if (($_GET['pan']==2)){echo 'is-active';} ?>"><a href="#panel2">Log du Serveur</a></li>
							<li class="tabs-title <?php if (($_GET['pan']==3)){echo 'is-active';} ?>"><a href="#panel3">Liste des joueurs</a></li>
							<li style="display: block;padding: 1.25rem 1.5rem;line-height: 1;font-size: 12px;"><a href="./admin.php?state=logout">Déconnecter</a></li>
						</ul>
					</div>
				</div>
				<div class="row ">
					<div class="medium-12 columns">
						<div class="tabs-content" data-tabs-content="example-tabs" style="background: #292929;">
							<div class="tabs-panel <?php if (($_GET['pan']==1) or !isset($_GET['pan'])){echo 'is-active';} ?>" id="panel1">
								<?php 
								include ('./config.php');
								?>
							</div>
							<div class="tabs-panel <?php if (($_GET['pan']==2)){echo 'is-active';} ?>" id="panel2">
								<?php 
								include ('./log.php');
								?>
							</div>
							<div class="tabs-panel <?php if (($_GET['pan']==3)){echo 'is-active';} ?>" id="panel3">
								<?php 
								include ('./banner.php');
								?>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</body>
	<?php
}
else
{
	?>
	<body><style type="text/css">
		body {
			background: rgb(0, 0, 0) ;
		}
	</style>
	<div id="main_content_ps" >
		<div class="main">
			<div class="row">
				<div class="small-4 small-centered columns">
					<form action="./admin.php" method="post">
						<div style="color: #ff6600;font-size: 1.25em;">Authentification</div>
						<div style="border-style: solid;border-color: darkgray;padding: 5px 5px 0px 5px;margin-bottom: 15px;">
							Login <br><input type="text" name="login">
							Mot de passe<br><input type="password" name="password">
						</div>

						<button class="button" type="submit">Se connecter</button>
					</form>
				</div>
			</div>
		</div>
	</div>
</body>
<?php
}
?>

<!--script pour les tableaux avec trie dynamique -->
<script type="text/javascript" src="<?=mark_5_path ?>include/js/jquery.dataTables.min.js"></script>
<!--script pour les tableaux avec trie dynamique -->
<script type="text/javascript" src="<?=mark_5_path ?>include/js/dataTables.foundation.min.js"></script>
<!--script d'interaction de founadtion -->
<script type="text/javascript" src="<?=mark_5_path ?>include/foundation-6/js/vendor/what-input.min.js"></script>
<!--script de fonctionnement général de founadtion -->
<script type="text/javascript" src="<?=mark_5_path ?>include/foundation-6/js/foundation.min.js"></script>
<script type="text/javascript">
	$(document).foundation();
</script>