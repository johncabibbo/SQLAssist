# Installation

This guide covers installing SQL Assist and creating the storage tables it uses
to keep table/column descriptions.

Copyright © 2026 Cloud Box 9 Inc. All rights reserved.

---

## 1. Requirements

| Component | Minimum |
|-----------|---------|
| Web server | Apache 2.0+ (or any PHP-capable server) |
| PHP | 7.0+ with PDO and `pdo_mysql` |
| MySQL | 5.6+ (read access to `information_schema`) |
| MS SQL Server *(optional)* | `pdo_sqlsrv`/`sqlsrv` or ODBC extension |

The MySQL account used by SQL Assist **does not need to be root**, but it must
have **read access to the `information_schema` database** of every server you
want to browse. To save native MySQL comments (`$commentSaveLocation` `B` or
`C`), it also needs `ALTER`/`COMMENT` privileges on the target tables.

## 2. Deploy the files

1. Copy the `SQLAssist/` directory into any web-accessible location, e.g.
   `/var/www/html/SQLAssist`, or point a virtual host / URL directly at it.
2. Ensure the web server user can read the directory.
3. Confirm `index.php` loads in a browser (you should see the login screen).

## 3. Configure `config.php`

Open `config.php` and set your connection details, login credentials, and options.
See **[CONFIGURATION.md](CONFIGURATION.md)** for a full reference. At minimum,
set the target MySQL connection:

```php
$mysql_dsn  = "mysql:host=YOURHOST;port=3306;dbname=information_schema;charset=utf8mb4";
$mysql_user = "yourUser";
$mysql_pass = "yourPassword";
```

> ⚠️ **Never commit real credentials** to a public repository. The values
> shipped in `config.php` are safe local placeholders.

## 4. Create the SQL Assist storage tables

When `$commentSaveLocation` is `A` (SQL Assist store) or `C` (both), SQL Assist
keeps descriptions in its own schema — the database named in `$mysqlSA_dsn`
(default `SQLAssist`). Create that schema and the two core tables:

```sql
CREATE DATABASE IF NOT EXISTS SQLAssist
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE SQLAssist;

-- Column & table-level descriptions.
-- A row with columnName = 'TABLEDEFINITION' stores the table-level comment.
CREATE TABLE IF NOT EXISTS colDef (
  colDefId      INT NOT NULL AUTO_INCREMENT,
  databaseName  VARCHAR(128) NOT NULL,
  tableName     VARCHAR(128) NOT NULL,
  columnName    VARCHAR(128) NOT NULL,
  columnDesc    TEXT,
  PRIMARY KEY (colDefId),
  UNIQUE KEY uq_colDef (databaseName, tableName, columnName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table metadata (e.g. tableType = 'Lookup' for lookup tables).
CREATE TABLE IF NOT EXISTS tableDef (
  tableDef      INT NOT NULL AUTO_INCREMENT,
  databaseName  VARCHAR(128) NOT NULL,
  tableName     VARCHAR(128) NOT NULL,
  tableType     VARCHAR(64) DEFAULT NULL,
  PRIMARY KEY (tableDef),
  UNIQUE KEY uq_tableDef (databaseName, tableName)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

> If you only ever save to native MySQL comments (`$commentSaveLocation = 'B'`),
> these tables are optional — but the lookup-table tooling stores its metadata
> in `tableDef`, so creating them is recommended.

### Optional: model-function documentation

The model-function screen (`modelFunct.php`) reads/writes a `docModelFunction`
table in the SQL Assist store. Create it only if you use that feature:

```sql
CREATE TABLE IF NOT EXISTS docModelFunction (
  docModelFunctionId INT NOT NULL AUTO_INCREMENT,
  model         VARCHAR(128) NOT NULL,
  functName     VARCHAR(255) DEFAULT NULL,
  functDesc     TEXT,
  PRIMARY KEY (docModelFunctionId)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

## 5. Log in

Browse to the SQLAssist URL and sign in with the `$loginUsername` /
`$loginPassword` you set in `config.php`. You'll land on the schema browser.

## 6. (Optional) Lock down access

Set `$allowedIPList` in `config.php` to a comma-delimited list of IPs to restrict
who can reach the app. See [CONFIGURATION.md](CONFIGURATION.md#ip-based-security).

---

Next: **[CONFIGURATION.md](CONFIGURATION.md)** · **[USAGE.md](USAGE.md)**
