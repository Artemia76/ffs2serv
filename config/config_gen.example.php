<?php
	/*
	 * Modèle de configuration du serveur FFS2play
	 *
	 * Ne pas modifier ce fichier : générer config/config_gen.php avec
	 *     php tools/generate_config.php
	 * qui crée une nouvelle paire de clés RSA et un mot de passe de session.
	 * config/config_gen.php contient des secrets : il n'est jamais versionné.
	 */
	if (!defined ('PROTECTIONINCLUDE')) {
		die('Accès Interdit');
	}
	define ('session_password', "@SESSION_PASSWORD@");//mot de passe de la session, à communiquer aux joueurs
	define ('serveur_name', "@SERVER_NAME@");//nom du serveur de jeux
	define ('debug_ffst', FALSE);//messages de debug renvoyés aux clients
	error_reporting (E_ERROR | E_PARSE);//les avertissements ne doivent pas polluer la réponse XML

	//certificat envoyé aux clients en réponse au "hello" : ils l'utilisent pour crypter leur clé AES
	define ('CertRSA', '@CERTIFICATE@');
	//clé privée correspondant au certificat
	define ('PrivateRSAKey', '@PRIVATE_KEY@');
?>
