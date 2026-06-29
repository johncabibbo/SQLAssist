<?php
/**
 * runBulkFillCLI.php
 *
 * CLI-only script to bulk-fill blank column comments for a target database.
 * Run from the xhr/ directory:
 *   php runBulkFillCLI.php docinfo_live
 *
 * This script does NOT require a web session — it connects directly.
 */

if (php_sapi_name() !== 'cli') {
    die("CLI only\n");
}

$databaseName = $argv[1] ?? null;
if (!$databaseName) {
    die("Usage: php runBulkFillCLI.php <databaseName>\n");
}

// Connection credentials are loaded from config.php (same config used by the web
// UI). Edit config.php to point at your target server — never hard-code
// credentials here.
require_once __DIR__ . '/../config.php';
// $mysql_dsn / $mysql_user / $mysql_pass        — target server (information_schema)
// $mysqlSA_dsn / $mysqlSA_user / $mysqlSA_pass  — SQL Assist comment storage
// $commentSaveLocation                          — 'A' | 'B' | 'C'

// [Deprecated] columns to skip (lowercase table.column)
$deprecatedColumns = [
    'serveralias.aliasscriptgroup'
];

$DISPLAY_ORDER_TEXT = 'Records are ordered by displayOrderGroup asc, displayOrder asc, title asc';

$totalUpdated    = 0;
$tablesProcessed = 0;
$tableDetails    = [];

try {
    $db = new \PDO($mysql_dsn, $mysql_user, $mysql_pass);
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    $dbSA = new \PDO($mysqlSA_dsn, $mysqlSA_user, $mysqlSA_pass);
    $dbSA->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // Write comment helper
    $writeComment = function($tableName, $col, $newComment) use ($db, $dbSA, $databaseName, $commentSaveLocation, &$totalUpdated) {
        $colName    = $col['COLUMN_NAME'];
        $columnType = $col['COLUMN_TYPE'];
        $nullable   = ($col['IS_NULLABLE'] === 'YES') ? 'NULL' : 'NOT NULL';
        $default    = '';
        if ($col['COLUMN_DEFAULT'] !== null) {
            $default = "DEFAULT '" . addslashes($col['COLUMN_DEFAULT']) . "'";
        } elseif ($col['IS_NULLABLE'] === 'YES') {
            $default = 'DEFAULT NULL';
        }
        $extra = (stripos($col['EXTRA'], 'auto_increment') !== false) ? 'AUTO_INCREMENT' : '';

        // Write to MySQL native COMMENT
        if ($commentSaveLocation === 'B' || $commentSaveLocation === 'C') {
            try {
                $alterSQL = "ALTER TABLE `$databaseName`.`$tableName`
                    MODIFY COLUMN `$colName` $columnType $nullable $default $extra COMMENT :comment";
                $stmt = $db->prepare($alterSQL);
                $stmt->execute([':comment' => $newComment]);
            } catch (\PDOException $e) {
                echo "  WARN ALTER skipped [$tableName.$colName]: " . $e->getMessage() . "\n";
                // Still write to SQLAssist colDef below
            }
        }

        // Write to SQLAssist colDef (only if colDef entry is also blank)
        if ($commentSaveLocation === 'A' || $commentSaveLocation === 'C') {
            $stmtSA = $dbSA->prepare("
                SELECT colDefId, columnDesc FROM colDef
                WHERE databaseName = :db AND tableName = :tbl AND columnName = :col
            ");
            $stmtSA->execute([':db' => $databaseName, ':tbl' => $tableName, ':col' => $colName]);
            $existing = $stmtSA->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                if (trim($existing['columnDesc'] ?? '') === '') {
                    $stmtSA = $dbSA->prepare("UPDATE colDef SET columnDesc = :desc WHERE colDefId = :id");
                    $stmtSA->execute([':desc' => $newComment, ':id' => $existing['colDefId']]);
                }
            } else {
                $stmtSA = $dbSA->prepare("
                    INSERT INTO colDef (databaseName, tableName, columnName, columnDesc)
                    VALUES (:db, :tbl, :col, :desc)
                ");
                $stmtSA->execute([
                    ':db'   => $databaseName,
                    ':tbl'  => $tableName,
                    ':col'  => $colName,
                    ':desc' => $newComment
                ]);
            }
        }

        $totalUpdated++;
        echo "  [$tableName.$colName] " . substr($newComment, 0, 70) . (strlen($newComment) > 70 ? '...' : '') . "\n";
    };

    // Get all base tables
    $stmtTables = $db->prepare("
        SELECT TABLE_NAME, TABLE_COMMENT FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = :db AND TABLE_TYPE = 'BASE TABLE'
        ORDER BY TABLE_NAME
    ");
    $stmtTables->execute([':db' => $databaseName]);
    $tables = $stmtTables->fetchAll(\PDO::FETCH_ASSOC);

    echo "Processing " . count($tables) . " tables in $databaseName...\n\n";

    foreach ($tables as $tbl) {
        $tableName     = $tbl['TABLE_NAME'];
        $tableComment  = $tbl['TABLE_COMMENT'] ?: '';
        $isLookupTable = (stripos($tableComment, 'lookup table') === 0);
        $isNotUsed     = (strpos($tableComment, '[NotUsed]') === 0);

        $tableUpdated = 0;

        // Get columns
        $stmtCols = $db->prepare("
            SELECT COLUMN_NAME, COLUMN_TYPE, DATA_TYPE, COLUMN_KEY, EXTRA,
                   IS_NULLABLE, COLUMN_DEFAULT, COLUMN_COMMENT, ORDINAL_POSITION
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl
            ORDER BY ORDINAL_POSITION
        ");
        $stmtCols->execute([':db' => $databaseName, ':tbl' => $tableName]);
        $columns = $stmtCols->fetchAll(\PDO::FETCH_ASSOC);

        // Get FK map
        $stmtFK = $db->prepare("
            SELECT kcu.COLUMN_NAME, kcu.REFERENCED_TABLE_NAME, kcu.REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = :db AND kcu.TABLE_NAME = :tbl
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
        ");
        $stmtFK->execute([':db' => $databaseName, ':tbl' => $tableName]);
        $fkRows = $stmtFK->fetchAll(\PDO::FETCH_ASSOC);
        $fkMap  = [];
        foreach ($fkRows as $fk) {
            $fkMap[$fk['COLUMN_NAME']] = [
                'refTable'  => $fk['REFERENCED_TABLE_NAME'],
                'refColumn' => $fk['REFERENCED_COLUMN_NAME']
            ];
        }

        $makeComment = function($text) use ($isNotUsed) {
            return $isNotUsed ? '[NotUsed] ' . $text : $text;
        };

        foreach ($columns as $col) {
            $colName = $col['COLUMN_NAME'];
            $comment = trim($col['COLUMN_COMMENT']);

            if ($comment !== '') continue;

            $key = strtolower($tableName . '.' . $colName);
            if (in_array($key, $deprecatedColumns)) {
                echo "  SKIPPED [Deprecated]: $tableName.$colName\n";
                continue;
            }

            $colNameLower = strtolower($colName);
            $dataType     = strtolower($col['DATA_TYPE']);

            if ($colNameLower === 'displayorder' || $colNameLower === 'displayordergroup') {
                $writeComment($tableName, $col, $makeComment($DISPLAY_ORDER_TEXT));
                $tableUpdated++;
                continue;
            }

            if ($colNameLower === 'displayclass') {
                $writeComment($tableName, $col, $makeComment('CSS Class applied when displayed in HTML'));
                $tableUpdated++;
                continue;
            }

            if ($colNameLower === 'userid' && $isLookupTable) {
                $writeComment($tableName, $col, $makeComment("Lookup Table Rule\nIf userd > 0, only display to users where their session.userId = THIS.userId or THIS.userId is null. If UserId is null this record is displayed for all users. "));
                $tableUpdated++;
                continue;
            }

            if ($colNameLower === 'orgid' && $isLookupTable) {
                $writeComment($tableName, $col, $makeComment("Lookup Table Rule\nIf orgId > 0, only display to users where their session.orgId = THIS.orgId or THIS.orgId is null. If orgId is null this record is displayed for all users. \n"));
                $tableUpdated++;
                continue;
            }

            if ($col['ORDINAL_POSITION'] == 1
                && $col['COLUMN_KEY'] === 'PRI'
                && stripos($col['EXTRA'], 'auto_increment') !== false
                && in_array($dataType, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint'])
            ) {
                $writeComment($tableName, $col, $makeComment('Auto-Assigned Primary Key'));
                $tableUpdated++;
                continue;
            }

            if (isset($fkMap[$colName])) {
                $refTable  = $fkMap[$colName]['refTable'];
                $refColumn = $fkMap[$colName]['refColumn'];

                $skipValues = (
                    (strtolower($refTable) === 'users' && strtolower($refColumn) === 'userid') ||
                    (strtolower($refTable) === 'org'   && strtolower($refColumn) === 'orgid')
                );

                $sampleValues = '';
                if (!$skipValues) {
                    $stmtRef = $db->prepare("
                        SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS
                        WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :tbl
                        ORDER BY ORDINAL_POSITION
                    ");
                    $stmtRef->execute([':db' => $databaseName, ':tbl' => $refTable]);
                    $refColumns = $stmtRef->fetchAll(\PDO::FETCH_COLUMN);

                    $textColumn = null;
                    foreach ($refColumns as $rc) {
                        if (strtolower($rc) === 'title' || strtolower($rc) === 'name') {
                            $textColumn = $rc;
                            break;
                        }
                    }
                    if (!$textColumn && count($refColumns) > 1) {
                        $textColumn = $refColumns[1];
                    }

                    if ($textColumn) {
                        $stmtSamples = $db->prepare("
                            SELECT `$refColumn`, `$textColumn`
                            FROM `$databaseName`.`$refTable`
                            ORDER BY `$refColumn`
                            LIMIT 3
                        ");
                        $stmtSamples->execute();
                        $samples = $stmtSamples->fetchAll(\PDO::FETCH_ASSOC);

                        $pairs = [];
                        foreach ($samples as $s) {
                            $pairs[] = $s[$refColumn] . '-' . $s[$textColumn];
                        }
                        if (!empty($pairs)) {
                            $sampleValues = implode(', ', $pairs);
                        }
                    }
                }

                $newComment = "Foreign Key to $refTable.$refColumn";
                if ($sampleValues) {
                    $newComment .= "\nValues: $sampleValues ...";
                }

                $writeComment($tableName, $col, $makeComment($newComment));
                $tableUpdated++;
                continue;
            }
        }

        if ($tableUpdated > 0) {
            $tableDetails[] = "$tableName: $tableUpdated column(s)";
        }
        $tablesProcessed++;
    }

    echo "\n========================================\n";
    echo "DONE\n";
    echo "Tables processed : $tablesProcessed\n";
    echo "Columns updated  : $totalUpdated\n";
    if (!empty($tableDetails)) {
        echo "\nTables with updates:\n";
        foreach ($tableDetails as $d) {
            echo "  $d\n";
        }
    }

} catch (\PDOException $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
?>
