<?php
require_once '../setting.php';

$returnObject = array();

if ( !isset($_SESSION['login']) ){
	$returnObject['success'] = '0';
} else {
	$returnObject['success'] = '1';
}

echo json_encode($returnObject);
?>