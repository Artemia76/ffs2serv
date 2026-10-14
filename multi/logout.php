<?php
	if (!defined ('PROTECTIONINCLUDE')) {
		die('Accès Interdit');
	}

	//suppression du slot du joueur : il disparaît immédiatement de la liste des autres joueurs
	$slot = Session_mp::find_by_key ($xml->switch->key);
	if ($slot !== NULL) {
		$slot->remove ();
	}
	$inter_com->addMessage (2, $msg);//Bonne fin de journée
?>
