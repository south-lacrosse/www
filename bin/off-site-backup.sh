#!/bin/bash

# Copy backups to off-site storage.

# Regular backups will be of the form db-{datetime}-{weekly|monthly|daily}.sql.gz

# Options are:
# weekly - sync all weekly backups with remote, that means older remote backups
#          that no longer exist on this server will be deleted
# monthly - copy all monthly backups to remote. This won't delete from the
#           backup server.
# both (default) - duh

# We do it this way rather than copying the last backup just in case anything
# fails, that way when this script next runs it will also redo any failed
# transfers.

# To backup or recover individual files just use rclone, remote is backup:

if [[ $# -gt 1 ]]; then
	echo "Usage: $0 [both|monthly|weekly]"
	exit 1
fi
if [[ $# -eq 0 ]]; then
	set -- 'both'
fi
case "$1" in
	both)
		WEEKLY=true;MONTHLY=true
		;;
	weekly)
		WEEKLY=true;MONTHLY=false
		;;
	monthly)
		WEEKLY=false;MONTHLY=true
		;;
	*)
		echo "Usage: $0 [both|monthly|weekly]"
		exit 1
		;;
esac

cd $(dirname "$0")

# Only save production backups
if [[ -z $(grep "^define.*WP_SITEURL.*www\.southlac" ../wp-config.php) ]]; then
	echo 'Error: This script must only be run on the production website'
	exit 1
fi
if [[ ! -d backups ]]; then
	echo 'Cannot find backups directory!'
	exit 1
fi

# rclone is in $HOME/bin, but that isn't on the $PATH in cron jobs, so try to find
# it here if necessary
RCLONE=rclone
if ! [[ -x "$(command -v rclone)" ]]; then
	if ! [[ -x "$(command -v $HOME/bin/rclone)" ]]; then
		echo 'Error: cannot fine rclone.'
		exit 1
	fi
	RCLONE=$HOME/bin/rclone
fi

# -vv --dry-run to test
if $WEEKLY; then
	echo 'Syncing weekly backup files to backup server'
	$RCLONE sync backups/ backup:/ --include=db-*weekly.sql.gz
fi
if $MONTHLY; then
	echo 'Copying monthly backup files to backup server'
	# max-age 6Months so rclone doesn't check every file
	$RCLONE copy --max-age 6M backups/ backup:/ --include=db-*monthly.sql.gz
fi
