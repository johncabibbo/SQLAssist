<?php
/**
 * appendAllColumnInfo.php
 * Runs appendColumnInfo logic on every lookup table in the database.
 * Called as Step 3 of "Apply Settings".
 *
 * Inputs: databaseName (POST or GET)
 * Returns: { success, processed, skipped, errors[] }
 */

require_once '../setting.php';

if (!isset($_SESSION['login'])) { exit(); }

require_once '../model/SQLStruct.php';

header('Content-Type: application/json; charset=utf-8');

foreach ($_GET as $k => $v) { $_POST[$k] = $v; }

$databaseName = isset($_POST['databaseName']) ? trim($_POST['databaseName']) : '';
if (!$databaseName) {
    echo json_encode(['success' => 0, 'msg' => 'Missing databaseName']);
    exit;
}

try {
    $db = new PDO($mysql_dsn, $mysql_user, $mysql_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $SQLStructModel = new SQLStruct($mysql_dsn, $mysql_user, $mysql_pass, $mysqlSA_dsn, $mysqlSA_user, $mysqlSA_pass);
    $saveLocation   = isset($commentSaveLocation) ? $commentSaveLocation : 'A';

    // Get all lookup tables in the database
    $stmt = $db->prepare("
        SELECT TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA  = :db
        AND   TABLE_COMMENT LIKE 'Lookup Table%'
        ORDER BY TABLE_NAME
    ");
    $stmt->execute([':db' => $databaseName]);
    $lookupTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $processed = 0;
    $skipped   = 0;
    $errors    = [];

    foreach ($lookupTables as $tableName) {
        try {
            // Get first two columns
            $colStmt = $db->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl
                ORDER BY ORDINAL_POSITION LIMIT 2
            ");
            $colStmt->execute([':db' => $databaseName, ':tbl' => $tableName]);
            $cols = $colStmt->fetchAll(PDO::FETCH_COLUMN);

            if (count($cols) < 2) { $skipped++; continue; }

            $col1 = $cols[0];
            $col2 = $cols[1];

            // Fetch up to 10 rows
            $rowStmt = $db->query("SELECT `$col1`, `$col2`, (SELECT COUNT(*) FROM `$databaseName`.`$tableName`) AS totalCount FROM `$databaseName`.`$tableName` ORDER BY `$col1` LIMIT 10");
            $rows = $rowStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$rows) { $skipped++; continue; }

            $total = $rows[0]['totalCount'];
            $items = [];
            foreach ($rows as $row) {
                $items[] = $row[$col1] . '-' . $row[$col2];
            }
            $infoString = implode(', ', $items) . ($total > count($rows) ? '...' : '');

            $newComment = "Lookup Table\nValues: " . $infoString;

            $SQLStructModel->colDescUpdate($databaseName, $tableName, 'TABLEDEFINITION', $newComment, $saveLocation);
            $processed++;

        } catch (Exception $e) {
            $errors[] = $tableName . ': ' . $e->getMessage();
        }
    }

    echo json_encode([
        'success'   => 1,
        'processed' => $processed,
        'skipped'   => $skipped,
        'errors'    => $errors,
        'msg'       => "Appended column info to $processed lookup table(s)"
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => 0, 'msg' => $e->getMessage()]);
}
?>
