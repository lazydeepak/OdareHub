#!/bin/bash

SERVER="oqtkzvov@spark"
REMOTE_DB="oqtkzvov_ERP2"
REMOTE_USER="oqtkzvov_ERP2"
REMOTE_PASS="EcUqBsEpShwhvzBZN35C"

LOCAL_DB="erp_local"

echo "Dumping server database..."

ssh $SERVER "mysqldump -u $REMOTE_USER -p$REMOTE_PASS $REMOTE_DB" > /tmp/erp_dump.sql

echo "Importing to local database..."

mysql -u root $LOCAL_DB < /tmp/erp_dump.sql

echo "Database sync complete."