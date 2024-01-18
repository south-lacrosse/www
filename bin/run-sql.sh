#!/bin/bash

# Run arbitrary SQL using the credentials from wp-config.php

# If there are no args then will run the MySQL client, leaving it open

# If there are args then they must be (any order):
#    either:
#       an SQL file to run, which will be unziped if needed
#       -e "query"
#
#    -y to stop the default confirmation prompt
#    optional flags which are passed to MySQL

# Useful MySQL flags are:
# -t - table display. Default batch mode (not -e flag) is to show tab separated
#      result, not the nice table effect
# -v, -vv, -vvv - verboseness, -vv will display each query and results

function usage() {
	echo "Usage: $(basename "$0") [(sql-file|-e 'sql query') [-y] [-mysql-arg...]]"
	exit 1
}

if [[ $# -gt 0 ]]; then
	has_args=true
	flags=()
	while [[ $# -gt 0 ]]; do
		case $1 in
			-y)
				no_confirm=true
				;;
			-e)
				shift
				if [[ $# -eq 0 || $1 = \-* || -n "$file" || -n "$query" ]]; then usage; fi
				query=$1
				;;
			-*)
				flags+=( $1 )
				;;
			*)
				if [[ -n "$file" || -n "$query" ]]; then usage; fi
				file=$1
				;;
		esac
		shift
	done
	if [[ -z "$query" ]]; then
		if [[ -z "$file" ]]; then usage; fi
		if [[ ! -f "$file" ]] ; then
			echo "File $file does not exist."
			exit 1
		fi
		file=$(realpath "$file")
	fi
fi

cd $(dirname "$0")
source ./db-creds.sh
echo -e "Running SQL on \e[93m$(grep "^define.*'WP_SITEURL" ../wp-config.php | sed "s/.*https:\/\/\([^']*\).*/\1/")\e[0m"

if [ "$has_args" != true ]; then
	mysql --defaults-extra-file=.my.cnf
	exit
fi

if [ "$no_confirm" != true ]; then
	if [[ -n "$query" ]]; then
		read -p "Are you sure you want to run the SQL query \"$query\"? <y/N> " prompt
	else
		read -p "Are you sure you want to run the SQL in $file? <y/N> " prompt
	fi
	if [[ $prompt != "y" && $prompt != "Y" ]] ; then
		echo 'Run SQL terminated by user.'
		exit
	fi
fi

if [[ -n "$query" ]]; then
	mysql --defaults-extra-file=.my.cnf -e "$query" "${flags[@]}"
elif [[ "$file" =~ \.gz$ ]]; then
	gunzip < "$file" | mysql --defaults-extra-file=.my.cnf "${flags[@]}"
else
	mysql --defaults-extra-file=.my.cnf "${flags[@]}" < "$file"
fi
