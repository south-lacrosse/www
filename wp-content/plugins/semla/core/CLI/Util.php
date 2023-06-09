<?php
namespace Semla\CLI;
/**
 * CLI utilities
 */
class Util {
	/**
	 * Clears the entire litespeed cache. Ideally we should use use more
	 * restrictive purges, but most don't work in the CLI.
	 */
	public static function clear_lscache() {
		if (defined( 'LSCWP_V' )) {
			\WP_CLI::log('Purging Litespeed cache');
			\WP_CLI::runcommand( 'litespeed-purge all', ['launch' => false]);
		}
	}
}