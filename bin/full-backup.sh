#!/bin/bash

# Create a full backup, which means all South Lacrosse specific tables and all
# WordPress core tables. We don't backup non-core WordPress tables as they are
# created by the plugins, and should be able to recreated.

# Optionally supply a filename to be created in the backups/ directory. If
# it ends in .gz the file will be zipped, otherwise not - which should be used
# if we are on the www website on the production server, and want to load the
# backup into staging - no point in zipping and unzipping.

GZ=.gz
if [[ $# -gt 1 ]]; then
	echo "Usage: $0 [filename]"
	exit 1
fi
if [[ $# -eq 0 ]]; then
	BACKUP_FILE="backups/full-$(date +%F-%H%M%S).sql"
else
	if [[ ! "$1" =~ ^[a-zA-Z0-9_.-]+$ ]]; then
		echo 'Invalid filename, it must only include letters, 0-9, or _-.'
		exit 1
	fi
	if [[ "$1" =~ \.gz$ ]]; then
		BACKUP_FILE="backups/$(basename $1 .gz)"
	else
		BACKUP_FILE="backups/$1"
		GZ=
	fi
fi

cd $(dirname "$0")
source ./db-creds.sh
PFX=$(grep table_prefix ../wp-config.php | awk -F "'" '{print $2}')
SL_TABLES=$($MYSQL --defaults-extra-file=.my.cnf -Bse "show tables like 'sl%'")
[[ $? -ne 0 ]] && exit 1
if [[ $OSTYPE == *win* ]]; then
	SL_TABLES=$(echo $SL_TABLES | tr -d '\r')
fi
BACKUP_TABLES="$SL_TABLES ${PFX}commentmeta ${PFX}comments ${PFX}links ${PFX}options ${PFX}postmeta ${PFX}posts ${PFX}term_relationships ${PFX}term_taxonomy ${PFX}termmeta ${PFX}terms ${PFX}usermeta ${PFX}users"

echo "Backing up $DBNAME to $(realpath $BACKUP_FILE)$GZ"
echo Dumping tables $BACKUP_TABLES

if ! $MYSQLDUMP --defaults-extra-file=.my.cnf $DBNAME $BACKUP_TABLES > $BACKUP_FILE ; then
	rm $BACKUP_FILE
	exit 1
fi
[[ "$GZ" == '.gz' ]] && gzip $BACKUP_FILE
