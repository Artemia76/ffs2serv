<?php
/*
 * Génération de config/config_gen.php : nouvelle paire de clés RSA 2048 bits,
 * certificat auto-signé et mot de passe de session.
 *
 * Usage (ligne de commande uniquement) :
 *     php tools/generate_config.php [--password=<mot de passe>] [--name=<nom du serveur>] [--days=3650] [--force]
 *
 * Les clients FFS2play récupèrent le certificat à chaque connexion ("hello") :
 * changer les clés ne nécessite aucune mise à jour des clients.
 */
if (PHP_SAPI !== 'cli') {
	http_response_code (403);
	die('Accès Interdit');
}

$options = getopt ('', array ('password:', 'name:', 'days:', 'force', 'output:'));
$root     = dirname (__DIR__);
$template = $root . '/config/config_gen.example.php';
$output   = isset ($options['output']) ? $options['output'] : $root . '/config/config_gen.php';
$days     = isset ($options['days']) ? max (1, intval ($options['days'])) : 3650;
$name     = isset ($options['name']) ? $options['name'] : 'Serveur FFS2play';
$password = isset ($options['password']) ? $options['password'] : bin2hex (random_bytes (6));

if (file_exists ($output) and !isset ($options['force'])) {
	fwrite (STDERR, "$output existe déjà (utiliser --force pour le remplacer)\n");
	exit (1);
}

$key = openssl_pkey_new (array ('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
if ($key === false) {
	fwrite (STDERR, "Génération de la clé RSA impossible : " . openssl_error_string () . "\n");
	exit (1);
}
$csr  = openssl_csr_new (array ('commonName' => $name, 'organizationName' => 'FFS2play'), $key, array ('digest_alg' => 'sha256'));
$cert = openssl_csr_sign ($csr, NULL, $key, $days, array ('digest_alg' => 'sha256'), random_int (1, PHP_INT_MAX));
if ($cert === false or !openssl_x509_export ($cert, $cert_pem) or !openssl_pkey_export ($key, $key_pem)) {
	fwrite (STDERR, "Génération du certificat impossible : " . openssl_error_string () . "\n");
	exit (1);
}

//les valeurs sont insérées dans des chaînes PHP entre apostrophes / guillemets
$escape_single = function ($value) { return str_replace (array ('\\', "'"), array ('\\\\', "\\'"), $value); };
$escape_double = function ($value) { return addcslashes ($value, "\\\"\$"); };

$config = strtr (file_get_contents ($template), array (
	'@SESSION_PASSWORD@' => $escape_double ($password),
	'@SERVER_NAME@'      => $escape_double ($name),
	'@CERTIFICATE@'      => $escape_single ($cert_pem),
	'@PRIVATE_KEY@'      => $escape_single ($key_pem),
));
//retrait de l'en-tête du modèle
$config = preg_replace ('#/\*.*?\*/\s*#s', '', $config, 1);

if (file_put_contents ($output, $config) === false) {
	fwrite (STDERR, "Écriture de $output impossible\n");
	exit (1);
}
@chmod ($output, 0600);

$info = openssl_x509_parse ($cert_pem);
echo "Configuration écrite dans $output\n";
echo "  serveur              : $name\n";
echo "  mot de passe session : $password\n";
echo "  certificat valide    : jusqu'au " . date ('Y-m-d', $info['validTo_time_t']) . "\n";
?>
