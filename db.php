<?php
/**
 * db.php — SQL Assist configuration
 *
 * Copyright © 2026 Cloud Box 9 Inc. All rights reserved.
 *
 * Configure your database connections, login, and options below.
 * The values shipped here are safe local placeholders — replace them
 * with your own. Do NOT commit real credentials to a public repository.
 */

// ---------------------------------------------------------------------------
// Target MySQL connection (the database whose schema you want to browse).
// The MySQL user does not need to be root but DOES require read access to
// the information_schema database.
// ---------------------------------------------------------------------------
$mysql_dsn  = "mysql:host=127.0.0.1;port=3306;dbname=information_schema;charset=utf8mb4";
$mysql_user = "root";
$mysql_pass = "root";

// ---------------------------------------------------------------------------
// SQL Assist storage connection (where table/column comments are stored when
// $commentSaveLocation is 'A' or 'C'). Often the same server as above, using
// a dedicated `SQLAssist` schema. Leave blank to disable SQL Assist storage.
// ---------------------------------------------------------------------------
$mysqlSA_dsn  = "mysql:host=127.0.0.1;port=3306;dbname=SQLAssist;charset=utf8mb4";
$mysqlSA_user = "root";
$mysqlSA_pass = "root";

// ---------------------------------------------------------------------------
// Microsoft SQL Server connection (optional). Leave blank if unused.
// Requires the PHP sqlsrv / pdo_sqlsrv (or ODBC) extension.
// ---------------------------------------------------------------------------
$MSserver = "";
$MSuser   = "";
$MSpass   = "";
$MSdb     = "";

// ---------------------------------------------------------------------------
// Database-backed session store (optional).
// Used by setting.php for cross-app session sharing. If the connection
// fails, SQL Assist automatically falls back to standard file-based
// PHP sessions, so these may be left as-is for a standalone install.
// ---------------------------------------------------------------------------
$sessionDb_dsn  = "mysql:host=127.0.0.1;port=3306;dbname=SQLAssist;charset=utf8mb4";
$sessionDb_user = "root";
$sessionDb_pass = "root";

// ---------------------------------------------------------------------------
// Login Username & Password (front-end login for the SQL Assist UI).
// ---------------------------------------------------------------------------
$loginUsername = 'admin';
$loginPassword = 'changeMe';

// ---------------------------------------------------------------------------
// IP Based Security
// If allowedIPList = '*', allow connections from anywhere.
// To limit by IP, set to a comma-delimited list of IP addresses.
// ---------------------------------------------------------------------------
$allowedIPList = '*';
//$allowedIPList = '127.0.0.1';

// ---------------------------------------------------------------------------
// Comment Save Location
// Controls where table and column comments are saved:
//   'A' = SQL Assist database only ($mysqlSA_dsn) — default
//   'B' = Target database only (native MySQL COMMENT)
//   'C' = Both locations
// ---------------------------------------------------------------------------
$commentSaveLocation = 'A';

$pageTitle = "SQLAssist";
?>
