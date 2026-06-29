<?php
/**
 * markLookupTables.php
 * Marks tables as lookup tables if they match the expected structure
 * Structure: 2nd col = title, 3rd = description, 4th = abr, 5th = displayOrder
 *
 * Also auto-assigns column comments:
 * - Primary keys (int, auto-increment) → "Auto-Assigned Primary Key"
 * - Foreign keys → "Foreign Key to TABLE.COLUMN" with sample values
 *
 * @author Cloud Box 9
 * @date 2026-02-04
 * @updated 2026-02-08
 */

require_once '../setting.php';

if (!isset($_SESSION['login'])) {
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$returnObject = array("success" => 0, "msg" => "", "markedTables" => [], "pkComments" => 0, "fkComments" => 0);

foreach ($_GET as $key => $value) {
    $_POST[$key] = $value;
}

if (!isset($_POST['databaseName'])) {
    $returnObject['msg'] = 'Missing required parameters';
    echo json_encode($returnObject);
    exit();
}

$databaseName = $_POST['databaseName'];

try {
    $db = new \PDO($mysql_dsn, $mysql_user, $mysql_pass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get all tables with their comments
    $stmt = $db->prepare("
        SELECT TABLE_NAME, TABLE_COMMENT
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = :databaseName
        AND TABLE_TYPE = 'BASE TABLE'
    ");
    $stmt->execute([':databaseName' => $databaseName]);
    $tables = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $markedCount = 0;
    $markedTables = [];
    $pkCommentsAdded = 0;
    $fkCommentsAdded = 0;

    foreach ($tables as $table) {
        $tableName = $table['TABLE_NAME'];
        $tableComment = $table['TABLE_COMMENT'];

        // =====================================================================
        // 1. Mark Lookup Tables
        // =====================================================================
        if (stripos($tableComment, 'Lookup Table') !== 0) {
            // Get column names in order
            $stmt = $db->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = :databaseName
                AND TABLE_NAME = :tableName
                ORDER BY ORDINAL_POSITION
                LIMIT 5
            ");
            $stmt->execute([':databaseName' => $databaseName, ':tableName' => $tableName]);
            $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Check if columns match lookup table pattern
            // 2nd=title, 3rd=description, 4th=abr, 5th=displayOrder
            if (count($columns) >= 5 &&
                strtolower($columns[1]) === 'title' &&
                strtolower($columns[2]) === 'description' &&
                strtolower($columns[3]) === 'abr' &&
                strtolower($columns[4]) === 'displayorder') {

                // Prepend "Lookup Table" to comment
                $newComment = 'Lookup Table' . ($tableComment ? ' - ' . $tableComment : '');

                $stmt = $db->prepare("ALTER TABLE `$databaseName`.`$tableName` COMMENT = :comment");
                $stmt->execute([':comment' => $newComment]);

                $markedCount++;
                $markedTables[] = $tableName;
            }
        }

        // =====================================================================
        // 2. Auto-Assign Primary Key Comments
        // =====================================================================
        // Check first column: if comment is blank, is int, is PK, is auto-increment
        $stmt = $db->prepare("
            SELECT COLUMN_NAME, DATA_TYPE, COLUMN_KEY, EXTRA, COLUMN_COMMENT
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :databaseName
            AND TABLE_NAME = :tableName
            ORDER BY ORDINAL_POSITION
            LIMIT 1
        ");
        $stmt->execute([':databaseName' => $databaseName, ':tableName' => $tableName]);
        $firstCol = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($firstCol) {
            $colComment = trim($firstCol['COLUMN_COMMENT']);
            $dataType = strtolower($firstCol['DATA_TYPE']);
            $columnKey = $firstCol['COLUMN_KEY'];
            $extra = strtolower($firstCol['EXTRA']);
            $colName = $firstCol['COLUMN_NAME'];

            // Check: comment is blank, data type is int variant, is primary key, is auto_increment
            $isIntType = in_array($dataType, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint']);
            $isPrimaryKey = ($columnKey === 'PRI');
            $isAutoIncrement = (strpos($extra, 'auto_increment') !== false);

            if ($colComment === '' && $isIntType && $isPrimaryKey && $isAutoIncrement) {
                // Set the column comment to "Auto-Assigned Primary Key"
                $stmt = $db->prepare("
                    ALTER TABLE `$databaseName`.`$tableName`
                    MODIFY COLUMN `$colName` $dataType AUTO_INCREMENT COMMENT 'Auto-Assigned Primary Key'
                ");
                $stmt->execute();
                $pkCommentsAdded++;
            }
        }

        // =====================================================================
        // 3. Auto-Assign Foreign Key Comments
        // =====================================================================
        // Get all foreign key constraints for this table
        $stmt = $db->prepare("
            SELECT
                kcu.COLUMN_NAME,
                kcu.REFERENCED_TABLE_NAME,
                kcu.REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = :databaseName
            AND kcu.TABLE_NAME = :tableName
            AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $stmt->execute([':databaseName' => $databaseName, ':tableName' => $tableName]);
        $foreignKeys = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($foreignKeys as $fk) {
            $fkColumnName = $fk['COLUMN_NAME'];
            $refTable = $fk['REFERENCED_TABLE_NAME'];
            $refColumn = $fk['REFERENCED_COLUMN_NAME'];

            // Get current column info (comment and data type)
            $stmt = $db->prepare("
                SELECT COLUMN_COMMENT, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = :databaseName
                AND TABLE_NAME = :tableName
                AND COLUMN_NAME = :columnName
            ");
            $stmt->execute([
                ':databaseName' => $databaseName,
                ':tableName' => $tableName,
                ':columnName' => $fkColumnName
            ]);
            $colInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($colInfo && trim($colInfo['COLUMN_COMMENT']) === '') {
                // Get first 3 values from the referenced table
                // Try to find a text column (title, name, or second column)
                $stmt = $db->prepare("
                    SELECT COLUMN_NAME
                    FROM INFORMATION_SCHEMA.COLUMNS
                    WHERE TABLE_SCHEMA = :databaseName
                    AND TABLE_NAME = :refTable
                    ORDER BY ORDINAL_POSITION
                ");
                $stmt->execute([':databaseName' => $databaseName, ':refTable' => $refTable]);
                $refColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

                // Find the best text column (title, name, or second column)
                $textColumn = null;
                foreach ($refColumns as $rc) {
                    if (strtolower($rc) === 'title' || strtolower($rc) === 'name') {
                        $textColumn = $rc;
                        break;
                    }
                }
                if (!$textColumn && count($refColumns) > 1) {
                    $textColumn = $refColumns[1]; // Use second column
                }

                // Skip sample values for users.userId and org.orgId
                $skipValues = (
                    (strtolower($refTable) === 'users' && strtolower($refColumn) === 'userid') ||
                    (strtolower($refTable) === 'org' && strtolower($refColumn) === 'orgid')
                );

                // Build the sample values string (unless skipped)
                $sampleValues = '';
                if (!$skipValues && $textColumn) {
                    $stmt = $db->prepare("
                        SELECT `$refColumn`, `$textColumn`
                        FROM `$databaseName`.`$refTable`
                        ORDER BY `$refColumn`
                        LIMIT 3
                    ");
                    $stmt->execute();
                    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    $valuePairs = [];
                    foreach ($samples as $sample) {
                        $valuePairs[] = $sample[$refColumn] . '-' . $sample[$textColumn];
                    }
                    if (!empty($valuePairs)) {
                        $sampleValues = implode(', ', $valuePairs);
                    }
                }

                // Build the new comment
                $newComment = "Foreign Key to $refTable.$refColumn";
                if ($sampleValues) {
                    $newComment .= "\nValues: $sampleValues";
                }

                // Update the column comment
                $columnType = $colInfo['COLUMN_TYPE'];
                $nullable = ($colInfo['IS_NULLABLE'] === 'YES') ? 'NULL' : 'NOT NULL';
                $default = '';
                if ($colInfo['COLUMN_DEFAULT'] !== null) {
                    $default = "DEFAULT '" . $colInfo['COLUMN_DEFAULT'] . "'";
                } elseif ($colInfo['IS_NULLABLE'] === 'YES') {
                    $default = "DEFAULT NULL";
                }

                $alterSQL = "ALTER TABLE `$databaseName`.`$tableName`
                    MODIFY COLUMN `$fkColumnName` $columnType $nullable $default COMMENT :comment";
                $stmt = $db->prepare($alterSQL);
                $stmt->execute([':comment' => $newComment]);
                $fkCommentsAdded++;
            }
        }
    }

    $returnObject['success'] = 1;
    $msgParts = [];
    if ($markedCount > 0) {
        $msgParts[] = "Marked $markedCount table(s) as lookup tables";
    }
    if ($pkCommentsAdded > 0) {
        $msgParts[] = "Added $pkCommentsAdded primary key comment(s)";
    }
    if ($fkCommentsAdded > 0) {
        $msgParts[] = "Added $fkCommentsAdded foreign key comment(s)";
    }
    if (empty($msgParts)) {
        $msgParts[] = "No changes needed";
    }
    $returnObject['msg'] = implode('. ', $msgParts);
    $returnObject['markedTables'] = $markedTables;
    $returnObject['markedCount'] = $markedCount;
    $returnObject['pkComments'] = $pkCommentsAdded;
    $returnObject['fkComments'] = $fkCommentsAdded;

} catch (PDOException $e) {
    $returnObject['msg'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($returnObject);
?>
