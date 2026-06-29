<?php
/**
 * addLookupColumns.php
 * Adds missing columns required for lookup tables
 *
 * Required columns (starting from 2nd position, matches userType reference table):
 * title, description, abr, displayOrder, displayOrderGroup, displayClass,
 * userId, orgId, deleted, deletable, icon, additionalInfo1, additionalInfo2, adminOnly
 *
 * @author Cloud Box 9
 * @date 2026-02-04
 */

require_once '../setting.php';

if (!isset($_SESSION['login'])) {
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$returnObject = array("success" => 0, "msg" => "", "addedColumns" => []);

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

$_SESSION['dbSelected'] = $databaseName;
$_SESSION['tableSelected'] = $tableName;

// Required lookup table columns — matches dev_ahiadvisors_com.userType as reference
$requiredColumns = [
    'title'            => 'VARCHAR(50)  NULL',
    'description'      => 'VARCHAR(300) NULL',
    'abr'              => 'VARCHAR(30)  NULL',
    'displayOrder'     => 'INT          NULL DEFAULT 0',
    'displayOrderGroup'=> 'INT          NULL DEFAULT 0',
    'displayClass'     => 'VARCHAR(30)  NULL',
    'userId'           => 'INT          NULL DEFAULT 0',
    'orgId'            => 'INT          NULL DEFAULT 0',
    'deleted'          => 'INT          NULL DEFAULT 0',
    'deletable'        => 'INT          NULL DEFAULT 1',
    'icon'             => 'VARCHAR(255) NULL',
    'additionalInfo1'  => 'JSON         NULL',
    'additionalInfo2'  => 'JSON         NULL',
    'adminOnly'        => 'INT          NULL DEFAULT 0',
];

try {
    $db = new \PDO($mysql_dsn, $mysql_user, $mysql_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get existing columns
    $stmt = $db->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :databaseName
        AND TABLE_NAME = :tableName
    ");
    $stmt->execute([':databaseName' => $databaseName, ':tableName' => $tableName]);
    $existingColumns = array_map('strtolower', $stmt->fetchAll(PDO::FETCH_COLUMN));

    $addedColumns = [];
    $previousColumn = null;

    // Get the first column name (ID column) to add after it
    $stmt = $db->prepare("
        SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :databaseName
        AND TABLE_NAME = :tableName
        ORDER BY ORDINAL_POSITION
        LIMIT 1
    ");
    $stmt->execute([':databaseName' => $databaseName, ':tableName' => $tableName]);
    $firstColumn = $stmt->fetchColumn();

    $previousColumn = $firstColumn;

    foreach ($requiredColumns as $columnName => $columnDef) {
        if (!in_array(strtolower($columnName), $existingColumns)) {
            // Add the column after the previous column
            $sql = "ALTER TABLE `$databaseName`.`$tableName` ADD COLUMN `$columnName` $columnDef";
            if ($previousColumn) {
                $sql .= " AFTER `$previousColumn`";
            }

            $db->exec($sql);
            $addedColumns[] = $columnName;
        }
        $previousColumn = $columnName;
    }

    $returnObject['success'] = 1;
    if (count($addedColumns) > 0) {
        $returnObject['msg'] = 'Added ' . count($addedColumns) . ' column(s): ' . implode(', ', $addedColumns);
    } else {
        $returnObject['msg'] = 'No columns needed to be added';
    }
    $returnObject['addedColumns'] = $addedColumns;

} catch (PDOException $e) {
    $returnObject['msg'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($returnObject);
?>
