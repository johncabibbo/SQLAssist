# Usage

How to use SQL Assist day to day. Assumes you have completed
[INSTALLATION.md](INSTALLATION.md) and [CONFIGURATION.md](CONFIGURATION.md).

Copyright © 2026 Cloud Box 9 Inc. All rights reserved.

---

## Logging in

1. Browse to the SQLAssist URL (e.g. `https://yourhost/SQLAssist/`).
2. Enter the `$loginUsername` / `$loginPassword` from `db.php`.
3. You'll be taken to the schema browser (`sql1.php`).

> If SQL Assist shares a session with another cloudbox9.com app and you are
> already signed in there, you'll be logged in automatically.

## Browsing a schema

1. **Select a database** from the dropdown. SQL Assist lists every database the
   connection can see in `information_schema`.
2. **Select a table** from the table dropdown, or use the **◀ / ▶** buttons to
   page through tables one at a time. Lookup tables are flagged with a `*`.
3. The grid shows each column with its type, key, nullability, default, and its
   current description.

## Editing table & column descriptions

- Click into a description field, type your text, and save. Where the text is
  stored depends on `$commentSaveLocation` (SQL Assist store, native MySQL
  `COMMENT`, or both — see [CONFIGURATION.md](CONFIGURATION.md#comment-save-location)).
- The **table-level** description is stored internally under the special
  column name `TABLEDEFINITION`.

## Toolbar tools

| Tool | What it does |
|------|--------------|
| **Auto-Fill Column Comments** | Fills missing comments for the **current table**: standard columns (`title`, `description`, `createdDate`, `createdBy`, `lastModifiedDate`, `lastModifiedBy`, `deleted`, `displayOrder`, `icon`, `userId`, `orgId`, …), primary keys (*"Auto-Assigned Primary Key"*), and foreign keys (*"Foreign Key to TABLE.COLUMN"*). |
| **Append Column Info** | Lookup tables only. Reads the first two columns of the current table, queries all rows, and appends a compact value list to the table comment, e.g. `1-Active, 2-Inactive, 3-Pending`. Re-running replaces any existing value block. |
| **Mark Lookup Tables** | Scans every table in the database and tags those whose comment starts with *"Lookup Table"* (or whose type is set to `Lookup`) so they get the `*` indicator and lookup-specific features. |
| **Run All (3-step)** | Convenience action that runs, across the whole database: (1) Mark Lookup Tables → (2) Auto-Fill all column comments → (3) Append value lists to every lookup table. |
| **Toolbar Help (?)** | Opens an in-app explanation of each button. |
| **🌙 Dark / Light** | Toggles dark mode; your preference is remembered. |

## Lookup tables

A table is treated as a *lookup table* when its comment starts with
`Lookup Table` or its `tableType` is set to `Lookup`. For these tables you can:

- Append a values summary to the comment (**Append Column Info**).
- See the icon gallery (rows that have an `icon` column).
- Get missing-standard-column checks.

## Model-function documentation (optional)

`modelFunct.php` provides a screen to document model functions per database,
stored in the `docModelFunction` table. Use it to keep a searchable catalog of
what each model function does. Create the table first (see
[INSTALLATION.md](INSTALLATION.md#optional-model-function-documentation)).

## Bulk-fill from the command line

To fill blank column comments for an **entire database** without using the UI,
run the CLI script. It loads its connection from `db.php`:

```bash
cd SQLAssist/xhr
php runBulkFillCLI.php <databaseName>
```

The script reports how many tables were processed and how many comments were
written. Its save behavior follows `$commentSaveLocation` in `db.php`.

## Logging out

Click **Logout** (top-right) or browse to `logout.php` to end the session.

## Troubleshooting

| Symptom | Likely cause / fix |
|---------|--------------------|
| "There was an error in communicating with your MySQL database." | Wrong connection details in `db.php`, or the user lacks access to `information_schema`. |
| **Access Denied** on load | Your client IP isn't in `$allowedIPList`. Add it or set `'*'`. |
| Comments don't persist | Using `$commentSaveLocation` `A`/`C` but the `SQLAssist` storage tables don't exist — see [INSTALLATION.md](INSTALLATION.md#4-create-the-sql-assist-storage-tables). |
| Native comments not saved (`B`/`C`) | The MySQL user lacks `ALTER`/`COMMENT` privileges on the target tables. |
| MS SQL Server tables missing | The `pdo_sqlsrv`/ODBC extension isn't installed, or `$MSserver` etc. are blank. |

---

Back to **[README.md](README.md)**
