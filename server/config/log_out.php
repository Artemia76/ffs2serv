<?php 
$_SESSION = array();
session_destroy();
setcookie('auth', NULL, -1);
echo "<meta http-equiv='refresh' content='0; URL=./admin.php'>";
?>