<?php
/*
Description:    Update tableType in tableDef for a given database/table.
Inputs:         databaseName, tableName, tableType
URL:            /xhr/tableDefUpdate.php
*/

require_once '../setting.php';

if ( !isset($_SESSION['login']) ){
	exit();
}
require_once '../model/SQLStruct.php';

$returnObject = array();
$SQLStructModel = new SQLStruct($mysql_dsn, $mysql_user, $mysql_pass, $mysqlSA_dsn, $mysqlSA_user, $mysqlSA_pass);

/* Converts GET to POST variables */
foreach($_GET as $key => $value){
	$_POST[$key] = $value;
}

if ( !isset($_POST['databaseName']) || !isset($_POST['tableName']) || !isset($_POST['tableType']) ){
	$returnObject = array("success"=>0, "msg"=>"Invalid Parameters");
} else {
	$result = $SQLStructModel->tableDefUpdate($_POST['databaseName'], $_POST['tableName'], $_POST['tableType']);
	$returnObject['success'] = $result ? '1' : '0';
	$returnObject['msg'] = $result ? 'Saved' : 'Failed to save';
}

echo json_encode($returnObject);
?>
