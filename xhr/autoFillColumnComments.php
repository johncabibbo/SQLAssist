<?php
/**
 * autoFillColumnComments.php
 *
 * Auto-fills BLANK column comments for a single table using standard rules:
 *   - Primary Key (int, auto-increment, first column, blank comment) → "Auto-Assigned Primary Key"
 *   - Foreign Key (blank comment) → "Foreign Key to TABLE.COLUMN\nValues: 1-X, 2-Y, 3-Z ..."
 *   - displayOrder (blank comment) → display order text
 *   - displayOrderGroup (blank comment) → display order text
 *   - displayClass (blank comment) → "CSS Class applied when displayed in HTML"
 *   - userId in lookup table (blank comment) → Lookup Table Rule (userId)
 *   - orgId in lookup table (blank comment) → Lookup Table Rule (orgId)
 *   - CB9 Standard columns: createdDate, createdBy, lastModifiedDate, lastModifiedBy, deleted
 *   - Named columns: title, description, abr, deletable, icon
 *   - Columns in [NotUsed] tables → prefixed with "[NotUsed] "
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
    'success'             => 0,
    'msg'                 => '',
    'pkComments'          => 0,
    'fkComments'          => 0,
    'displayComments'     => 0,
    'displayClassComments'=> 0,
    'lookupRuleComments'  => 0,
    'standardComments'    => 0,
    'namedComments'       => 0,
    'total'               => 0,
    'details'             => []
];

foreach ($_GET as $key => $value) {
    $_POST[$key] = $value;
}

if (empty($_POST['databaseName']) || empty($_POST['tableName'])) {
    $returnObject['msg'] = 'Missing required parameters: databaseName and tableName';
    echo json_encode($returnObject);
    exit();
}

$databaseName = $_POST['databaseName'];
$tableName    = $_POST['tableName'];

$DISPLAY_ORDER_TEXT = 'Records are ordered by displayOrderGroup asc, displayOrder asc, title asc';

try {
    // Target DB connection (information_schema user has cross-db ALTER access)
    $db   = new \PDO($mysql_dsn, $mysql_user, $mysql_pass);
    $db->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // SQLAssist DB connection (for colDef dual-write)
    $dbSA = new \PDO($mysqlSA_dsn, $mysqlSA_user, $mysqlSA_pass);
    $dbSA->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

    // =========================================================================
    // 1. Get all columns for this table
    // =========================================================================
    $stmt = $db->prepare("
        SELECT
            COLUMN_NAME,
            COLUMN_TYPE,
            DATA_TYPE,
            COLUMN_KEY,
            EXTRA,
            IS_NULLABLE,
            COLUMN_DEFAULT,
            COLUMN_COMMENT,
            ORDINAL_POSITION
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = :databaseName
          AND TABLE_NAME   = :tableName
        ORDER BY ORDINAL_POSITION
    ");
    $stmt->execute([':databaseName' => $databaseName, ':tableName' => $tableName]);
    $columns = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    if (empty($columns)) {
        $returnObject['msg'] = "Table not found: $databaseName.$tableName";
        echo json_encode($returnObject);
        exit();
    }

    // =========================================================================
    // 2. Get all FK constraints for this table
    // =========================================================================
    $stmt = $db->prepare("
        SELECT
            kcu.COLUMN_NAME,
            kcu.REFERENCED_TABLE_NAME,
            kcu.REFERENCED_COLUMN_NAME
        FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
        WHERE kcu.TABLE_SCHEMA         = :databaseName
          AND kcu.TABLE_NAME           = :tableName
          AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
    ");
    $stmt->execute([':databaseName' => $databaseName, ':tableName' => $tableName]);
    $fkRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Index FK info by column name
    $fkMap = [];
    foreach ($fkRows as $fk) {
        $fkMap[$fk['COLUMN_NAME']] = [
            'refTable'  => $fk['REFERENCED_TABLE_NAME'],
            'refColumn' => $fk['REFERENCED_COLUMN_NAME']
        ];
    }

    // =========================================================================
    // 3. Get table comment (for lookup table and [NotUsed] detection)
    // =========================================================================
    $stmtTbl = $db->prepare("
        SELECT TABLE_COMMENT
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = :databaseName
          AND TABLE_NAME   = :tableName
    ");
    $stmtTbl->execute([':databaseName' => $databaseName, ':tableName' => $tableName]);
    $tableComment  = $stmtTbl->fetchColumn() ?: '';
    $isLookupTable = (stripos($tableComment, 'lookup table') === 0);
    $isNotUsed     = (strpos($tableComment, '[NotUsed]') === 0);

    // =========================================================================
    // 4. Helper: dual-write a comment to MySQL native COMMENT + SQLAssist colDef
    // Only writes if the SQLAssist colDef comment is also blank/missing.
    // =========================================================================
    $writeComment = function($col, $newComment, $forceColDef = false) use ($db, $dbSA, $databaseName, $tableName, $commentSaveLocation, &$returnObject) {
        $colName     = $col['COLUMN_NAME'];
        $columnType  = $col['COLUMN_TYPE'];
        $nullable    = ($col['IS_NULLABLE'] === 'YES') ? 'NULL' : 'NOT NULL';
        $default     = '';
        if ($col['COLUMN_DEFAULT'] !== null) {
            $default = "DEFAULT '" . addslashes($col['COLUMN_DEFAULT']) . "'";
        } elseif ($col['IS_NULLABLE'] === 'YES') {
            $default = 'DEFAULT NULL';
        }
        $extra       = (stripos($col['EXTRA'], 'auto_increment') !== false) ? 'AUTO_INCREMENT' : '';

        // --- Write to MySQL native COMMENT ---
        if ($commentSaveLocation === 'A' || $commentSaveLocation === 'B' || $commentSaveLocation === 'C') {
            if ($commentSaveLocation !== 'A') {
                try {
                    $alterSQL = "ALTER TABLE `$databaseName`.`$tableName`
                        MODIFY COLUMN `$colName` $columnType $nullable $default $extra COMMENT :comment";
                    $stmt = $db->prepare($alterSQL);
                    $stmt->execute([':comment' => $newComment]);
                } catch (\PDOException $e) {
                    // Log and continue — colDef write still proceeds below
                    error_log("autoFillColumnComments ALTER skipped [$databaseName.$tableName.$colName]: " . $e->getMessage());
                }
            }
        }

        // --- Write to SQLAssist colDef (only if colDef entry is also blank) ---
        if ($commentSaveLocation === 'A' || $commentSaveLocation === 'C') {
            // Check existing colDef entry
            $stmtSA = $dbSA->prepare("
                SELECT colDefId, columnDesc
                FROM colDef
                WHERE databaseName = :db AND tableName = :tbl AND columnName = :col
            ");
            $stmtSA->execute([':db' => $databaseName, ':tbl' => $tableName, ':col' => $colName]);
            $existing = $stmtSA->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                // Update if blank, or if this is a standard/named rule (force overwrite)
                if ($forceColDef || trim($existing['columnDesc'] ?? '') === '') {
                    $stmtSA = $dbSA->prepare("
                        UPDATE colDef SET columnDesc = :desc
                        WHERE colDefId = :id
                    ");
                    $stmtSA->execute([':desc' => $newComment, ':id' => $existing['colDefId']]);
                }
            } else {
                // Insert new row
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

        $returnObject['details'][] = "$colName: " . substr($newComment, 0, 60) . (strlen($newComment) > 60 ? '...' : '');
        $returnObject['total']++;
    };

    // =========================================================================
    // 5. Process each column
    // =========================================================================
    foreach ($columns as $col) {
        $colName      = $col['COLUMN_NAME'];
        $colNameLower = strtolower(trim($colName));
        $dataType     = strtolower($col['DATA_TYPE']);
        $comment      = trim($col['COLUMN_COMMENT'] ?? '');

        // Helper: apply [NotUsed] prefix when table is marked [NotUsed]
        $makeComment = function($text) use ($isNotUsed) {
            return $isNotUsed ? '[NotUsed] ' . $text : $text;
        };

        // =====================================================================
        // Named columns — always fill, even if MySQL comment is non-blank.
        // These are standard well-known field names with fixed meanings.
        // =====================================================================
        if ($colNameLower === 'title') {
            $writeComment($col, $makeComment('Friendly Title'), true);
            $returnObject['namedComments']++;
            continue;
        }
        if ($colNameLower === 'description') {
            $writeComment($col, $makeComment('Friendly Description'), true);
            $returnObject['namedComments']++;
            continue;
        }
        if ($colNameLower === 'abr') {
            $writeComment($col, $makeComment('Abbreviation'), true);
            $returnObject['namedComments']++;
            continue;
        }
        if ($colNameLower === 'deletable') {
            $writeComment($col, $makeComment('Is this row deletable? If 0, only system admins (UserTypeId=1) can set deleted=1'), true);
            $returnObject['namedComments']++;
            continue;
        }
        if ($colNameLower === 'icon') {
            $writeComment($col, $makeComment('Image Path & Filename'), true);
            $returnObject['namedComments']++;
            continue;
        }

        // =====================================================================
        // CB9 Standard columns — always fill, even if MySQL comment is non-blank.
        // =====================================================================
        if ($colNameLower === 'createddate') {
            $writeComment($col, $makeComment('Date/time when this record was created'), true);
            $returnObject['standardComments']++;
            continue;
        }
        if ($colNameLower === 'createdby') {
            $writeComment($col, $makeComment('UserId of the user who created this record'), true);
            $returnObject['standardComments']++;
            continue;
        }
        if ($colNameLower === 'lastmodifieddate') {
            $writeComment($col, $makeComment('Date/time when this record was last modified'), true);
            $returnObject['standardComments']++;
            continue;
        }
        if ($colNameLower === 'lastmodifiedby') {
            $writeComment($col, $makeComment('UserId of the user who last modified this record'), true);
            $returnObject['standardComments']++;
            continue;
        }
        if ($colNameLower === 'deleted') {
            $writeComment($col, $makeComment('1-Yes, 0-No'), true);
            $returnObject['standardComments']++;
            continue;
        }
        if ($colNameLower === 'displayorder' || $colNameLower === 'displayordergroup') {
            $writeComment($col, $makeComment($DISPLAY_ORDER_TEXT), true);
            $returnObject['displayComments']++;
            continue;
        }
        if ($colNameLower === 'displayclass') {
            $writeComment($col, $makeComment('CSS Class applied when displayed in HTML'), true);
            $returnObject['displayClassComments']++;
            continue;
        }

        // =====================================================================
        // Lookup table rules — always fill for recognised lookup-table columns.
        // =====================================================================
        if ($colNameLower === 'userid' && $isLookupTable) {
            $writeComment($col, $makeComment("Lookup Table Rule\nIf userd > 0, only display to users where their session.userId = THIS.userId or THIS.userId is null. If UserId is null this record is displayed for all users. "), true);
            $returnObject['lookupRuleComments']++;
            continue;
        }
        if ($colNameLower === 'orgid' && $isLookupTable) {
            $writeComment($col, $makeComment("Lookup Table Rule\nIf orgId > 0, only display to users where their session.orgId = THIS.orgId or THIS.orgId is null. If orgId is null this record is displayed for all users. \n"), true);
            $returnObject['lookupRuleComments']++;
            continue;
        }

        // =====================================================================
        // Primary Key — always set if comment doesn't already start with the
        // expected prefix, regardless of whether comment is blank.
        // =====================================================================
        if ($col['ORDINAL_POSITION'] == 1
            && $col['COLUMN_KEY'] === 'PRI'
            && stripos($col['EXTRA'], 'auto_increment') !== false
            && in_array($dataType, ['int', 'integer', 'bigint', 'smallint', 'tinyint', 'mediumint'])
        ) {
            if (stripos($comment, 'Auto-Assigned Primary Key') !== 0) {
                $writeComment($col, $makeComment('Auto-Assigned Primary Key'), true);
                $returnObject['pkComments']++;
            }
            continue;
        }

        // =====================================================================
        // Foreign Key — always set if comment doesn't already start with
        // "Foreign Key to", regardless of whether comment is blank.
        // =====================================================================
        if (isset($fkMap[$colName])) {
            $refTable  = $fkMap[$colName]['refTable'];
            $refColumn = $fkMap[$colName]['refColumn'];

            if (stripos($comment, 'Foreign Key to') !== 0) {
                // Skip sample values for users and org tables
                $skipValues = (
                    (strtolower($refTable) === 'users' && strtolower($refColumn) === 'userid') ||
                    (strtolower($refTable) === 'org'   && strtolower($refColumn) === 'orgid')
                );

                $sampleValues = '';
                if (!$skipValues) {
                    // Find best text column in referenced table
                    $stmtRef = $db->prepare("
                        SELECT COLUMN_NAME
                        FROM INFORMATION_SCHEMA.COLUMNS
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

                $writeComment($col, $makeComment($newComment), true);
                $returnObject['fkComments']++;
            }
            continue;
        }

        // =====================================================================
        // All remaining columns — only process if MySQL comment is blank.
        // =====================================================================
        if ($comment !== '') {
            continue;
        }
    }

    // =========================================================================
    // 6. Build response message
    // =========================================================================
    $returnObject['success'] = 1;
    $parts = [];
    if ($returnObject['pkComments'] > 0) {
        $parts[] = $returnObject['pkComments'] . ' primary key comment(s)';
    }
    if ($returnObject['fkComments'] > 0) {
        $parts[] = $returnObject['fkComments'] . ' foreign key comment(s)';
    }
    if ($returnObject['displayComments'] > 0) {
        $parts[] = $returnObject['displayComments'] . ' display order comment(s)';
    }
    if ($returnObject['displayClassComments'] > 0) {
        $parts[] = $returnObject['displayClassComments'] . ' display class comment(s)';
    }
    if ($returnObject['lookupRuleComments'] > 0) {
        $parts[] = $returnObject['lookupRuleComments'] . ' lookup table rule comment(s)';
    }
    if ($returnObject['standardComments'] > 0) {
        $parts[] = $returnObject['standardComments'] . ' standard column comment(s)';
    }
    if ($returnObject['namedComments'] > 0) {
        $parts[] = $returnObject['namedComments'] . ' named column comment(s)';
    }
    if (empty($parts)) {
        $returnObject['msg'] = 'No blank comments found to fill';
    } else {
        $returnObject['msg'] = 'Added ' . implode(', ', $parts);
    }

} catch (\PDOException $e) {
    $returnObject['msg'] = 'Database error: ' . $e->getMessage();
}

echo json_encode($returnObject);
?>
