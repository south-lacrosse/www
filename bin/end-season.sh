#!/bin/bash

cd $(dirname "$0")
if [[ "" != $(grep "^define.*WP_SITEURL.*www\.southlac" ../wp-config.php) ]]; then
	echo -e '\e[91mERROR:\e[0m This operation must not be run on the production website'
	echo 'See https://github.com/south-lacrosse/www-dev/blob/main/docs/end-season.md'
	exit 1
fi
source ./db-creds.sh
NO_RESULT=$(mysql --defaults-extra-file=.my.cnf -Nse 'SELECT COUNT(*) FROM slc_fixture WHERE result = ""')
if [[ $? -ne 0 ]]; then
	echo 'Database access failed - check output'
	exit 1
fi
NO_RESULT="${NO_RESULT/$'\r'/}"
if [[ $NO_RESULT != "0" ]] ; then
	echo -e "\e[91mThere are $NO_RESULT fixtures without results.\e[0m"
	echo 'All fixtures must have results. You can mark fixtures as V-V for void, or C-C for cancelled.'
	exit 1
fi
echo -e '\e[93mBefore continuing make sure you have read the instuctions at'
echo -e 'https://github.com/south-lacrosse/www-dev/blob/main/docs/end-season.md\e[0m'
echo
read -p "Run end of season processing? <y/N> " prompt
if [[ $prompt != "y" && $prompt != "Y" ]] ; then
	echo 'End of season processing terminated'
	exit 0
fi
echo ---------- Stats before history update -----------------
wp history stats
echo --------------------------------------------------------
# Run with --verbose to see statements
mysql --defaults-extra-file=.my.cnf < end-season.sql
if [[ $? -ne 0 ]]; then
	echo 'Database update failed - check output'
	exit 1
fi
wp history update-pages
echo ---------- Stats after history update ------------------
wp history stats
echo --------------------------------------------------------
