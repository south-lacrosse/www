#!/bin/bash

# Loads a production backup onto another website (e.g. dev or stg).

# This script enables you to load a production version of the website
# into another database, changing the URL to that in wp-config.php

# If run with --latest will copy the latest daily in ~/public_html/bin/backups,
# which is an easy way to copy production to staging (assumes both are on the same server)

if [[ $# -ne 1 ]]; then
	echo "Usage: $0 backup.sql.gz|--latest"
	exit 1
fi
if [[ "$1" == '--latest' ]]; then
	file=$(ls -t ~/public_html/bin/backups/db-*.sql.gz | head -1)
	if [[ -z "$file" ]] ; then
		echo 'Cannot find latest production backup.'
		exit 1
	fi
	file=$(realpath "$file")
else
	if [[ ! -f "$1" ]] ; then
		echo "Backup file $1 does not exist."
		exit 1
	fi
	file=$(realpath "$1")
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
read -p "Load database from production backup $file? <y/N> " prompt
if [[ $prompt != "y" && $prompt != "Y" ]] ; then
	echo 'Load terminated by user.'
	exit
fi
source ./db-creds.sh
if [[ "$file" =~ \.gz$ ]]; then
	if [[ "$URL" == 'dev.southlacrosse.org.uk' ]]; then
		# Later versions of MariaDB that run on the server have a breaking change
		# to enable sandbox mode.
		# In dev we probably use Local, which has an older version of MariaDB or MySQL,
		# so we remove that line. Note it only checks the 1st line.
		# Note: MariaDB say they will modify this change to be non-breaking at some point
		gunzip < "$file" | sed "1{/999999.*sandbox/d}" | sed "s/$WWW/$URL/g" | mysql --defaults-extra-file=.my.cnf
	else
		gunzip < "$file" | sed "s/$WWW/$URL/g" | mysql --defaults-extra-file=.my.cnf
	fi
else
	sed "s/$WWW/$URL/g" "$file" | mysql --defaults-extra-file=.my.cnf
fi
# need to purge menu cache in case it's changed in the DB
wp purge menu
