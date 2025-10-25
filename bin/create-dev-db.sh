#!/bin/bash

# Create a SQL file for development, with "www." replaced by "dev." in
# all southlacrosse URLs.

# Additionally all users will have the password set to 'pass', and all
# emails will be user<ID>@southlacrosse.org.uk, so the main admin user
# will be user1@southlacrosse.org.uk

cd $(dirname "$0")
if [[ -z $(grep "^define.*WP_SITEURL.*www\.southlac" ../wp-config.php) ]]; then
	echo -e '\e[91mWARNING:\e[0m This operation is not being run on the production website'
fi

BACKUP_FILE="backups/southlacrosse-dev-db.sql"
echo "Creating development SQL file at $(realpath $BACKUP_FILE).gz"

source ./db-creds.sh
SL_TABLES=$($MYSQL --defaults-extra-file=.my.cnf -Bse "show tables like 'sl%'")
if [[ $? -ne 0 ]]; then
	echo 'failed showing tables'
	exit 1
fi
if [[ $OSTYPE == *win* ]]; then
	SL_TABLES=$(echo $SL_TABLES | tr -d '\r')
fi
PFX=$(grep table_prefix ../wp-config.php | awk -F "'" '{print $2}')
MOST_TABLES="$SL_TABLES ${PFX}commentmeta ${PFX}comments ${PFX}links ${PFX}options ${PFX}postmeta ${PFX}posts ${PFX}term_relationships ${PFX}term_taxonomy ${PFX}termmeta ${PFX}terms"
if ! $MYSQLDUMP --defaults-extra-file=.my.cnf $DBNAME $MOST_TABLES > $BACKUP_FILE ; then
	echo 'db dump failed (1)'
	exit 1
fi
# no session tokens
if ! $MYSQLDUMP --defaults-extra-file=.my.cnf $DBNAME ${PFX}usermeta --no-set-names --where "meta_key!='session_tokens'" >> $BACKUP_FILE
then
	echo 'db dump failed (2)'
	exit 1
fi
if ! $MYSQLDUMP --defaults-extra-file=.my.cnf --no-data $DBNAME ${PFX}users >> $BACKUP_FILE ; then
	echo 'db dump failed (3)'
	exit 1
fi
# mysqldump/mariadb-dump wraps the SQL statements with various set commands. Since we are creating our own
# inserts we need to add that back in.
# You can see what mysqldump does if you dump the table with no drop, create, or data with
# [mysqldump|mariadb-dump] --defaults-extra-file=.my.cnf DBNAME wp_users --where "ID=1" --skip-add-drop-table --no-create-info
cat << EOF >> $BACKUP_FILE
/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
 SET NAMES utf8mb4 ;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

LOCK TABLES \`wp_users\` WRITE;
/*!40000 ALTER TABLE \`wp_users\` DISABLE KEYS */;
INSERT INTO \`wp_users\` VALUES
EOF
# Obfuscate user emails and passwords
if ! $MYSQL --defaults-extra-file=.my.cnf -N < create-dev-user.sql >> $BACKUP_FILE ; then
	echo 'create-dev-user.sql failed'
	exit 1
fi
cat << EOF >> $BACKUP_FILE
;
/*!40000 ALTER TABLE \`wp_users\` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;
EOF
sed 's/www.southlacrosse/dev.southlacrosse/g' $BACKUP_FILE | gzip > $BACKUP_FILE.gz
rm $BACKUP_FILE

echo Completed. Either download the file and send it to whoever requested it, or move it to somewhere so that they can download it and send them a link
