<?php
/**
 * autoFillAllTableComments.php
 *
 * Bulk version of autoFillColumnComments.php.
 * Loops through ALL base tables in the selected database and auto-fills
 * BLANK column comments using the same standard rules:
 *   - Primary Key (int, auto-increment, first column) → "Auto-Assigned Primary Key"
 *   - Foreign Key → "Foreign Key to TABLE.COLUMN\nValues: 1-X, 2-Y, 3-Z ..."
 *   - displayOrder / displayOrderGroup → display order text
 *   - displayClass → "CSS Class applied when displayed in HTML"
 *   - userId in lookup table → Lookup Table Rule (userId)
 *   - orgId in lookup table  → Lookup Table Rule (orgId)
 *   - CB9 Standard columns: createdDate, createdBy, lastModifiedDate, lastModifiedBy, deleted
 *   - Named columns: title, description, abr, deletable, icon
 *   - Columns in [NotUsed] tables → prefixed with "[NotUsed] "
 *
 * Skips [Deprecated] columns: serverAlias.aliasScriptGroup
 *
 * Dual-writes to:
 *   1. Target database native MySQL COMMENT (ALTER TABLE MODIFY COLUMN)
 *   2. SQLAssist colDef table (only if the existing colDef comment is also blank)
 *
 * @author Cloud Box 9
 * @date 2026-03-24
 */

require_once '../setting.php';

if (!isset($_SESSION['login'])) {
    exit();
}

header('Content-Type: application/json; charset=utf-8');

$returnObject = [
    'success'         => 0,
    'msg'             => '',
    'totalUpdated'    => 0,
    'tablesProcessed' => 0,
    'tableDetails'    => []
];

foreach ($_GET as $key => $value) {
    $_POST[$key] = $value;
}

if (empty($_POST['databaseName'])) {
    $returnObject['msg'] = 'Missing required parameter: databaseName';
    echo json_encode($returnObject);
    exit();
}

$databaseName = $_POST['databaseName'];

// [Deprecated] columns to skip (table.column, lowercase)
$deprecatedColumns = [
    'serveralias.aliasscriptgroup'
];

$DISPLAY_ORDER_TEXT = 'Records are ordered by displayOrderGroup asc, displayOrder asc, title asc';

try {
    $db   = new \PDO($mysql_dsn, $mysql_user, $mysql_pass);
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    $dbSA = new \PDO($mysqlSA_dsn, $mysqlSA_user, $mysqlSA_pass);
    $dbSA->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // =========================================================================
    // 1. Get all base tables in this database
    // =========================================================================
    $stmtTables = $db->prepare("
        SELECT TABLE_NAME, TABLE_COMMENT
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = :databaseName
          AND TABLE_TYPE   = 'BASE TABLE'
        ORDER BY TABLE_NAME
    ");
    $stmtTables->execute([':databaseName' => $databaseName]);
    $tables = $stmtTables->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($tables)) {
        $returnObject['msg'] = "No tables found in database: $databaseName";
        echo json_encode($returnObject);
        exit();
    }

    // =========================================================================
    // 2. Helper: dual-write a comment
    // =========================================================================
    $writeComment = function($tableName, $col, $newComment, $forceColDef = false) use ($db, $dbSA, $databaseName, $commentSaveLocation, &$returnObject) {
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
        if ($commentSaveLocation === 'A' || $commentSaveLocation === 'B' || $commentSaveLocation === 'C') {
            if ($commentSaveLocation !== 'A') {
                try {
                    $alterSQL = "ALTER TABLE `$databaseName`.`$tableName`
                        MODIFY COLUMN `$colName` $columnType $nullable $default $extra COMMENT :comment";
                    $stmt = $db->prepare($alterSQL);
                    $stmt->execute([':comment' => $newComment]);
                } catch (\PDOException $e) {
                    // Log and continue — colDef write still proceeds below
                    error_log("autoFillAllTableComments ALTER skipped [$databaseName.$tableName.$colName]: " . $e->getMessage());
                }
            }
        }

        // Write to SQLAssist colDef (only if colDef entry is also blank)
        if ($commentSaveLocation === 'A' || $commentSaveLocation === 'C') {
            $stmtSA = $dbSA->prepare("
                SELECT colDefId, columnDesc
                FROM colDef
                WHERE databaseName = :db AND tableName = :tbl AND columnName = :col
            ");
            $stmtSA->execute([':db' => $databaseName, ':tbl' => $tableName, ':col' => $colName]);
            $existing = $stmtSA->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                if ($forceColDef || trim($existing['columnDesc'] ?? '') === '') {
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

        $returnObject['totalUpdated']++;
    };

    // =========================================================================
    // 3. Process each table
    // =========================================================================
    foreach ($tables as $tbl) {
        $tableName    = $tbl['TABLE_NAME'];
        $tableComment = $tbl['TABLE_COMMENT'] ?: '';
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

        // Get FK map for this table
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

        // Helper: apply [NotUsed] prefix when table is marked [NotUsed]
        $makeComment = function($text) use ($isNotUsed) {
            return $isNotUsed ? '[NotUsed] ' . $text : $text;
        };

        foreach ($columns as $col) {
            $colName      = $col['COLUMN_NAME'];
            $colNameLower = strtolower(trim($colName));
            $dataType     = strtolower($col['DATA_TYPE']);
            $comment      = trim($col['COLUMN_COMMENT'] ?? '');

            // Skip [Deprecated] columns
            $key = strtolower($tableName . '.' . $colName);
            if (in_array($key, $deprecatedColumns)) {
                continue;
            }

            // Helper: apply [NotUsed] prefix when table is marked [NotUsed]
            $makeComment = function($text) use ($isNotUsed) {
                return $isNotUsed ? '[NotUsed] ' . $text : $text;
            };

            // =================================================================
            // Named columns — always fill, even if MySQL comment is non-blank.
            // =================================================================
            if ($colNameLower === 'title') {
                $writeComment($tableName, $col, $makeComment('Friendly Title'), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'description') {
                $writeComment($tableName, $col, $makeComment('Friendly Description'), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'abr') {
                $writeComment($tableName, $col, $makeComment('Abbreviation'), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'deletable') {
                $writeComment($tableName, $col, $makeComment('Is this row deletable? If 0, only system admins (UserTypeId=1) can set deleted=1'), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'icon') {
                $writeComment($tableName, $col, $makeComment('Image Path & Filename'), true);
                $tableUpdated++;
                continue;
            }

            // =================================================================
            // CB9 Standard columns — always fill, even if MySQL comment is non-blank.
            // =================================================================
            if ($colNameLower === 'createddate') {
                $writeComment($tableName, $col, $makeComment('Date/time when this record was created'), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'createdby') {
                $writeComment($tableName, $col, $makeComment('UserId of the user who created this record'), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'lastmodifieddate') {
                $writeComment($tableName, $col, $makeComment('Date/time when this record was last modified'), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'lastmodifiedby') {
                $writeComment($tableName, $col, $makeComment('UserId of the user who last modified this record'), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'deleted') {
                $writeComment($tableName, $col, $makeComment('1-Yes, 0-No'), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'displayorder' || $colNameLower === 'displayordergroup') {
                $writeComment($tableName, $col, $makeComment($DISPLAY_ORDER_TEXT), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'displayclass') {
                $writeComment($tableName, $col, $makeComment('CSS Class applied when displayed in HTML'), true);
                $tableUpdated++;
                continue;
            }

            // =================================================================
            // Lookup table rules — always fill for recognised lookup columns.
            // =================================================================
            if ($colNameLower === 'userid' && $isLookupTable) {
                $writeComment($tableName, $col, $makeComment("Lookup Table Rule\nIf userd > 0, only display to users where their session.userId = THIS.userId or THIS.userId is null. If UserId is null this record is displayed for all users. "), true);
                $tableUpdated++;
                continue;
            }
            if ($colNameLower === 'orgid' && $isLookupTable) {
                $writeComment($tableName, $col, $makeComment("Lookup Table Rule\nIf orgId > 0, only display to users where their session.orgId = THIS.orgId or THIS.orgId is null. If orgId is null this record is displayed for all users. \n"), true);
                $tableUpdated++;
                continue;
            }

            // =================================================================
            // All remaining columns — only process if MySQL comment is blank.
            // =================================================================
            if ($comment !== '') {
                continue;
            }

            // --- Primary Key (first column, int, auto_increment, PK) ---
            if ($col['ORDINAL_POSITION'] == 1
                && $col['COLUMN_KEY'] === 'PRI'
                && stripos($col['EXTRA'], 'auto_increment') !== false
                && in_array($dataType, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint'])
            ) {
                $writeComment($tableName, $col, $makeComment('Auto-Assigned Primary Key'), true);
                $tableUpdated++;
                continue;
            }

            // --- Foreign Key ---
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
            $returnObject['tableDetails'][] = "$tableName: $tableUpdated column(s) updated";
        }
        $returnObject['tablesProcessed']++;
    }

    // =========================================================================
    // 4. Build response message
    // =========================================================================
    $returnObject['success'] = 1;
    if ($returnObject['totalUpdated'] === 0) {
        $returnObject['msg'] = 'No blank comments found to fill across all tables';
    } else {
        $returnObject['msg'] = 'Updated ' . $returnObject['totalUpdated'] . ' column comment(s) across '
            . count($returnObject['tableDetails']) . ' table(s)';
    }

} catch (\PDOException $e) {
    $returnObject['msg'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($returnObject);
?>
