<?php
define('DB_SERVER', 'localhost');
define('DB_USERNAME', 'root');
define('DB_PASSWORD', '');
define('DB_NAME', 'gearup');

define('SITE_NAME', 'GEARUP!');
define('BACKGROUND_INTERVAL', 5);

$link = mysqli_connect(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

if($link === false){
    die("ERROR: Could not connect. " . mysqli_connect_error());
}

function start_safe_session() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
}
?>
