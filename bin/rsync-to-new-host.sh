#!/bin/bash

# run with: user@server:dir -p port -go
# Args:
#	user@server:dir is the location to copy to e.g. user@newhost:~/wp-location/
#	-p port of SSH on server if not default
#	-go - defaults to dry run, -go actually runs the rsync

# Verify what directories you don't want to copy (i.e. there may be cached file in the
#	 LSCache plugin), and add to rsync-excludes.txt if needed

params=''
ssh=''
dry_run='--dry-run'
while (( "$#" )); do
	case "$1" in
		-go)
			dry_run=''
			shift
			;;
		-p)
			if [[ -n "$2" ]] && [[ ${2:0:1} != "-" ]]; then
				if ! [[ "$2" -eq "$2" ]] 2>/dev/null ; then
					echo "Error: port $2 must be a number"
					exit 1
				fi
				ssh="-e '/bin/ssh -p $2'"
				shift 2
			else
				echo "Error: Argument for $1 is missing" >&2
				exit 1
			fi
			;;
		-*|--*=) # unsupported flags
			echo "Error: Unsupported flag $1" >&2
			exit 1
			;;
		*) # preserve positional arguments
			params="$params $1"
			shift
			;;
	esac
done
eval set -- "$params"
if [[ $# -eq 0 ]] || [[ $# -gt 1 ]]; then
	echo Usage: location [-p port] [-go]
	exit 1
fi

cd $(dirname "$0")

# rsync:
# r=recursive, t=preserve modification times, z=compress,
#	--delete if you want to delete files not on client

[ -f "backups/rsync.log" ] && rm backups/rsync.log
eval "rsync $dry_run -rtz $ssh --info=progress2 --log-file=backups/rsync.log --stats --exclude-from=rsync-excludes.txt ../ $1"
