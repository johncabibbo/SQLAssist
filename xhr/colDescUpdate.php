<?php

require_once '../setting.php';

// Debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ( !isset($_SESSION['login']) ){
	exit();
}
require_once '../model/SQLStruct.php';

$returnObject = array();
$SQLStructModel = new SQLStruct($mysql_dsn, $mysql_user, $mysql_pass, $mysqlSA_dsn, $mysqlSA_user, $mysqlSA_pass);
$nowDate = date("Y-m-d H:i:s");
$nowDateDay =  date("Y-m-d");

/* Converts GET to POST variables*/
foreach($_GET as $key => $value){
  	$_POST[$key] = $value;
}

if ( !isset($_POST['databaseName']) || !isset($_POST['tableName']) || !isset($_POST['columnName']) || !isset($_POST['desc']) ){
	$returnObject = array("success"=>0,"msg"=>"Invalid Parameters","sessionExists"=>1);

} else {
	// Get comment save location from settings (default to 'A' if not set)
	$saveLocation = isset($commentSaveLocation) ? $commentSaveLocation : 'A';

	$update = $SQLStructModel->colDescUpdate($_POST['databaseName'],$_POST['tableName'],$_POST['columnName'],$_POST['desc'],$saveLocation);

	$returnObject['success'] = '1';
	$returnObject['msg'] = '';
	$returnObject['savedToSA'] = isset($update['savedToSA']) ? $update['savedToSA'] : false;
	$returnObject['savedToTarget'] = isset($update['savedToTarget']) ? $update['savedToTarget'] : false;

	// Include any error messages from target database update
	if (isset($update['targetError'])) {
		$returnObject['targetError'] = $update['targetError'];
	}
}
echo json_encode($returnObject);
?>