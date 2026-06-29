<?php
/**
 * appendColumnInfo.php
 * Appends first two column values to table comment
 * Format: Lookup Table\n1-Value1, 2-Value2, ...
 *
 * Uses SQLStruct model to respect $commentSaveLocation setting
 *
 * @author Cloud Box 9
 * @date 2026-02-04
 */

require_once '../setting.php';

if (!isset($_SESSION['login'])) {
    exit();
}

require_once '../model/SQLStruct.php';

header('Content-Type: application/json; charset=utf-8');

$returnObject = array("success" => 0, "msg" => "");

foreach ($_GET as $key => $value) {
    $_POST[$key] = $value;
}

if (!isset($_POST['databaseName']) || !isset($_POST['tableName'])) {
    $returnObject['msg'] = 'Missing required parameters';
    echo json_encode($returnObject);
    exit();
}

$databaseName = $_POST['databaseName'];
$tableName = $_POST['tableName'];

try {
    $db = new \PDO($mysql_dsn, $mysql_user, $mysql_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $SQLStructModel = new SQLStruct($mysql_dsn, $mysql_user, $mysql_pass, $mysqlSA_dsn, $mysqlSA_user, $mysqlSA_pass);

    // Get comment save location from settings
    $saveLocation = isset($commentSaveLocation) ? $commentSaveLocation : 'A';

    // Get first two column names
    $stmt = $db->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :databaseName
        AND TABLE_NAME = :tableName
        ORDER BY ORDINAL_POSITION
        LIMIT 2
    ");
    $stmt->execute([':databaseName' => $databaseName, ':tableName' => $tableName]);
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (count($columns) < 2) {
        $returnObject['msg'] = 'Table must have at least 2 columns';
        echo json_encode($returnObject);
        exit();
    }

    $col1 = $columns[0];
    $col2 = $columns[1];

    // Get data from table - first column (ID) and second column (title/name)
    $stmt = $db->prepare("SELECT `$col1`, `$col2`, (select count(*) from `$databaseName`.`$tableName` ) as totalCount FROM `$databaseName`.`$tableName` ORDER BY `$col1` limit 10");
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $count1 = count($rows); 
    $count2 = $rows[0]['totalCount'];

    // Build the info string
    $infoItems = [];
    foreach ($rows as $row) {
        $id = $row[$col1];
        $title = $row[$col2];
        $infoItems[] = "{$id}-{$title}";
    }
    $infoString = implode(', ', $infoItems);

    if ( $count1 != $count2 ){
        $infoString = $infoString.'...';
    }

    // Get native table comment to check if it's a lookup table
    $stmt = $db->prepare("
        SELECT TABLE_COMMENT
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = :databaseName
        AND TABLE_NAME = :tableName
    ");
    $stmt->execute([':databaseName' => $databaseName, ':tableName' => $tableName]);
    $currentComment = $stmt->fetchColumn() ?: '';

    // Check if it's a lookup table
    $isLookupTable = (stripos($currentComment, 'Lookup Table') === 0);

    if ($isLookupTable) {
        // Keep only "Lookup Table" on first line, then Values on new line
        $newComment = "Lookup Table\nValues: " . $infoString;
    } else {
        // For non-lookup tables, just set Values prefix
        $newComment = "Values: " . $infoString;
    }

    // Update table comment using SQLStruct model (respects saveLocation setting)
    // TABLEDEFINITION is used as the columnName for table-level comments
    $update = $SQLStructModel->colDescUpdate($databaseName, $tableName, 'TABLEDEFINITION', $newComment, $saveLocation);

    $returnObject['success'] = 1;
    $returnObject['msg'] = 'Column info appended to table comment';
    $returnObject['infoString'] = $infoString;
    $returnObject['newComment'] = $newComment;
    $returnObject['savedToSA'] = isset($update['savedToSA']) ? $update['savedToSA'] : false;
    $returnObject['savedToTarget'] = isset($update['savedToTarget']) ? $update['savedToTarget'] : false;

} catch (PDOException $e) {
    $returnObject['msg'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($returnObject);
?>
