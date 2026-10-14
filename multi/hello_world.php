<?php
	if (!defined ('PROTECTIONINCLUDE')) {
		die('Accès Interdit');
	}

	//on signale au logiciel client la prise en charge du cryptage dynamique
	$data_xml['hello'] = 'world';
	//envoi du certificat RSA : le client l'utilise pour crypter sa clé AES
	$data_xml['certificat'] = CertRSA;
?>
