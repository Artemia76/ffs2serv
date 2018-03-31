<?php
if (!defined ('PROTECTIONINCLUDE'))
{
	die('Accès Interdit');
}

//config de la base de donnée coté tracker
define ('PF_MARK_5_ENR', 'tks_');
define ('DBASE_MARK_5_ENR_DNS', 'mysql:host=localhost; dbname=FFS2_ENR');
define ('DBASE_MARK_5_ENR_SERVER', 'localhost');
define ('DBASE_MARK_5_ENR_NAME', 'FFS2_ENR');
define ('DBASE_MARK_5_ENR_USER', 'ffsimulateur2');
define ('DBASE_MARK_5_ENR_PASS', 'ib7474qe');

//config de TS3
define('TS3_ADRESSE','ts3.ffsimulateur2.fr');
define('TS3_QUERY_PORT','10011');
define('TS3_LOGIN','serveradmin');
define('TS3_PASSWORD','LhHp6L8f');


?>