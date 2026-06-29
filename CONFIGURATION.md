# Configuration

All configuration lives in **`db.php`** at the project root. This is the only
file you need to edit for a standard install. Each setting is described below.

Copyright © 2026 Cloud Box 9 Inc. All rights reserved.

---

## Target MySQL connection

The database server whose schema you want to browse. Requires read access to
`information_schema`.

```php
$mysql_dsn  = "mysql:host=127.0.0.1;port=3306;dbname=information_schema;charset=utf8mb4";
$mysql_user = "root";
$mysql_pass = "root";
```

| Variable | Description |
|----------|-------------|
| `$mysql_dsn`  | PDO DSN pointing at `information_schema` on the target server |
| `$mysql_user` | MySQL username (read access to `information_schema`) |
| `$mysql_pass` | MySQL password |

## SQL Assist storage connection

Where descriptions are stored when `$commentSaveLocation` is `A` or `C`. Often
the same server as the target, using a dedicated `SQLAssist` schema (see
[INSTALLATION.md](INSTALLATION.md#4-create-the-sql-assist-storage-tables)).

```php
$mysqlSA_dsn  = "mysql:host=127.0.0.1;port=3306;dbname=SQLAssist;charset=utf8mb4";
$mysqlSA_user = "root";
$mysqlSA_pass = "root";
```

## Microsoft SQL Server connection (optional)

Leave blank if unused. Requires the PHP `sqlsrv`/`pdo_sqlsrv` (or ODBC)
extension. Used by `model/SQLStructMS.php`.

```php
$MSserver = "";
$MSuser   = "";
$MSpass   = "";
$MSdb     = "";
```

## Database-backed session store (optional)

Used by `setting.php` so SQL Assist can share a session with another
`*.cloudbox9.com` app. **If the connection fails, SQL Assist automatically falls
back to standard file-based PHP sessions** — so for a standalone install you can
leave these at their defaults.

```php
$sessionDb_dsn  = "mysql:host=127.0.0.1;port=3306;dbname=SQLAssist;charset=utf8mb4";
$sessionDb_user = "root";
$sessionDb_pass = "root";
```

## Login credentials

The username/password for the SQL Assist UI login screen.

```php
$loginUsername = 'admin';
$loginPassword = 'changeMe';
```

> Change these before deploying. Passwords must be 4+ characters.

## IP-based security

Restrict which clients may reach the app.

```php
$allowedIPList = '*';            // allow from anywhere (default)
//$allowedIPList = '127.0.0.1';  // single IP
//$allowedIPList = '10.0.0.5, 203.0.113.7';  // comma-delimited list
```

When not `*`, any request from an IP not in the list receives **`Access
Denied`**.

## Comment save location

Controls where table/column descriptions are written when you save:

| Value | Behavior |
|-------|----------|
| `'A'` | SQL Assist database only (`$mysqlSA_dsn`) — **default** |
| `'B'` | Target database only (native MySQL `COMMENT`) |
| `'C'` | Both locations |

```php
$commentSaveLocation = 'A';
```

> For `'B'` or `'C'`, the `$mysql_user` needs privileges to alter/comment the
> target tables.

## Page title

```php
$pageTitle = "SQLAssist";   // shown in the browser tab / header
```

---

## Security notes

- **Do not commit real credentials.** Keep production values out of any public
  repository (consider a deploy-time copy of `db.php` or environment-specific
  overrides).
- The CLI bulk-fill script (`xhr/runBulkFillCLI.php`) loads its credentials from
  this same `db.php` — there are no separate secrets to maintain.

---

Next: **[USAGE.md](USAGE.md)**
