#!/bin/bash

# Run weekly DMARC tasks
# - Email weekly summary to Webmaster
# - Backup dmarc tables
# - Delete backups over 60 days old

cd $(dirname "$0")
BIN=$(pwd)
cd ../sub/dmarc-srg || exit
echo Running DMARC tasks
$BIN/db-backup.sh dmarc
# explicitly point to /bin/find so this script will run on Windows which has its own find.exe
# -mtime +60 = days
/bin/find $BIN/backups/ -maxdepth 1 -name "dmarc-*.sql.gz" -type f -mtime +60 -delete

php utils/summary_report.php domain=southlacrosse.org.uk period=lastweek
echo DMARC tasks comleted
