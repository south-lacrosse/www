<?php
namespace Semla\CLI;
/**
 * Purge a SEMLA cache or WordPress autosaves.
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
	 * Cleanup WordPress autosaves over 7 days old.
	 */
	public function autosaves() {
		global $wpdb;

		$old_posts = $wpdb->get_col(
			"SELECT ID FROM $wpdb->posts
			WHERE post_type = 'revision'
			AND post_name REGEXP '^\\\\d+-autosave-v1$'
			AND DATE_SUB( NOW(), INTERVAL 7 DAY ) > post_date" );
		Util::db_check();

		$count = count($old_posts);
		if ( ! $count ) {
			WP_CLI::warning( 'No autosaves found.' );
			return;
		}

		WP_CLI::log("Found $count autosave(s) to delete");

		$number = $successes = 0;
		foreach ( (array) $old_posts as $ID ) {
			$number++;
			$progress = "$number/$count";
			$post = wp_delete_post_revision( $ID );
			if ($post) {
				$successes++;
				WP_CLI::log("$progress Autosave ID $ID ($post->post_name, $post->post_title) deleted.");
			} else {
				WP_CLI::warning("$progress Autosave ID $ID delete failed.");
			}
		}

		WP_CLI::success( "$successes of $count autosave(s) deleted.");
	}

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
	 * Purge menu caches.
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
