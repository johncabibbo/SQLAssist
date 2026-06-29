<?php
/*
databaseList
tableList
columnList
tableDesc
colDescUpdate
*/
class SQLStruct{
	private $db; //Private to the object
	
	public function __construct($dsn,$user,$pass,$dsnSA,$userSA,$passSA){
		try {
			$this->db = new \PDO($dsn, $user, $pass);
			$this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		}
		catch (Exception $e){
			header( 'Location: error.php?msg=Connection 1 Failed' );
			exit();
		}

		try {
			$this->dbSA = new \PDO($dsnSA, $userSA, $passSA);
			$this->dbSA->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		}
		catch (Exception $e){
			header( 'Location: error.php?msg=Connection 2 Failed' );
			exit();
		}
	}
	
	public function databaseList(){
		$statement = $this->db->prepare("
			select SCHEMA_NAME as databaseName
			from information_schema.SCHEMATA
			where SCHEMA_NAME != 'information_schema'
			and 	SCHEMA_NAME != 'performance_schema'
			and 	SCHEMA_NAME != 'mysql'
			and 	SCHEMA_NAME != 'sys'
			and 	SCHEMA_NAME != 'dbo'
			and 	SCHEMA_NAME != 'SQLAssist'
			and 	SCHEMA_NAME != 'mbrteamfl_model'
			and 	SCHEMA_NAME != 'teamfl_org'
			order by SCHEMA_NAME
		");
		try {
			if ( $statement->execute() ) {
				$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
				return $rows;
			}
		}
		catch(Exception $e){
			return 0;
		}
		return 0;
	}

	public function tableList($databaseName){
		$statement = $this->db->prepare("
			select 	TABLE_SCHEMA as databaseName, TABLE_NAME as tableName, 
					left(TABLE_COMMENT,12) as TABLE_COMMENT_12 
			from 	information_schema.TABLES
			where 	TABLE_TYPE = 'BASE TABLE'
			and 	TABLE_SCHEMA = :databaseName
			and 	TABLE_NAME not like 'Old%'
			and 	TABLE_NAME != 'CDATA'
			and 	TABLE_NAME != 'CGLOBAL'
			order by TABLE_NAME
		");
		try {
			if ( $statement->execute( array(":databaseName"=>$databaseName) ) ) {
				$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
				return $rows;
			}
		}
		catch(\PDOException $e){
			echo "Query Failed".$e;
		}
		return 0;
	}

	public function tablenameList($databaseName){
		$statement = $this->db->prepare("
			select 	distinct(TABLE_NAME) as tableName
			from 	information_schema.TABLES
			where 	TABLE_TYPE = 'BASE TABLE'
			and 	TABLE_SCHEMA = :databaseName
			and 	TABLE_NAME not like 'Old%'
			and 	TABLE_NAME != 'CDATA'
			and 	TABLE_NAME != 'CGLOBAL'
			order by TABLE_NAME
		");
		try {
			if ( $statement->execute( array(":databaseName"=>$databaseName) ) ) {
				$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
				return $rows;
			}
		}
		catch(\PDOException $e){
			echo "Query Failed".$e;
		}
		return 0;
	}
	
	public function tableInfo($databaseName,$tableName,$saveLocation='A'){
		$SQLFilter = array();

		// Include COLUMN_COMMENT from information_schema for option B
		if ( $tableName == 'ALL'){
			$statement = $this->db->prepare("
				select     TABLE_SCHEMA as databaseName,
					TABLE_Name as tableName,
					COLUMN_NAME as columnName,
					DATA_TYPE as columnType,
					COLUMN_TYPE as columnTypeFull,
					case
						when CHARACTER_MAXIMUM_LENGTH is not null then CHARACTER_MAXIMUM_LENGTH
						else NUMERIC_PRECISION
					end as length,
					IS_NULLABLE as isNullable,
					COLUMN_DEFAULT as columnDefault,
					COLUMN_KEY,
					EXTRA,
					ORDINAL_POSITION,
					COLUMN_COMMENT as nativeComment
				from     information_schema.COLUMNS
				where    TABLE_SCHEMA = :databaseName
				order by TABLE_NAME, ORDINAL_POSITION
			");
			$SQLFilter[':databaseName'] = $databaseName;

		} else {
			$statement = $this->db->prepare("
				select     TABLE_SCHEMA as databaseName,
					TABLE_Name as tableName,
					COLUMN_NAME as columnName,
					DATA_TYPE as columnType,
					COLUMN_TYPE as columnTypeFull,
					case
						when CHARACTER_MAXIMUM_LENGTH is not null then CHARACTER_MAXIMUM_LENGTH
						else NUMERIC_PRECISION
					end as length,
					IS_NULLABLE as isNullable,
					COLUMN_DEFAULT as columnDefault,
					COLUMN_KEY,
					EXTRA,
					ORDINAL_POSITION,
					COLUMN_COMMENT as nativeComment
				from     information_schema.COLUMNS
				where    TABLE_NAME = :tableName
				and 	 TABLE_SCHEMA = :databaseName
				order by ORDINAL_POSITION
			");
			$SQLFilter[':databaseName'] = $databaseName;
			$SQLFilter[':tableName'] = $tableName;
		}

		try {
			if ( $statement->execute($SQLFilter) ){
				$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

				$output = array();
				$col = array();
				$i = 0;
				foreach ($rows as $row) {
				 	$r = [];
				 	$r['databaseName'] = $databaseName;
				 	$r['tableName'] = $row['tableName'];
				 	$r['columnName'] = $row['columnName'];
					$r['columnType'] = $row['columnType'];
					$r['columnTypeFull'] = $row['columnTypeFull'];
					$r['length'] = $row['length'];
					$r['isNullable'] = $row['isNullable'];
					$r['columnDefault'] = $row['columnDefault'];
					$r['COLUMN_KEY'] = $row['COLUMN_KEY'];
					$r['EXTRA'] = $row['EXTRA'];
					$r['ORDINAL_POSITION'] = $row['ORDINAL_POSITION'];

					$desc = "";
					$saDesc = "";
					$nativeDesc = $row['nativeComment'] ?? '';

					// Get description based on save location setting
					if ($saveLocation == 'B') {
						// Read from target database native comment only
						$desc = $nativeDesc;
					} else {
						// Read from SQLAssist database (options A and C)
						$statementSA = $this->dbSA->prepare("select columnDesc from colDef where databaseName = :databaseName and tableName = :tableName and columnName = :columnName");
						$statementSA->execute( array(":databaseName"=>$databaseName, ":tableName"=>$r['tableName'], ":columnName"=>$r['columnName']) );
						$rowDesc = $statementSA->fetchAll(\PDO::FETCH_ASSOC);
						if ( count($rowDesc) == 1){
							$saDesc = $rowDesc[0]['columnDesc'];
						}

						// For option C, check if both have data and differ
						if ($saveLocation == 'C') {
							if (!empty($saDesc) && !empty($nativeDesc) && $saDesc !== $nativeDesc) {
								// Both have data but differ - show comparison format
								$desc = "-- SQL Assist Entry\n" . $saDesc . "\n----------------------------------------\n-- Target Database Entry\n" . $nativeDesc;
							} elseif (!empty($saDesc)) {
								$desc = $saDesc;
							} else {
								$desc = $nativeDesc;
							}
						} else {
							$desc = $saDesc;
						}
					}
					$r['desc'] = $desc;
					$r['saDesc'] = $saDesc;
					$r['nativeDesc'] = $nativeDesc;

				    $col[$i] = $r;

				 	$i = $i + 1;
				}
				$output['columns'] = $col;
				return $output;
			}
		}
		catch(\PDOException $e){
			echo "Query Failed".$e;
		}
		return 0;
	}
	
	public function tableDesc($databaseName,$tableId,$tableName,$saveLocation='A'){
		$output = array();

		$desc = "";
		$saDesc = "";
		$nativeDesc = $this->getNativeTableComment($databaseName, $tableName);

		// Get description based on save location setting
		if ($saveLocation == 'B') {
			// Read from target database native table comment only
			$desc = $nativeDesc;
		} else {
			// Read from SQLAssist database (options A and C)
			$statementSA = $this->dbSA->prepare("select columnDesc from colDef where databaseName = :databaseName and tableName = :tableName and columnName = 'TABLEDEFINITION'");
			$statementSA->execute( array(":databaseName"=>$databaseName, ":tableName"=>$tableName) );
			$rowDesc = $statementSA->fetchAll(\PDO::FETCH_ASSOC);
			if ( count($rowDesc) == 1){
				$saDesc = $rowDesc[0]['columnDesc'];
			}

			// For option C, check if both have data and differ
			if ($saveLocation == 'C') {
				if (!empty($saDesc) && !empty($nativeDesc) && $saDesc !== $nativeDesc) {
					// Both have data but differ - show comparison format
					$desc = "-- SQL Assist Entry\n" . $saDesc . "\n----------------------------------------\n-- Target Database Entry\n" . $nativeDesc;
				} elseif (!empty($saDesc)) {
					$desc = $saDesc;
				} else {
					$desc = $nativeDesc;
				}
			} else {
				$desc = $saDesc;
			}
		}
		$output = $desc;

		return $output;
	}

	/**
	 * Get table descriptions for ALL tables in a database
	 * Returns array with tableName and tableDesc for each table
	 */
	public function allTableDescs($databaseName, $saveLocation='A') {
		$output = array();

		// Get list of all tables
		$tables = $this->tablenameList($databaseName);

		if ($tables && is_array($tables)) {
			foreach ($tables as $table) {
				$tableName = $table['tableName'];
				$desc = $this->tableDesc($databaseName, 0, $tableName, $saveLocation);
				$output[] = array(
					'tableName' => $tableName,
					'tableDesc' => $desc
				);
			}
		}

		return $output;
	}

	/**
	 * Get native table comment from INFORMATION_SCHEMA
	 */
	private function getNativeTableComment($databaseName, $tableName) {
		try {
			$statement = $this->db->prepare("
				SELECT TABLE_COMMENT
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = :databaseName
				AND TABLE_NAME = :tableName
			");
			$statement->execute(array(
				":databaseName" => $databaseName,
				":tableName" => $tableName
			));
			$row = $statement->fetch(\PDO::FETCH_ASSOC);
			return $row ? ($row['TABLE_COMMENT'] ?? '') : '';
		} catch (\PDOException $e) {
			error_log("Failed to get native table comment: " . $e->getMessage());
			return '';
		}
	}


	/**
	 * Get row count and size in MB for a table from INFORMATION_SCHEMA
	 */
	public function tableStats($databaseName, $tableName) {
		try {
			$statement = $this->db->prepare("
				SELECT
					TABLE_ROWS as rowCount,
					ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as sizeMB
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = :databaseName
				AND TABLE_NAME = :tableName
			");
			$statement->execute(array(
				":databaseName" => $databaseName,
				":tableName"    => $tableName
			));
			$row = $statement->fetch(\PDO::FETCH_ASSOC);
			return $row ? array(
				'rowCount' => $row['rowCount'] ?? 0,
				'sizeMB'   => $row['sizeMB']   ?? 0
			) : array('rowCount' => 0, 'sizeMB' => 0);
		} catch (\PDOException $e) {
			return array('rowCount' => 0, 'sizeMB' => 0);
		}
	}

	/**
	 * Get row count and size in MB for all tables in a database, keyed by tableName
	 */
	public function tableStatsAll($databaseName) {
		try {
			$statement = $this->db->prepare("
				SELECT
					TABLE_NAME as tableName,
					TABLE_ROWS as rowCount,
					ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as sizeMB
				FROM information_schema.TABLES
				WHERE TABLE_SCHEMA = :databaseName
				AND TABLE_TYPE = 'BASE TABLE'
			");
			$statement->execute(array(":databaseName" => $databaseName));
			$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
			$result = array();
			foreach ($rows as $row) {
				$result[$row['tableName']] = array(
					'rowCount' => $row['rowCount'] ?? 0,
					'sizeMB'   => $row['sizeMB']   ?? 0
				);
			}
			return $result;
		} catch (\PDOException $e) {
			return array();
		}
	}

	public function colDescUpdate ($databaseName,$tableName,$columnName,$columnDesc,$saveLocation='A'){
		$output = array();
		$output['success'] = 1;
		$output['savedToSA'] = false;
		$output['savedToTarget'] = false;

		// Save to SQL Assist database (options A and C)
		if ($saveLocation == 'A' || $saveLocation == 'C') {
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
			$output['savedToSA'] = true;

			// Ensure table exists in tableDef
			$this->ensureTableInTableDef($databaseName, $tableName);
		}

		// Save to target database (options B and C)
		if ($saveLocation == 'B' || $saveLocation == 'C') {
			try {
				if ($columnName == 'TABLEDEFINITION') {
					// Update table comment
					$result = $this->updateTableComment($databaseName, $tableName, $columnDesc);
				} else {
					// Update column comment
					$result = $this->updateColumnComment($databaseName, $tableName, $columnName, $columnDesc);
				}
				$output['savedToTarget'] = $result;
				if (!$result) {
					$output['targetError'] = 'Failed to update comment in target database';
				}
			} catch (Exception $e) {
				$output['savedToTarget'] = false;
				$output['targetError'] = $e->getMessage();
			}
		}

		return $output ;
	}

	/**
	 * Update table comment in target database using ALTER TABLE
	 */
	private function updateTableComment($databaseName, $tableName, $comment) {
		try {
			// Escape the comment for SQL (replace single quotes)
			$escapedComment = str_replace("'", "''", $comment);

			// Build and execute ALTER TABLE statement
			$sql = "ALTER TABLE `{$databaseName}`.`{$tableName}` COMMENT = '{$escapedComment}'";
			$statement = $this->db->prepare($sql);
			$statement->execute();
			return true;
		} catch (\PDOException $e) {
			error_log("Failed to update table comment: " . $e->getMessage());
			return false;
		}
	}

	/**
	 * Update column comment in target database using ALTER TABLE MODIFY COLUMN
	 */
	private function updateColumnComment($databaseName, $tableName, $columnName, $comment) {
		try {
			// First, get the full column definition from INFORMATION_SCHEMA
			$statement = $this->db->prepare("
				SELECT
					COLUMN_NAME,
					COLUMN_TYPE,
					IS_NULLABLE,
					COLUMN_DEFAULT,
					EXTRA,
					CHARACTER_SET_NAME,
					COLLATION_NAME
				FROM information_schema.COLUMNS
				WHERE TABLE_SCHEMA = :databaseName
				AND TABLE_NAME = :tableName
				AND COLUMN_NAME = :columnName
			");
			$statement->execute(array(
				":databaseName" => $databaseName,
				":tableName" => $tableName,
				":columnName" => $columnName
			));
			$columnInfo = $statement->fetch(\PDO::FETCH_ASSOC);

			if (!$columnInfo) {
				error_log("Column not found: {$databaseName}.{$tableName}.{$columnName}");
				return false;
			}

			// Build the MODIFY COLUMN statement
			$columnType = $columnInfo['COLUMN_TYPE'];
			$nullable = ($columnInfo['IS_NULLABLE'] == 'YES') ? 'NULL' : 'NOT NULL';

			// Handle default value
			$default = '';
			if ($columnInfo['COLUMN_DEFAULT'] !== null) {
				// Check if default needs quotes (non-numeric, non-function values)
				$defaultVal = $columnInfo['COLUMN_DEFAULT'];
				if (strtoupper($defaultVal) == 'CURRENT_TIMESTAMP' ||
					strtoupper($defaultVal) == 'NULL' ||
					is_numeric($defaultVal)) {
					$default = "DEFAULT {$defaultVal}";
				} else {
					$default = "DEFAULT '" . str_replace("'", "''", $defaultVal) . "'";
				}
			} elseif ($columnInfo['IS_NULLABLE'] == 'YES') {
				$default = "DEFAULT NULL";
			}

			// Handle EXTRA (like AUTO_INCREMENT, ON UPDATE CURRENT_TIMESTAMP)
			$extra = $columnInfo['EXTRA'] ? $columnInfo['EXTRA'] : '';

			// Escape the comment
			$escapedComment = str_replace("'", "''", $comment);

			// Build the full SQL
			$sql = "ALTER TABLE `{$databaseName}`.`{$tableName}` MODIFY COLUMN `{$columnName}` {$columnType} {$nullable}";
			if ($default) {
				$sql .= " {$default}";
			}
			if ($extra) {
				$sql .= " {$extra}";
			}
			$sql .= " COMMENT '{$escapedComment}'";

			$statement = $this->db->prepare($sql);
			$statement->execute();
			return true;
		} catch (\PDOException $e) {
			error_log("Failed to update column comment: " . $e->getMessage() . " SQL: " . (isset($sql) ? $sql : 'N/A'));
			return false;
		}
	}	

	public function modelFunctionList($modelSelected=''){
		$SQLFilter = array();
		if ( $modelSelected != ''){
			$statement = $this->dbSA->prepare("
				select 	docModelFunctionId,
					model,
					functName,
					funct,
					functParameters,
					functDesc
				from docModelFunction 
				where model = :model
				order by model, functName 
			");
			$SQLFilter[':model'] = $modelSelected;
		} else {
			$statement = $this->dbSA->prepare("
				select 	docModelFunctionId,
					model,
					functName,
					funct,
					functParameters,
					functDesc
				from docModelFunction
				order by model, functName 
			");
		}
		
		try {
			if ( $statement->execute($SQLFilter) ) {
				$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
				return $rows;
			}
		}
		catch(\PDOException $e){
			echo "Query Failed".$e;
		}
		return 0;
	}

	public function modelFunctUpdate ($docModelFunctionId,$functDesc){
		$output = array();

		$statementSA = $this->dbSA->prepare("update docModelFunction set functDesc = :functDesc where docModelFunctionId = :docModelFunctionId");
		$statementSA->execute( array(":docModelFunctionId"=>$docModelFunctionId, ":functDesc"=>$functDesc ) );
		
		$output['success'] = 1;
		return $output ;
	}


	public function tableTriggers($databaseName, $tableName) {
		try {
			$statement = $this->db->prepare("
				select
					TRIGGER_NAME     as triggerName,
					EVENT_MANIPULATION as triggerEvent,
					ACTION_TIMING    as actionTiming,
					ACTION_STATEMENT as triggerBody
				from information_schema.TRIGGERS
				where EVENT_OBJECT_SCHEMA = :databaseName
				  and EVENT_OBJECT_TABLE  = :tableName
				order by ACTION_TIMING, EVENT_MANIPULATION
			");
			$statement->execute(array(':databaseName' => $databaseName, ':tableName' => $tableName));
			$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
			return $rows;
		} catch(\PDOException $e) {
			return array();
		}
	}


	public function modelList(){
		$statement = $this->dbSA->prepare("
			select distinct model from docModelFunction
			order by model
		");
		try {
			if ( $statement->execute() ) {
				$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);
				return $rows;
			}
		}
		catch(Exception $e){
			return 0;
		}
		return 0;
	}

	/**
	 * Get all distinct non-null tableType values across all databases.
	 */
	public function tableTypeDistinct() {
		$stmt = $this->dbSA->prepare("SELECT DISTINCT tableType FROM tableDef WHERE tableType IS NOT NULL AND tableType != '' ORDER BY tableType");
		$stmt->execute();
		$rows = $stmt->fetchAll(\PDO::FETCH_COLUMN);
		return $rows;
	}

	/**
	 * Get all tableType values for a database as tableName => tableType map.
	 */
	public function tableDefGetAll($databaseName) {
		$stmt = $this->dbSA->prepare("SELECT tableName, tableType FROM tableDef WHERE databaseName = :db");
		$stmt->execute(array(':db' => $databaseName));
		$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
		$map = array();
		foreach ($rows as $row) {
			$map[$row['tableName']] = $row['tableType'];
		}
		return $map;
	}

	/**
	 * Ensure a table exists in tableDef; insert if missing.
	 */
	public function ensureTableInTableDef($databaseName, $tableName) {
		$stmt = $this->dbSA->prepare("SELECT tableDef FROM tableDef WHERE databaseName = :db AND tableName = :tbl LIMIT 1");
		$stmt->execute(array(':db' => $databaseName, ':tbl' => $tableName));
		if ($stmt->rowCount() == 0) {
			$ins = $this->dbSA->prepare("INSERT INTO tableDef (databaseName, tableName) VALUES (:db, :tbl)");
			$ins->execute(array(':db' => $databaseName, ':tbl' => $tableName));
		}
	}

	/**
	 * Get tableType from tableDef for a given database/table.
	 */
	public function tableDefGet($databaseName, $tableName) {
		$stmt = $this->dbSA->prepare("SELECT tableType FROM tableDef WHERE databaseName = :db AND tableName = :tbl LIMIT 1");
		$stmt->execute(array(':db' => $databaseName, ':tbl' => $tableName));
		$row = $stmt->fetch(\PDO::FETCH_ASSOC);
		return $row ? $row['tableType'] : null;
	}

	/**
	 * Update tableType in tableDef; insert row if it doesn't exist.
	 */
	public function tableDefUpdate($databaseName, $tableName, $tableType) {
		$this->ensureTableInTableDef($databaseName, $tableName);
		$stmt = $this->dbSA->prepare("UPDATE tableDef SET tableType = :tableType WHERE databaseName = :db AND tableName = :tbl");
		$stmt->execute(array(':tableType' => $tableType, ':db' => $databaseName, ':tbl' => $tableName));
		return true;
	}

	/**
	 * Returns all foreign keys across all tables in the database that reference
	 * this table's column(s).  Result is keyed by the referenced column name so
	 * the JS can look up the PK column directly.
	 *
	 * Returns:
	 *   [
	 *     "clientId" => [
	 *       ["referencingTable" => "clientPolicy", "referencingColumn" => "clientId"],
	 *       ...
	 *     ],
	 *     ...
	 *   ]
	 */
	public function pkFkReferences($databaseName, $tableName) {
		try {
			$stmt = $this->db->prepare("
				SELECT
					kcu.COLUMN_NAME          AS referencingColumn,
					kcu.TABLE_NAME           AS referencingTable,
					kcu.REFERENCED_COLUMN_NAME AS referencedColumn
				FROM information_schema.KEY_COLUMN_USAGE kcu
				WHERE kcu.REFERENCED_TABLE_SCHEMA = :db
				  AND kcu.REFERENCED_TABLE_NAME   = :tbl
				  AND kcu.REFERENCED_COLUMN_NAME  IS NOT NULL
				ORDER BY kcu.REFERENCED_COLUMN_NAME, kcu.TABLE_NAME, kcu.COLUMN_NAME
			");
			$stmt->execute(array(':db' => $databaseName, ':tbl' => $tableName));
			$rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

			// Group by referenced (PK) column
			$result = array();
			foreach ($rows as $row) {
				$pkCol = $row['referencedColumn'];
				if (!isset($result[$pkCol])) {
					$result[$pkCol] = array();
				}
				$result[$pkCol][] = array(
					'referencingTable'  => $row['referencingTable'],
					'referencingColumn' => $row['referencingColumn']
				);
			}
			return $result;
		} catch (\PDOException $e) {
			return array();
		}
	}

	public function lookupIconRows($databaseName, $tableName) {
		try {
			$db = $databaseName;
			$tbl = $tableName;
			$SQL = "SELECT title, icon FROM `$db`.`$tbl` WHERE icon IS NOT NULL AND icon != '' ORDER BY displayOrder ASC, title ASC";
			$stmt = $this->db->prepare($SQL);
			$stmt->execute();
			return $stmt->fetchAll(\PDO::FETCH_ASSOC);
		} catch (\PDOException $e) {
			return array();
		}
	}

	public function deprecatedTables($databaseName) {
		try {
			$stmt = $this->db->prepare("
				SELECT DISTINCT TABLE_NAME FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = :databaseName1
				AND (LOWER(COLUMN_COMMENT) LIKE '%deprecated%' OR LOWER(COLUMN_COMMENT) LIKE '%depreciated%')
				UNION
				SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
				WHERE TABLE_SCHEMA = :databaseName2
				AND (LOWER(TABLE_COMMENT) LIKE '%deprecated%' OR LOWER(TABLE_COMMENT) LIKE '%depreciated%')
				ORDER BY TABLE_NAME ASC
			");
			$stmt->execute([':databaseName1' => $databaseName, ':databaseName2' => $databaseName]);
			$result = [];
			foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
				$result[] = $row['TABLE_NAME'];
			}
			return $result;
		} catch (\PDOException $e) {
			return [];
		}
	}

	public function deprecatedColumns($databaseName) {
		try {
			$stmt = $this->db->prepare("
				SELECT TABLE_NAME, COLUMN_NAME, COLUMN_COMMENT
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = :databaseName
				AND (LOWER(COLUMN_COMMENT) LIKE '%deprecated%' OR LOWER(COLUMN_COMMENT) LIKE '%depreciated%')
				ORDER BY TABLE_NAME ASC, ORDINAL_POSITION ASC
			");
			$stmt->execute([':databaseName' => $databaseName]);
			$result = [];
			foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
				$tbl = $row['TABLE_NAME'];
				if (!isset($result[$tbl])) { $result[$tbl] = []; }
				$result[$tbl][] = [
					'columnName' => $row['COLUMN_NAME'],
					'comment'    => $row['COLUMN_COMMENT']
				];
			}
			return $result;
		} catch (\PDOException $e) {
			return [];
		}
	}


}