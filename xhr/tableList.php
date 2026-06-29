<?php
/*
Description: 	
Inputs:	databaseName
URL:	
/xhr/tableList.php?databaseName=
*/

require_once '../setting.php';

if ( !isset($_SESSION['login']) ){
	exit();
}
require_once '../model/SQLStruct.php';

$returnObject = array();
$db = new \PDO($mysql_dsn, $mysql_user, $mysql_pass);
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
	

	$returnObject['tableCount'] = '0';
	$returnObject['lookUpTableCount'] = '0';

	if ( isset($_SESSION['dbSelected']) && $_SESSION['dbSelected'] != ''){
		
		$statement = $db->prepare("
			SELECT TABLE_COMMENT
			FROM INFORMATION_SCHEMA.TABLES
			WHERE TABLE_SCHEMA = :databaseName;
		");
		if ( $statement->execute(  array(":databaseName"=>$_SESSION['dbSelected'] ) ) ) {
				$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
				$returnObject['tableCount'] = count($rows);
		}

		$statement = $db->prepare("
			SELECT 	TABLE_COMMENT
			FROM 	INFORMATION_SCHEMA.TABLES
			WHERE 	TABLE_SCHEMA = :databaseName
			and 	TABLE_COMMENT like 'Lookup Table%';
		");
		if ( $statement->execute(  array(":databaseName"=>$_SESSION['dbSelected']) ) ) {
			$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
			$returnObject['lookUpTableCount'] = count($rows);
		}

	}

	$_SESSION['dbSelected'] = $_POST['databaseName'];
	$returnObject['tableList'] = $SQLStructModel->tableList($_POST['databaseName']);
	$returnObject['tableNameList'] = $SQLStructModel->tablenameList($_POST['databaseName']);
	$returnObject['tableStatsAll'] = $SQLStructModel->tableStatsAll($_POST['databaseName']);
	$returnObject['databaseName'] = $_POST['databaseName'];

	// Get table comments to identify lookup tables
	$statement = $db->prepare("
		SELECT TABLE_NAME, TABLE_COMMENT 
		FROM INFORMATION_SCHEMA.TABLES
		WHERE TABLE_SCHEMA = :databaseName
	");
	$tableComments = [];
	if ($statement->execute(array(":databaseName" => $_POST['databaseName']))) {
		$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
		foreach ($rows as $row) {
			$tableComments[$row['TABLE_NAME']] = $row['TABLE_COMMENT'];
		}
	}
	$returnObject['tableComments'] = $tableComments;

	$returnObject['success'] = '1';
	$returnObject['msg'] = '';
}
echo json_encode($returnObject);
?>