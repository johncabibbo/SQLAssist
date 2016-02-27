<?php
ini_set("session.cookie_lifetime","28800"); 
session_set_cookie_params(28800);
session_start();

if ( !isset($_SESSION['login']) ){
	exit();
}

require_once '../db.php';
require_once '../model/SQLStruct.php';

$returnObject = array();
$SQLStructModel = new SQLStruct($mysql_dsn, $mysql_user, $mysql_pass);
$nowDate = date("Y-m-d H:i:s");
$nowDateDay =  date("Y-m-d");

if ( !isset($_POST['databaseName'])
	){
	$returnObject = array("success"=>0,"msg"=>"Invalid Parameters","sessionExists"=>1);
	
} else {
	$_SESSION['tableSelected'] = $_POST['tableName'];
	$returnObject['tableInfo'] = $SQLStructModel->tableInfo($_POST['databaseName'],$_POST['tableName']);
	$returnObject['databaseName'] = $_POST['databaseName'];
	$returnObject['tableName'] = $_POST['tableName'];
	$returnObject['success'] = '1';
	$returnObject['msg'] = '';
}
echo json_encode($returnObject);
?>