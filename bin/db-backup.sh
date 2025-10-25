#!/bin/bash

# Backup tables. Options are:
#  sl - all SEMLA tables (prefix sl_, slc_, and slh_)
#  slonly - just the sl_ tables, so not slc_ or slh_
#  slc - all SEMLA current fixtures/flags/league tables (prefix slc_)
#  slh - all SEMLA history tables (prefix slh_)
#  wp - all core WordPress tables
#  dmarc - DMARC email authentication reports
#  table-name - must be specific table, starting sl_, slc_, slh_, wp_, or dmarc_

if [[ $# -ne 1 ]]; then
	echo "Usage: $0 sl|slc|slh|wp|dmarc|table-name"
	exit 1
fi

cd $(dirname "$0")
source ./db-creds.sh
case "$1" in
	wp)
		PFX=$(grep table_prefix ../wp-config.php | awk -F "'" '{print $2}')
		BACKUP_TABLES="${PFX}commentmeta ${PFX}comments ${PFX}links ${PFX}options ${PFX}postmeta ${PFX}posts ${PFX}term_relationships ${PFX}term_taxonomy ${PFX}termmeta ${PFX}terms ${PFX}usermeta ${PFX}users"
		;;
	sl|slc|slh|dmarc)
		BACKUP_TABLES=$($MYSQL --defaults-extra-file=.my.cnf -Bse "show tables like '$1%'")
		[[ $? -ne 0 ]] && exit 1
		if [[ $OSTYPE == *win* ]]; then
			BACKUP_TABLES=$(echo $BACKUP_TABLES | tr -d '\r')
		fi
		;;
	slonly)
		BACKUP_TABLES=$($MYSQL --defaults-extra-file=.my.cnf -Bse "show tables like 'sl\_%'")
		[[ $? -ne 0 ]] && exit 1
		if [[ $OSTYPE == *win* ]]; then
			BACKUP_TABLES=$(echo $BACKUP_TABLES | tr -d '\r')
		fi
		;;
	*)
		TEST_TABLES=$($MYSQL --defaults-extra-file=.my.cnf -Bse "show tables like '$1'")
		[[ $? -ne 0 ]] && exit 1
		if [[ -z $TEST_TABLES ]]; then
			echo "Unknown table. Usage: $0 sl|slc|slh|wp|dmarc|table-name"
			exit 1
		fi
		BACKUP_TABLES=$1
		;;
esac

BACKUP_FILE="backups/$1-$(date +%F-%H%M%S).sql.gz"
echo "Backing up $DBNAME to $(realpath $BACKUP_FILE)"
echo Dumping tables $BACKUP_TABLES

set -o pipefail # return any non-zero return code in the pipe
if !  $MYSQLDUMP --defaults-extra-file=.my.cnf $DBNAME $BACKUP_TABLES | gzip > $BACKUP_FILE ; then
	rm -f $BACKUP_FILE
	exit 1
fi
