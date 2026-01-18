<?php
namespace Semla\CLI;
/**
 * Purge a SEMLA cache or WordPress autosaves & revisions.
 *
 * You might want to clear the current and history caches through the SEMLA
 * Admin page as that will also do a minimal purge of Litespeed Cache entries,
 * whereas that option isn't available through the command line so we purge
 * all cache entries.
 *
 * ## EXAMPLE
 *
 *     # Purge menu cache.
 *     $ wp purge menu-cache
 *     Success: Menu cache purged.
 */

use Semla\Cache;
use \WP_CLI;
class Purge_Command {
	/**
	 * Cleanup WordPress autosaves over 7 days old.
	 *
	 * Uses WordPress functions for the actual delete to ensure everything is
	 * tidied up correctly.
	 */
	public function autosaves() {
		global $wpdb;
		$this->delete_revisions(
			"SELECT ID FROM $wpdb->posts
			WHERE post_type = 'revision'
			AND post_name LIKE '%-autosave-v1'
			AND DATE_SUB( NOW(), INTERVAL 7 DAY ) > post_date",
			'autosave' );
	}

	/**
	 * Purge old revisions, but keep at least 5 revisions (2 for private clubs)
	 * and anything under 1 year old.
	 *
	 * Uses WordPress functions for the actual delete to ensure everything is
	 * tidied up correctly.
	 *
	 * We delete most revisions of Private clubs as we make clubs private
	 * instead of trashing them so we have a starting point if they re-enter the
	 * league, however it's not worth keeping a long history.
	 */
	public function revisions() {
		global $wpdb;
		$this->delete_revisions(
			"SELECT ID FROM (
				SELECT r.ID, r.post_date_gmt,
					ROW_NUMBER() OVER (PARTITION BY r.post_parent ORDER BY r.post_date_gmt DESC) AS row_num,
					IF (p.post_status = 'private' AND p.post_type = 'clubs', 2, 5) AS max_row
				FROM $wpdb->posts r, $wpdb->posts p
				WHERE r.post_type = 'revision'
				AND r.post_name NOT LIKE '%-autosave-v1'
				AND p.ID = r.post_parent
				ORDER BY r.post_parent, r.post_date_gmt DESC
			) AS posts
			WHERE row_num > max_row
			AND post_date_gmt < DATE_SUB(NOW(),INTERVAL 1 YEAR)",
			'revision' );
	}

	/**
	 * Delete revisions
	 * @param $sql SQL which returns a list of IDs to delete
	 * @param $name singular name
	 */
	private function delete_revisions($sql, $name) {
		global $wpdb;

		$IDs = $wpdb->get_col($sql);
		Util::db_check();

		$count = count($IDs);
		if ( ! $count ) {
			WP_CLI::warning("No {$name}s found.");
			return;
		}
		WP_CLI::log("Found $count $name(s) to delete");

		$number = $successes = 0;
		foreach ($IDs as $ID) {
			$number++;
			$progress = "$number/$count";
			$post = wp_delete_post_revision($ID);
			if ($post) {
				$successes++;
				WP_CLI::log("$progress $name ID $ID ($post->post_name, $post->post_title) deleted.");
			} else {
				WP_CLI::warning("$progress $name ID $ID delete failed.");
			}
		}

		WP_CLI::success( "$successes of $count $name(s) deleted.");
	}

	/**
	 * Purge current fixtures cache.
	 * @subcommand current-cache
	 */
	public function current_cache() {
		Cache::clear_cache();
		$this->cache_cleared('Current');
	}

	/**
	 * Purge historical fixtures cache.
	 * @subcommand history-cache
	 */
	public function history_cache() {
		Cache::clear_cache('hist');
		$this->cache_cleared('History');
	}

	/**
	 * Purge menu caches.
	 * @subcommand menu-cache
	 */
	public function menu_cache() {
		if (!current_theme_supports('semla')) {
			WP_CLI::error( 'Current theme does not support the menu cache.' );
		}
		do_action('semla_clear_menu_cache');
		$this->cache_cleared('Menu');
	}

	private function cache_cleared($cache) {
		WP_CLI::success( "$cache cache cleared." );
		Util::clear_lscache();
	}
}
