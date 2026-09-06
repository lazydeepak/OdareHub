Runtime storage lives here.

Keep these rules for GitHub-based deployments:

- Do not commit `storage/db_config.php` with real credentials.
- Create `storage/db_config.php` on the server from `storage/db_config.php.example`, or set `ERP_DB_*` / `DB_*` environment variables.
- This directory must stay writable by the web server for logs, imports, restores, snapshots, and release packaging.
