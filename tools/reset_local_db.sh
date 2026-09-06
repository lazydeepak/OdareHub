#!/bin/zsh
set -euo pipefail

echo "Resetting erp_local DB - truncating all tables..."
mysql -h127.0.0.1 -P3306 -uroot erp_local -e "
SET FOREIGN_KEY_CHECKS=0;
SET UNIQUE_CHECKS=0;
SET SQL_LOG_BIN=0;

$(mysql -h127.0.0.1 -P3306 -uroot erp_local -Nse 'SELECT GROUP_CONCAT(CONCAT(\"TRUNCATE TABLE \", QUOTE(table_name), \";\") SEPARATOR \" \") FROM information_schema.tables WHERE table_schema=\"erp_local\" AND table_type=\"BASE TABLE\";');

SET FOREIGN_KEY_CHECKS=1;
SET UNIQUE_CHECKS=1;
SET SQL_LOG_BIN=1;
"

echo "DB reset complete. All tables truncated."

