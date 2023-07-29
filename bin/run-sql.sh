#!/bin/bash

# Run arbitrary SQL using the credentials from wp-config.php
# If there are no args then will run the mysql client, leaving it open
# If there are args then:
#    arg 1 is an SQL file to run, which will be unziped if needed
#    the user will be prompted to confirm unless 2nd arg is "-y"

if [[ $# -gt 2 || ( $# -eq 2 && "$2" != '-y' ) ]]; then
	echo "Usage: $0 [sql-file [-y]]"
	exit 1
fi
if [[ $# -gt 0 ]]; then
	if [[ ! -f "$1" ]] ; then
		echo "File $1 does not exist."
		exit 1
	fi
	file=$(realpath "$1")
fi

cd $(dirname "$0")
source ./db-creds.sh
echo -e "Running SQL on \e[93m$(grep "^define.*'WP_SITEURL" ../wp-config.php | sed "s/.*https:\/\/\([^']*\).*/\1/")\e[0m"

if [[ $# -eq 0 ]]; then
	mysql --defaults-extra-file=.my.cnf
	exit
fi

if [[ "$2" != '-y' ]]; then
	read -p "Are you sure you want to run the SQL in $file? <y/N> " prompt
	if [[ $prompt != "y" && $prompt != "Y" ]] ; then
		echo 'Run SQL terminated by user.'
		exit
	fi
fi

if [[ "$file" =~ \.gz$ ]]; then
	gunzip < "$file" | mysql --defaults-extra-file=.my.cnf
else
	mysql --defaults-extra-file=.my.cnf < "$file"
fi
