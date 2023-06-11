<?php
namespace Semla\CLI;
/**
 * CLI utilities
 */
class Util {

	public static function db_check() {
		global $wpdb;
		if ($wpdb->last_error) {
			\WP_CLI::error('Database error: ' . $wpdb->last_error);
		}
	}

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