#!/bin/bash

if [[ $# -ne 1 ]]; then
	echo "Usage: $0 backup.sql.gz"
	exit 1
fi
if [[ ! -f "$1" ]] ; then
	echo "Backup file $1 does not exist."
	exit 1
fi
FILE=$(realpath "$1")

cd $(dirname "$0")
echo -e "Website \e[93m$(grep "^define.*'WP_SITEURL" ../wp-config.php | sed "s/.*https:\/\/\([^']*\).*/\1/")\e[0m"
read -p "Restore database from backup $1? <y/N> " prompt
if [[ $prompt != "y" && $prompt != "Y" ]] ; then
	echo 'Restore terminated by user.'
	exit
fi

source ./db-creds.sh

gunzip < $FILE | mysql --defaults-extra-file=.my.cnf
