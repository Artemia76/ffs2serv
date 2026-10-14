<?php
/*
 * Lance tous les tests : php tests/run.php
 * Sans PHP installé : docker run --rm -v "$PWD":/app -w /app php:8.3-cli php tests/run.php
 */
require __DIR__ . '/bootstrap.php';

if (!extension_loaded ('openssl')) {
	echo "L'extension openssl est requise\n";
	exit (1);
}

echo "PHP " . PHP_VERSION . ", " . OPENSSL_VERSION_TEXT . "\n";
foreach (array ('test_crypto.php', 'test_protocol.php') as $test) {
	try {
		require __DIR__ . '/' . $test;
	}
	catch (Throwable $e) {
		$GLOBALS['test_failures']++;
		echo "  ERREUR " . get_class ($e) . ': ' . $e->getMessage () . ' (' . basename ($e->getFile ()) . ':' . $e->getLine () . ")\n";
	}
}

echo "\n" . $GLOBALS['test_count'] . " vérifications, " . $GLOBALS['test_failures'] . " échec(s)\n";
exit ($GLOBALS['test_failures'] === 0 ? 0 : 1);
?>
