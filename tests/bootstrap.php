<?php
/*
 * Mini framework de test (aucune dépendance) : php tests/run.php
 */
define ('PROTECTIONINCLUDE', 'TRUE');
error_reporting (E_ALL);
ini_set ('display_errors', '1');

$GLOBALS['test_failures'] = 0;
$GLOBALS['test_count'] = 0;

//toute alerte PHP est une erreur de test
set_error_handler (function ($severity, $message, $file, $line) {
	//alerte masquée volontairement avec @
	if (!(error_reporting () & $severity)) {
		return false;
	}
	throw new ErrorException ($message, 0, $severity, $file, $line);
});

function check ($condition, $label)
{
	$GLOBALS['test_count']++;
	if ($condition) {
		echo "  ok   $label\n";
	}
	else {
		$GLOBALS['test_failures']++;
		echo "  FAIL $label\n";
	}
}

function check_equal ($expected, $actual, $label)
{
	check ($expected === $actual, $label . ($expected === $actual ? '' : ' (attendu ' . var_export ($expected, TRUE) . ', obtenu ' . var_export ($actual, TRUE) . ')'));
}

function temp_dir ($prefix)
{
	$dir = sys_get_temp_dir () . '/' . $prefix . bin2hex (random_bytes (4));
	mkdir ($dir, 0700, TRUE);
	return $dir;
}

function remove_dir ($dir)
{
	foreach (glob ($dir . '/*') ?: array () as $file) {
		is_dir ($file) ? remove_dir ($file) : unlink ($file);
	}
	@rmdir ($dir);
}

/**
 * Génère un certificat et sa clé privée comme tools/generate_config.php
 */
function make_rsa_pair ()
{
	$key = openssl_pkey_new (array ('private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA));
	$csr = openssl_csr_new (array ('commonName' => 'test'), $key, array ('digest_alg' => 'sha256'));
	$cert = openssl_csr_sign ($csr, NULL, $key, 1, array ('digest_alg' => 'sha256'));
	openssl_x509_export ($cert, $cert_pem);
	openssl_pkey_export ($key, $key_pem);
	return array ($cert_pem, $key_pem);
}
?>
