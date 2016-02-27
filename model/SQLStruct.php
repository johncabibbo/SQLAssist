<?php
class SQLStruct{
	private $db; //Private to the object
	
	public function __construct($dsn,$user,$pass){
		try {
			$this->db = new \PDO($dsn, $user, $pass);
			$this->db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		}
		catch (Exception $e){
			header( 'Location: error.php' );
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
			select 	TABLE_SCHEMA as databaseName, TABLE_NAME as tableName
			from 	information_schema.TABLES
			where 	TABLE_TYPE = 'BASE TABLE'
			and 	TABLE_SCHEMA = :databaseName
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
	
	public function tableInfo($databaseName,$tableName){
		$statement = $this->db->prepare("
			select     TABLE_SCHEMA as databaseName, 
				TABLE_Name as tableName, 
				COLUMN_NAME as columnName, 
				DATA_TYPE as columnType, 
				case 
					when CHARACTER_MAXIMUM_LENGTH is not null then CHARACTER_MAXIMUM_LENGTH
					else NUMERIC_PRECISION 
				end as length,
				COLUMN_KEY, 
				EXTRA, 
				ORDINAL_POSITION
			from     information_schema.COLUMNS
			where    TABLE_NAME = :tableName 
			and 	 TABLE_SCHEMA = :databaseName
			order by ORDINAL_POSITION
		");
		try {
			if ( $statement->execute( array(":databaseName"=>$databaseName, ":tableName"=>$tableName) )) {
				$rows = $statement->fetchAll(\PDO::FETCH_ASSOC);

				$output['columns'] = $rows;

				return $output;
			}
		}
		catch(\PDOException $e){
			echo "Query Failed".$e;
		}
		return 0;
	}
	
}