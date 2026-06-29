<?php
require_once 'setting.php';

if( isset($_SESSION['userId']) ){
	header( 'Location: index.php' );
	exit();
}
unset($_SESSION['userId']);
unset($_SESSION['userTypeId']);
unset($_SESSION['login']);
session_destroy();

header( 'Location: index.php' );
?>