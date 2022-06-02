#!/bin/bash

# Run arbitrary SQL using the credentials from wp-config.php
# If there are no args then will run the mysql client, leaving it open
# If there are args then run the sql in that file, and will unzip if needed

if [[ $# -gt 1 ]]; then
	echo "Usage: $0 [sql-file]"
	exit 1
fi
if [[ $# -eq 1 ]]; then
	if [[ ! -f "$1" ]] ; then
		echo "File $1 does not exist."
		exit 1
	fi
	FILE=$(realpath "$1")
fi

cd $(dirname "$0")
source ./db-creds.sh
echo -e "Running SQL on \e[93m$(grep "^define.*'WP_SITEURL" ../wp-config.php | sed "s/.*https:\/\/\([^']*\).*/\1/")\e[0m"

if [[ $# -eq 0 ]]; then
	mysql --defaults-extra-file=.my.cnf
	exit
fi

if [[ "$FILE" =~ \.gz$ ]]; then
	gunzip < $FILE | mysql --defaults-extra-file=.my.cnf
else
	mysql --defaults-extra-file=.my.cnf < $FILE
fi
