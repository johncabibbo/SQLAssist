<?php
class SQLStructMS{
	private $db; //Private to the object
	
	public function __construct($MSserver,$MSuser,$MSpass,$MSdb,$dsnSA,$userSA,$passSA){
		try {
			// $this->db = mssql_connect($server, $user, $pass) or die(print_r(sqlsrv_errors(), true));
			$this->db = mssql_connect($MSserver, $MSuser, $MSpass) or die(print_r('ERROR'));
			mssql_select_db($MSdb, $this->db);
		}
		catch (Exception $e){
			header( 'Location: error.php' );
			exit();
		}
		try {
			$this->dbSA = new \PDO($dsnSA, $userSA, $passSA);
			$this->dbSA->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		}
		catch (Exception $e){
			header( 'Location: error.php' );
			exit();
		}
	}
	
	public function databaseList(){
		$statement = mssql_query("
			SELECT name
			FROM sys.databases 
			order by name
		");
		
		$rows = [];
		$i = 0;
		while ($row = mssql_fetch_assoc($statement)) {
		 	$r = [];
		 	$r['databaseName'] = $row['name'];
		    $rows[$i] = $r;

		 	$i = $i + 1;
		}
		
		return $rows;
	}

	public function tableList($databaseName){
		$SQL = "select id, name from ".$databaseName.".sys.sysobjects WHERE (xtype = 'U') order by name ";
		$statement = mssql_query($SQL);

		$rows = [];
		$i = 0;
		while ($row = mssql_fetch_assoc($statement)) {
		 	$r = [];
		 	$r['databaseName'] = $databaseName;
		 	$r['tableName'] = $row['name'];
		 	$r['id'] = $row['id'];
		    $rows[$i] = $r;

		 	$i = $i + 1;
		}
		
		return $rows;
	}
	
	public function tableInfo($databaseName,$tableId,$tableName){
		$SQL = "SELECT	".$databaseName.".sys.syscolumns.name as columnName, ".$databaseName.".sys.types.name as columnType, length, '' as COLUMN_KEY, '' as EXTRA, '' as ORDINAL_POSITION FROM ".$databaseName.".sys.syscolumns join ".$databaseName.".sys.types on ".$databaseName.".sys.types.user_type_id = ".$databaseName.".sys.syscolumns.xusertype where id = '".$tableId."'";
		$statement = mssql_query($SQL);

		$output = array();
		$col = array();
		$i = 0;
		while ($row = mssql_fetch_assoc($statement)) {
		 	$r = [];
		 	$r['databaseName'] = $databaseName;
		 	$r['tableName'] = $tableName;
		 	$r['columnName'] = $row['columnName'];
			$r['columnType'] = $row['columnType'];
			$r['length'] = $row['length'];
			$r['COLUMN_KEY'] = $row['COLUMN_KEY'];
			$r['EXTRA'] = $row['EXTRA'];
			$r['ORDINAL_POSITION'] = $row['ORDINAL_POSITION'];
			
			$desc = "";
			
			$statementSA = $this->dbSA->prepare("select columnDesc from colDef where databaseName = :databaseName and tableName = :tableName and columnName = :columnName");
			$statementSA->execute( array(":databaseName"=>$databaseName, ":tableName"=>$tableName, ":columnName"=>$r['columnName']) );
			$rowDesc = $statementSA->fetchAll(\PDO::FETCH_ASSOC);
			if ( count($rowDesc) == 1){
				$desc = $rowDesc[0]['columnDesc'];
			}
			$r['desc'] = $desc;
			
		    $col[$i] = $r;

		 	$i = $i + 1;
		}
		$output['columns'] = $col;
		return $output;
	}


	public function tableDesc($databaseName,$tableId,$tableName){
		$output = array();

		$desc = "";
		
		$statementSA = $this->dbSA->prepare("select columnDesc from colDef where databaseName = :databaseName and tableName = :tableName and columnName = 'TABLEDEFINITION'");
		$statementSA->execute( array(":databaseName"=>$databaseName, ":tableName"=>$tableName) );
		$rowDesc = $statementSA->fetchAll(\PDO::FETCH_ASSOC);
		if ( count($rowDesc) == 1){
			$desc = $rowDesc[0]['columnDesc'];
		}
		$output = $desc;
		
		return $output;
	}


	public function colDescUpdate ($databaseName,$tableName,$columnName,$columnDesc){
		$output = array();

		$statementSA = $this->dbSA->prepare("select colDefId from colDef where databaseName = :databaseName and tableName = :tableName and columnName = :columnName");
		$statementSA->execute( array(":databaseName"=>$databaseName, ":tableName"=>$tableName, ":columnName"=>$columnName) );
		$rowCheck = $statementSA->fetchAll(\PDO::FETCH_ASSOC);
		
		if ( count($rowCheck) == 0){
			$statementSA = $this->dbSA->prepare("insert into colDef (databaseName, tableName, columnName, columnDesc) values (:databaseName, :tableName, :columnName, :columnDesc)");
			$statementSA->execute( array(":databaseName"=>$databaseName, ":tableName"=>$tableName, ":columnName"=>$columnName, ":columnDesc"=>$columnDesc) );
			
		} else {
			$statementSA = $this->dbSA->prepare("update colDef set columnDesc = :columnDesc where colDefId = :colDefId");
			$statementSA->execute( array(":columnDesc"=>$columnDesc, ":colDefId"=>$rowCheck[0]['colDefId'] ) );
		}
		
		$output['success'] = 1;
		return $output ;
	}	
}