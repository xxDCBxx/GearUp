<?php
require_once "connections.php";
start_safe_session();
$_SESSION = array();
session_destroy();
header("location: index.php");
exit;
?>
