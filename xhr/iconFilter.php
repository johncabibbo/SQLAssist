<?php
/**
 * iconFilter.php
 * Returns which lookup tables have rows with non-null icon values,
 * and which have no icon values.
 *
 * Inputs: databaseName (POST or GET)
 * Returns: { success, tablesWithIcons: [], tablesWithoutIcons: [] }
 */

require_once '../setting.php';

if (!isset($_SESSION['login'])) { exit(); }

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

    // Get all lookup tables that have an icon column
    $stmt = $db->prepare("
        SELECT t.TABLE_NAME
        FROM INFORMATION_SCHEMA.TABLES t
        JOIN INFORMATION_SCHEMA.COLUMNS c
            ON  t.TABLE_SCHEMA = c.TABLE_SCHEMA
            AND t.TABLE_NAME   = c.TABLE_NAME
        WHERE t.TABLE_SCHEMA   = :db
        AND   t.TABLE_COMMENT LIKE 'Lookup Table%'
        AND   LOWER(c.COLUMN_NAME) = 'icon'
        ORDER BY t.TABLE_NAME
    ");
    $stmt->execute([':db' => $databaseName]);
    $lookupTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $tablesWithIcons    = [];
    $tablesWithoutIcons = [];

    foreach ($lookupTables as $table) {
        $count = $db
            ->query("SELECT COUNT(*) FROM `$databaseName`.`$table` WHERE icon IS NOT NULL AND icon != '' AND icon != 'Blank'")
            ->fetchColumn();
        if ($count > 0) {
            $tablesWithIcons[] = $table;
        } else {
            $tablesWithoutIcons[] = $table;
        }
    }

    echo json_encode([
        'success'            => 1,
        'tablesWithIcons'    => $tablesWithIcons,
        'tablesWithoutIcons' => $tablesWithoutIcons,
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => 0, 'msg' => $e->getMessage()]);
}
?>
