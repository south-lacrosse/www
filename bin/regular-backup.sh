#!/bin/bash

# Main backup routine. Defaults to monthly on the 1st, weekly on Mondays,
# daily otherwise.

# See https://github.com/south-lacrosse/www-dev/blob/main/docs/backups.md

# Backs up everything which may change on a daily basis and cannot be easily
# recreated - so core WordPress tables along with sl_* tables

# Will backup to bin/backups/, and for weekly/monthly run off-site-backup.sh

if [[ $# -gt 1 ]]; then
	echo "Usage: $0 [daily|monthly|weekly]"
	exit 1
fi
if [[ $# -eq 0 ]]; then
	if [[ $(date +%d) == '01' ]]; then
		set -- 'monthly'
	elif [[ $(date +%u) -eq 1 ]]; then
		set -- 'weekly'
	else
		set -- 'daily'
	fi
fi
case "$1" in
	daily)
		DAYS=8
		;;
	weekly)
		DAYS=35 # keep 5 weekly backups
		;;
	monthly)
		DAYS=366 # keep a year of monthly backups
		;;
	*)
		echo "Usage: $0 [daily|monthly|weekly]"
		exit 1
		;;
esac

BACKUP_FILE="backups/db-$(date +%F-%H%M%S)-$1.sql.gz"

cd $(dirname "$0")
source ./db-creds.sh
PFX=$(grep table_prefix ../wp-config.php | awk -F "'" '{print $2}')
SL_TABLES=$(mysql --defaults-extra-file=.my.cnf -Bse "show tables like 'sl\_%'")
[[ $? -ne 0 ]] && exit 1

if [[ $OSTYPE == *win* ]]; then
	SL_TABLES=$(echo $SL_TABLES | tr -d '\r')
fi
BACKUP_TABLES="$SL_TABLES slc_remarks ${PFX}commentmeta ${PFX}comments ${PFX}links ${PFX}options ${PFX}postmeta ${PFX}posts ${PFX}term_relationships ${PFX}term_taxonomy ${PFX}termmeta ${PFX}terms ${PFX}usermeta ${PFX}users"

echo "Backing up $DBNAME to $(realpath $BACKUP_FILE)"
echo Dumping tables $BACKUP_TABLES

set -o pipefail # return any non-zero return code in the pipe
if ! mysqldump --defaults-extra-file=.my.cnf $DBNAME $BACKUP_TABLES | gzip > $BACKUP_FILE ; then
	rm -f $BACKUP_FILE
	exit 1
fi

PATTERN="db-*$1.sql.gz"
# explicitly point to /bin/find so this script will run on Windows which has its own find.exe
/bin/find backups/ -maxdepth 1 -name "$PATTERN" -type f -mtime +$DAYS -delete

[[ "$1" == 'daily' ]] && exit

# Only save production backups to Google Drive
[[ -z $(grep "^define.*WP_SITEURL.*www\.southlac" ../wp-config.php) ]] && exit

# Off site backups can be done with RClone or alternatively with a PHP script
./off-site-backup.sh $1
# php off-site.php backup $1
