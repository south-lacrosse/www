#!/bin/bash

# Backup the WordPress config & .htaccess to the south-lacrosse/wordpress repo if changed

echo 'Backing up wp-config.php and .htaccess (if needed)'
COMMAND='cp -p $WP_DIR/{wp-config.php,.htaccess} .;cp -p $WP_DIR/wp-content/plugins/semla/core/Data_Access/Lacrosse_Play_Config.php .'
source $(dirname "$0")/wp-git-repo.sh
