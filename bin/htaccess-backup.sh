#!/bin/bash

# Backup our root .htaccess file

echo 'Backing up .htaccess (if needed)'
COMMAND='cp ../../../.htaccess .'
source $(dirname "$0")/wp-git-repo.sh
