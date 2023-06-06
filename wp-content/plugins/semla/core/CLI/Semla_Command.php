<?php
namespace Semla\CLI;
/**
 * Manage SEMLA fixtures/tables/flags, history pages, caches.
 *
 * ## EXAMPLES
 *
 *     # Update fixtures.
 *     $ wp semla fixtures update
 *
 *     # Purge menu cache.
 *     $ wp semla purge menu
 *     Success: menu cache purged.
 */

use Semla\Cache;
use Semla\Data_Access\Fixtures_Sheet_Gateway;
use Semla\Data_Access\History_Gateway;
use \WP_CLI;
class Semla_Command {
	/**
	 * Manage the SEMLA fixtures.
	 *
	 * Note it is better to use the SEMLA WordPress Admin menu as that
	 * purges fewer cached pages than the CLI version because of the way
	 * LiteSpeed cache works.
	 *
	 * ## OPTIONS
	 *
	 * <update|update-all|revert>
	 * : Only run update-all at the beginning of the season, or if you
	 * have changed any of the divisions or teams.
	 * Only run revert as a last resort to quickly revert an update. It
	 * won't work if the teams or divisions were changed.
	 */
	public function fixtures($args) {
		switch ($args[0]) {
			case 'update':
			case 'update-all':
				$result = (new Fixtures_Sheet_Gateway())->update($args[0]);
				if (is_wp_error($result)) {
					WP_CLI::warning('Update failed (no data has been changed)');
					$this->handle_wp_error($result);
				}
				WP_CLI::success('Fixtures updated');
				foreach ($result as $message) {
					WP_CLI::log($message);
				}
				break;
			case 'revert':
				$result = Fixtures_Sheet_Gateway::revert();
				if (is_wp_error($result)) {
					$this->handle_wp_error($result);
				}
				WP_CLI::success($result);
				break;
			default:
				WP_CLI::error( 'Unknown option.' );
		}
		if (defined( 'LSCWP_V' )) {
			WP_CLI::log('Info: fixtures updates are better run from the SEMLA Admin menu as that purges fewer cached pages than the CLI version');
			$this->clear_lscache();
		}
	}

	/**
	 * Purge a SEMLA cache.
	 *
	 * You might want to clear the current and history caches through the SEMLA
	 * Admin page as that will also do a minimal purge of Litespeed Cache entries,
	 * whereas that option isn't available through the command line so we purge
	 * all cache entries.
	 *
	 * ## OPTIONS
	 *
	 * <current|history|menu>
	 * : The cache name to clear.
	 */
	public function purge($args) {
		switch ($args[0]) {
			case 'current':
				Cache::clear_cache();
				break;
			case 'history':
				Cache::clear_cache('hist');
				break;
			case 'menu':
				if (!current_theme_supports('semla')) {
					WP_CLI::error( 'Current theme does not support the menu cache.' );
				}
				do_action('semla_clear_menu_cache');
				break;
			default:
				WP_CLI::error( 'Unknown cache.' );
		}
		WP_CLI::success( $args[0] . ' cache cleared.' );
		$this->clear_lscache();
	}

	/**
	 * History pages update/stats.
	 *
	 * If for any reason you need to delete all the history pages you can
	 * do this with:
	 *
	 *    $ wp post delete $(wp post list --post_type='history' --format=ids) --force
	 *
	 * ## OPTIONS
	 *
	 * <update-pages|update-winners|stats>
	 * : update-winners will just update non-league/flags winners pages, useful
	 * if you have updated competition winners like Sixes or Varsity, but not run
	 * the full end of season update.
	 * The stats option will give you statistics about how many rows there are
	 * on all the history tables. Useful to run before and after the end of season
	 * processing.
	 *
	 * ## EXAMPLES
	 *
	 *     # Update history pages.
	 *     $ wp semla history update-pages
	 */
	public function history($args) {
		if ($args[0] === 'update-pages') {
			History_Pages::update_pages();
			$this->clear_lscache();
			// do_action( 'litespeed_purge_posttype', 'history' );
			return;

		}
		if ($args[0] === 'update-winners') {
			History_Pages::update_winners();
			$this->clear_lscache();
			return;

		}
		if ($args[0] !== 'stats')
			WP_CLI::error( 'Unknown option.' );
		$num_posts = wp_count_posts( 'history' );
		if( ! $num_posts )
			WP_CLI::error('Cannot count history pages');
		WP_CLI::log("There are $num_posts->publish history WordPress pages");
		$stats = History_Gateway::get_stats();
		global $wpdb;
		if ($wpdb->last_error) $this->handle_db_error('Failed to get history stats');
		WP_CLI::log('History database table row counts:');
		foreach ($stats as $stat) {
			WP_CLI::log("$stat->table_name: $stat->row_count");
		}
	}

	/**
	 * Refresh media file sizes meta data.
	 *
	 * If you have optimized images outside of WordPress, without changing their
	 * dimensions, this this command will update the image metadata for the new
	 * file sizes.
	 *
	 * @subcommand media-sizes
	 */
	public function media_sizes($args) {
		require __DIR__ . '/media-sizes.php';
	}

	/**
	 * Clears the entire litespeed cache. Ideally we should use use more restrictive
	 * purges, but most don't work in the CLI.
	 */
	private function clear_lscache() {
		if (defined( 'LSCWP_V' )) {
			WP_CLI::log('Purging Litespeed cache');
			WP_CLI::runcommand( 'litespeed-purge all', ['launch' => false]);
		}
	}

	private function handle_wp_error($error) {
		foreach ($error->get_error_messages() as $message) {
			WP_CLI::error($message, false);
		}
		WP_CLI::halt(1);
	}
	private function handle_db_error($errmsg) {
		global $wpdb;
		WP_CLI::error("$errmsg. SQL error: $wpdb->last_error");
	}
}
