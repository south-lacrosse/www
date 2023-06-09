<?php
namespace Semla\CLI;
/**
 * Purge a SEMLA cache.
 *
 * You might want to clear the current and history caches through the SEMLA
 * Admin page as that will also do a minimal purge of Litespeed Cache entries,
 * whereas that option isn't available through the command line so we purge
 * all cache entries.
 *
 * ## EXAMPLE
 *
 *     # Purge menu cache.
 *     $ wp purge menu
 *     Success: Menu cache purged.
 */

use Semla\Cache;
use \WP_CLI;
class Purge_Command {
	/**
	 * Purge current fixtures cache.
	 */
	public function current() {
		Cache::clear_cache();
		$this->done('Current');
	}

	/**
	 * Purge historical fixtures cache.
	 */
	public function history() {
		Cache::clear_cache('hist');
		$this->done('History');
	}

	/**
	 * Purge current fixtures cache.
	 */
	public function menu() {
		if (!current_theme_supports('semla')) {
			WP_CLI::error( 'Current theme does not support the menu cache.' );
		}
		do_action('semla_clear_menu_cache');
		$this->done('Menu');
	}

	private function done($cache) {
		WP_CLI::success( "$cache cache cleared." );
		Util::clear_lscache();
	}
}
