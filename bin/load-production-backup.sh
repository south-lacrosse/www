#!/bin/bash

# Loads a production backup onto another website (e.g. dev or stg).

# This script enables you to load a production version of the website
# into another database, changing the URL to that in wp-config.php

# If run with --latest will copy the latest daily backup in ~/public_html/bin/backups,
# which is an easy way to copy production to staging (assumes both are on the same server)

if [[ $# -ne 1 ]]; then
	echo "Usage: $0 backup.sql.gz|--latest"
	exit 1
fi
if [[ "$1" == '--latest' ]]; then
	FILE=$(ls -t ~/public_html/bin/backups/db*-daily.sql.gz | head -1)
	if [[ -z "$FILE" ]] ; then
		echo 'Cannot find latest production backup.'
		exit 1
	fi
	FILE=$(realpath "$FILE")
else
	if [[ ! -f "$1" ]] ; then
		echo "Backup file $1 does not exist."
		exit 1
	fi
	FILE=$(realpath "$1")
fi

cd $(dirname "$0")
URL=$(grep "^define.*'WP_SITEURL" ../wp-config.php | sed "s/.*https:\/\/\([^']*\).*/\1/")
if [[ -z "$URL" ]]; then
	echo 'Cannot extract WP_SITEURL from wp-config.php'
	exit 1
fi
WWW='www.southlacrosse.org.uk'
if [[ "$URL" == "$WWW" ]]; then
	echo 'Exiting as this IS the prodction website.'
	exit 1
fi
if [[ ${#URL} != ${#WWW} ]]; then
	echo 'Cannot run because the domain name length is different to production.'
	exit 1
fi
echo -e "Current website is \e[93m$URL\e[0m"
read -p "Load database from production backup $FILE? <y/N> " prompt
if [[ $prompt != "y" && $prompt != "Y" ]] ; then
	echo 'Load terminated by user.'
	exit
fi
source ./db-creds.sh
if [[ "$FILE" =~ \.gz$ ]]; then
	gunzip < $FILE | sed "s/$WWW/$URL/g" | mysql --defaults-extra-file=.my.cnf
else
	sed "s/$WWW/$URL/g" $FILE | mysql --defaults-extra-file=.my.cnf
fi
