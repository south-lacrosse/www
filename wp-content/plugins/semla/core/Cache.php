<?php
namespace Semla;
/**
 * Handle cacheing of PHP data or strings
 */
class Cache {
	/** Create a filename for the cache dir */
	public static function cache_filename($file, $cache_name) {
		// sanitize file name here to be 100% sure
		return __DIR__ . "/.cache$cache_name/" . str_replace([' ','/','\\',':'] ,'-', $file);
	}

	/** Return the raw cached resource, or null if it doesn't exist or has expired */
	public static function get_cache($resource, $cache_name = '', $expires = 'never') {
		$file = self::cache_filename($resource, $cache_name);
		if ($expires !== 'never') {
			if ($expires !== 'day') die('doing it wrong');
			$filemtime = @filemtime($file);
			if (!$filemtime) return null;
			$datetime = new \DateTime('now', new \DateTimeZone('Europe/London'));
			$now = $datetime->format('Ymd');
			$datetime->setTimestamp($filemtime);
			$filedate = $datetime->format('Ymd');
			if ($filedate !== $now) {
				return null;
			}
		}
		return @file_get_contents($file);
	}

	/** Return the cached resource as a PHP value, or null if it doesn't exist */
	public static function get_cache_value($resource, $cache_name = '') {
		$file = self::cache_filename($resource, $cache_name);
		return unserialize(@file_get_contents($file));
	}

	/** Cache data to disk */
	public static function write_cache($resource, $data, $cache_name = '') {
		self::write_cache_file(self::cache_filename($resource, $cache_name), $data);
	}
	/** Cache serialized version of PHP value to disk */
	public static function write_cache_value($resource, $data, $cache_name = '') {
		self::write_cache_file(self::cache_filename($resource, $cache_name), serialize($data));
	}

	public static function clear_cache($cache_name = '', $glob = '*') {
		$files = glob( self::cache_filename($glob, $cache_name));
		foreach( $files as $file ){
			@unlink($file);	  
		}
	}

	public static function write_cache_file($file, $data) {
		$cacheDir = dirname($file);
		if (!is_dir($cacheDir)) {
			mkdir($cacheDir, 0705, true);
		}
	
		// write to a temp file so another process doesn't try to read
		// a half written file
		$tmpf = tempnam('/tmp','SLC');
		$fp = fopen($tmpf,'w');
		fwrite($fp,$data);
		fclose($fp);
		chmod($tmpf, 0604); // temp files default to 0600
		rename($tmpf, $file);
	}
}
