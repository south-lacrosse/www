<?php
/**
 * Basic script to use FTPS to copy backups to and from a remote directory.
 *
 * Command line options are:
 *
 * backup  - backup to remote. Only checks the last 6 months of local files.
 *   Weekly files on the remote server which aren't on the local one will be
 *   deleted. Will re-copy any files where the size doesn't match.
 *
 *   Additional argument for: weekly     - keep remote and local weekly backups
 *     in sync, deleting files on the remote that aren't on local monthly    -
 *     copy all monthly files to the remote both       - both weekly and monthly
 *     (default) history    - same as monthly, but for history backups list    -
 *     list all backups on the remote server recover - recover the specified
 *     file
 *
 * Config must exists in file off-site.secret.php, and define the following
 * variables: HOSTNAME, USER, PASSWORD, LOCAL_DIR, REMOTE_DIR, and optionally
 * DEBUG (default false) and MAX_TRIES (default 3).
 *
 * MAX_TRIES is only relevant on backups, and will retry each operation if the
 * FTPS connection is lost or anything else fails. It wasn't added to the list
 * and recover options as they are a single operation, and the program can
 * easily be re-run.
 */

// 1st arg is filename
if ($argc < 2 || $argc > 3) usage();
switch ($argv[1]) {
	case 'list':
		if ($argc !== 2) usage();
		break;
	case 'recover':
		if ($argc !== 3) usage();
		break;
	case 'backup':
		if ($argc === 2) {
			$argv[2] = 'both';
		} elseif ($argc !== 3) {
			usage();
		}
		switch ($argv[2]) {
			case 'both':
				$pattern = '/^db.*-(weekly|monthly)\.sql\.gz$/';
				break;
			case 'weekly':
				$pattern = '/^db.*-weekly\.sql\.gz$/';
				break;
			case 'monthly':
				$pattern = '/^db.*-monthly\.sql\.gz$/';
				break;
			case 'history':
				$pattern = '/^slh.*\.sql\.gz$/';
				break;
			default:
				usage();
		}
		break;
	default:
		usage();
}

if (!file_exists(__DIR__ . '/off-site.secret.php')) {
	die('Config file off-site.secret.php does not exist. Check the head of this script for details.');
}
require __DIR__ . '/off-site.secret.php';
! defined('DEBUG') && define('DEBUG', false);
! DEFINED('MAX_TRIES') && define('MAX_TRIES', 3);

connect();
// dynamically call the correct run_ function below
call_user_func('run_'.$argv[1]);
ftp_close($ftp);
exit(0);

//------------------------------------------------------------------------------

function connect() {
	global $ftp;
	if (isset($ftp)) @ftp_close($ftp); // close old connection if we are re-connecting
	$ftp = ftp_ssl_connect(HOSTNAME) or die('Failed to connect to ' . HOSTNAME);
	ftp_login($ftp, USER, PASSWORD) or die("Can't login");
	ftp_pasv($ftp, true) or die("Can't set passive mode");
	@ftp_chdir($ftp, REMOTE_DIR) or die('Remote dir does not exist');
}

function run_backup() {
	global $argv, $ftp, $pattern;

	echo "Off site backup ($argv[2]) running\n";
	// get all remote files under a year old matching our pattern
	$date = new DateTimeImmutable('-1 year');
	$year_ago = $date->format('YmdHis');

	$list = do_with_retries(function() {
		global $ftp;
		return ftp_mlsd($ftp, '.');
	}, "Can't list remote server");

	$remote_files = [];
	foreach ($list as $file) {
		if ($file['type'] === 'file' && $file['modify'] > $year_ago
		&& preg_match($pattern, $file['name'])) {
			$remote_files[$file['name']] = $file['size'];
		}
	}
	if (DEBUG) {
		echo "Remote files:\n";
		print_r($remote_files);
	}

	// See which local files to backup
	$six_months_ago = strtotime("-6 months");
	foreach (new DirectoryIterator(LOCAL_DIR) as $file_info) {
		$filename = $file_info->getFilename();
		if (!$file_info->isFile()
		|| $file_info->getMTime() < $six_months_ago) {
			if (DEBUG) echo "$filename is not a file, or too old\n";
			continue;
		}
		if (!preg_match($pattern, $filename)) {
			if (DEBUG) echo "$filename does not match pattern\n";
			continue;
		}
		if (isset($remote_files[$filename])) {
			if ($remote_files[$filename] != $file_info->getSize()) {
				echo "Replacing $filename as sizes don't match - local is "
					. $file_info->getSize() . ", remote $remote_files[$filename]\n";
				upload_file($file_info->getRealPath(), $file_info->getMTime(), $filename);
			} elseif (DEBUG) {
				echo "Skipping $filename as remote and local match\n";
			}
			unset($remote_files[$filename]);
		} else {
			echo "Copying $filename\n";
			upload_file($file_info->getRealPath(), $file_info->getMTime(), $filename);
		}
	}
	if (DEBUG) {
		echo "Files on remote not in local after copying:\n";
		print_r($remote_files);
	}
	// Prune any weekly remotes if needed
	if ($argv[2] === 'both' || $argv[2] == 'weekly') {
		foreach ($remote_files as $filename => $size) {
			if (preg_match('/^db.*-weekly\.sql\.gz$/', $filename)) {
				if (!ftp_delete($ftp, $filename)) {
					echo "Failed to delete remote file $filename\n";
					continue;
				}
				echo "Deleted remote $filename as not on local server\n";
			}
		}
	}
}

function upload_file($local, $mtime, $remote) {
	global $ftp;
	do_with_retries(function() use($local, $remote) {
		global $ftp;
		return ftp_put($ftp, $remote, $local, FTP_BINARY);
	}, "Error uploading $local to $remote");
	// ftp_put sets the modified time to now, so make sure we set it to match
	// the local file time
	// Some servers may not support MFMT and use the non-standard
	// `MDTM filename timestamp`, Send FEAT request to find out.
	do_with_retries(function() use($mtime, $remote) {
		global $ftp;
		$gmtime = gmdate('YmdHis', $mtime);
		$ret = ftp_raw($ftp, "MFMT $gmtime $remote");
		if (!is_array($ret) || !str_starts_with($ret[0], '213 ')) {
			echo "Invalid MFMT response for $remote, modified time not set:\n";
			if ($ret) print_r($ret);
			return false;
		}
		return true;
	}, "Error setting modified time on $remote");
}

function run_list() {
	global $ftp;

	$list = ftp_rawlist ($ftp, '.');
	if (!$list) die('Failed to list remote directory');
	echo implode("\n",$list);
}

function run_recover() {
	global $argv, $ftp;

	$file = $argv[2];
	$local_file = LOCAL_DIR . "/$file";
	if (!ftp_get($ftp, $local_file, $file, FTP_BINARY)) {
		die("Failed to fetch $file");
	}
	// ftp_get sets the modified time to now, so make sure we set it to match
	// the remote
	$mtime = ftp_mdtm($ftp, $file);
	if ($mtime === -1) die("Failed to get modified time for $file");
	touch($local_file, $mtime);
	echo "Successfully written to $local_file\n";
}

function do_with_retries($function, $failure_message) {
	$tries = 1;
	while (! $ret = $function()) {
		if($tries < MAX_TRIES) {
			echo "\n$failure_message. Tried $tries of " . MAX_TRIES .". Reconnecting...\n";
			sleep(1);
			connect();
		} else {
			echo "\n$failure_message. Tried $tries of " . MAX_TRIES . ".\n";
			break;
		}
		$tries++;
	}
	return $ret;
}

function usage() {
	global $argv;
	echo "$argv[0] options:\n"
		. "list  - list remote directory\n"
		. "recover filename    - recover named file\n"
		. "backup [weekly|monthly|both|history]    - backup to remote";
	die();
}
