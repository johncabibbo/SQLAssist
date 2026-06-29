# SQL Assist

SQL Assist is a lightweight, web-based tool for **browsing and documenting MySQL
(and optionally Microsoft SQL Server) database schemas**. It reads a server's
`information_schema`, lets you view every table and column, and makes it easy to
write, auto-fill, and store table/column descriptions — so your schema stays
self-documenting.

Copyright © 2026 Cloud Box 9 Inc. All rights reserved. See [LICENSE](LICENSE).

---

## Features

- **Schema browser** — pick a database, page through its tables, and inspect
  every column with type, key, null, and default information.
- **Editable comments** — add or edit table and column descriptions inline and
  save them as native MySQL `COMMENT`s, to the SQL Assist store, or both.
- **Auto-fill comments** — one click fills standard columns (`title`,
  `description`, `createdDate`, `lastModifiedBy`, …), primary keys, and foreign
  keys with sensible default descriptions.
- **Lookup-table tooling** — mark lookup tables, append their value lists to the
  table comment (e.g. `1-Active, 2-Inactive, 3-Pending`), and view an icon
  gallery for lookup rows.
- **Bulk CLI fill** — populate blank comments for an entire database from the
  command line (`xhr/runBulkFillCLI.php`).
- **Model-function documentation** — optional screen for documenting model
  functions per database.
- **Dark mode**, keep-alive sessions, and optional IP allow-listing.

## Quick start

```bash
# 1. Place this directory in a web-accessible path (Apache/PHP).
# 2. Copy your settings into config.php (connections + login).
# 3. Create the SQL Assist storage tables (see INSTALLATION.md).
# 4. Browse to the SQLAssist URL and log in.
```

## Documentation

| Guide | Purpose |
|-------|---------|
| [INSTALLATION.md](INSTALLATION.md)   | Requirements, install steps, and storage-schema setup |
| [CONFIGURATION.md](CONFIGURATION.md) | Full `config.php` settings reference |
| [USAGE.md](USAGE.md)                 | Using the application day to day |

## Project layout

```
SQLAssist/
├── config.php            # Your configuration (connections, login, options)
├── setting.php       # Session bootstrap (loads config.php)
├── index.php         # Login / entry point
├── sql1.php          # Main schema browser page
├── modelFunct.php    # Model-function documentation page
├── xhr/              # AJAX/CLI endpoints (was "api/")
├── model/            # Data access (SQLStruct.php, SQLStructMS.php, sessions)
├── view/             # HTML view includes (.inc)
├── js/  css/  image/ # Front-end assets
└── *.md  LICENSE     # Documentation & license
```

> **Note:** The AJAX endpoint folder is named **`xhr/`** (renamed from the
> original `api/`). All front-end and server references use `xhr/`.

## Support

Email: support@cloudbox9.com
