<?php
/*
Description: 	
Inputs:	databaseName, tableName
URL:	
/xhr/tableInfo.php?databaseName=&tableName=
*/

require_once '../setting.php';

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

// Action
if ( !isset($_POST['databaseName'])
	){
	$returnObject = array("success"=>0,"msg"=>"Invalid Parameters","sessionExists"=>1);
	
} else {
	// Get comment save location from settings (default to 'A' if not set)
	$saveLocation = isset($commentSaveLocation) ? $commentSaveLocation : 'A';

	$_SESSION['dbSelected'] = $_POST['databaseName'];
	$_SESSION['tableSelected'] = $_POST['tableId'];

	// Handle special options - use tableId (value) not tableName (label text)
	// tableId contains: 'ALL', 'ALLTables', or actual table name
	// tableName contains: display label which may differ from value
	$tableIdValue = $_POST['tableId'];

	if ($tableIdValue == 'ALLTables') {
		// ALLTables - return all table descriptions
		$returnObject['allTableDescs'] = $SQLStructModel->allTableDescs($_POST['databaseName'], $saveLocation);
		$returnObject['tableNameList'] = $SQLStructModel->tablenameList($_POST['databaseName']);
		$returnObject['tableStatsAll'] = $SQLStructModel->tableStatsAll($_POST['databaseName']);
		$returnObject['tableTypeAll'] = $SQLStructModel->tableDefGetAll($_POST['databaseName']);
		$returnObject['tableTypeList'] = $SQLStructModel->tableTypeDistinct();
		$returnObject['deprecatedTables'] = $SQLStructModel->deprecatedTables($_POST['databaseName']);
		$returnObject['deprecatedColumns'] = $SQLStructModel->deprecatedColumns($_POST['databaseName']);
		$returnObject['databaseName'] = $_POST['databaseName'];
		$returnObject['tableName'] = $tableIdValue;
		$returnObject['commentSaveLocation'] = $saveLocation;
		$returnObject['success'] = '1';
		$returnObject['msg'] = '';
	} else {
		// ALL or specific table - pass tableId value to model (not label text)
		$returnObject['tableInfo'] = $SQLStructModel->tableInfo($_POST['databaseName'], $tableIdValue, $saveLocation);
		$returnObject['tableDesc'] = $SQLStructModel->tableDesc($_POST['databaseName'], $tableIdValue, $tableIdValue, $saveLocation);
		$returnObject['tableNameList'] = $SQLStructModel->tablenameList($_POST['databaseName']);
		$returnObject['tableTypeList'] = $SQLStructModel->tableTypeDistinct();
		$returnObject['databaseName'] = $_POST['databaseName'];
		$returnObject['tableName'] = $tableIdValue;
		$returnObject['commentSaveLocation'] = $saveLocation;
		$returnObject['success'] = '1';
		$returnObject['msg'] = '';

		// Include row count and size for a specific (non-ALL) table
		if ($tableIdValue !== 'ALL') {
			$returnObject['tableStats'] = $SQLStructModel->tableStats($_POST['databaseName'], $tableIdValue);
			$returnObject['tableTriggers'] = $SQLStructModel->tableTriggers($_POST['databaseName'], $tableIdValue);
			$returnObject['tableType'] = $SQLStructModel->tableDefGet($_POST['databaseName'], $tableIdValue);
			$returnObject['pkFkReferences'] = $SQLStructModel->pkFkReferences($_POST['databaseName'], $tableIdValue);
			$returnObject['lookupIconRows'] = $SQLStructModel->lookupIconRows($_POST['databaseName'], $tableIdValue);
		}
	}
}
echo json_encode($returnObject);
?>