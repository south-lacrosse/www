# Called by other scripts to get DB credentials from the WordPress config.

# We store as much info as possible in a MySQL options file, that way sensitive
# args are not passed in the command line which can be insecure on multi-user
# systems

if command -v mariadb >/dev/null 2>&1; then
	MYSQL=mariadb
	MYSQLDUMP=mariadb-dump
else
	MYSQL=mysql
	MYSQLDUMP=mysqldump
fi

BIN=$(cd "$(dirname "${BASH_SOURCE[0]}")" &> /dev/null && pwd)
WP_DIR="$(dirname "$BIN")"
DBNAME=$(grep DB_NAME $WP_DIR/wp-config.php | awk -F "'" '{print $4}')
# recreate .my.cnf if the WordPress config has been updated
if [[ $WP_DIR/wp-config.php -nt $BIN/.my.cnf ]]; then
	DBUSER=$(grep DB_USER $WP_DIR/wp-config.php | awk -F "'" '{print $4}')
	DBHOST=$(grep DB_HOST $WP_DIR/wp-config.php | awk -F "'" '{print $4}')
	DBPASS=$(grep DB_PASS $WP_DIR/wp-config.php | awk -F "'" '{print $4}')
	STR="[client]
user = $DBUSER
password = $DBPASS
host = $DBHOST
[mysql]
database = $DBNAME
[mysqldump]
no-tablespaces
skip-comments
skip-disable-keys
single-transaction"
	# touch and chmod BEFORE we write sensitive information to the file
	touch $BIN/.my.cnf
	chmod 0600 $BIN/.my.cnf
	echo "$STR" > $BIN/.my.cnf
fi
