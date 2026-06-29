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

if ( !isset($_SESSION['login']) ){
	$returnObject = array("success"=>0,"msg"=>"","sessionExists"=>0);

} else if ( !isset($_POST['docModelFunctionId']) || !isset($_POST['modelFunctionDesc']) ){
	$returnObject = array("success"=>0,"msg"=>"Invalid Parameters","sessionExists"=>1);
	
} else {
	$update = $SQLStructModel->modelFunctUpdate( $_POST['docModelFunctionId'],$_POST['modelFunctionDesc'] );
	
	$returnObject['success'] = '1';
	$returnObject['msg'] = '';
}
echo json_encode($returnObject);
?>