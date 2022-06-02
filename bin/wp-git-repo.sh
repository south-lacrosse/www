#!/bin/bash

# Go to the bin/backups/wordpress dir and make sure it is setup for Git
# repo git@github.com:south-lacrosse/wordpress.git

# Then run $COMMAND, and update the repository if needed

# Must only be called from other scripts

[[ -z $COMMAND ]] && { echo 'Error: No command specified'; exit 1; }

cd $(dirname "${BASH_SOURCE[0]}")/backups || exit 1
if [[ -z $(grep "^define.*WP_SITEURL.*www\.southlac" ../../wp-config.php) ]]; then
	echo 'Error: This script must only be run on the production website'
	exit 1
fi

if [[ ! -d 'wordpress' ]]; then
	echo 'No wordpress repo...cloning'
	git clone git@github.com:south-lacrosse/wordpress.git || exit 1
fi

cd wordpress || exit 1
if [[ -z $(git remote -v|grep git@github.com:south-lacrosse/wordpress.git) ]]; then
    echo 'Error: backups/wordpress dir is not set up for the correct remote repository'
    exit 1
fi

eval "$COMMAND"

if [[ $(git status --porcelain) ]]; then
	echo 'Local repository changed, commiting to git and pushing to remote'
	git add . || exit 1
	git commit -m "Automated commit on $(date +%F-%H%M%S)" || exit 1
	git push || exit 1
	echo 'Repository successfully updated'
else
    echo 'Nothing changed'
fi
