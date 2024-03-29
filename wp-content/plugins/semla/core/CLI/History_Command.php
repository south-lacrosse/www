<?php
namespace Semla\CLI;
/**
 * History pages update and statistics.
 *
 * If for any reason you need to delete all the history pages you can do this
 * with:
 *
 *   $ wp post delete $(wp post list --post_type='history' --format=ids) --force
 *
 * ## EXAMPLE
 *
 *     # Update history pages.
 *     $ wp history update
 */

use Semla\Cache;
use Semla\Data_Access\Competition_Gateway;
use Semla\Data_Access\Competition_Group_Gateway;
use Semla\Data_Access\Cup_Draw_Gateway;
use Semla\Data_Access\Fixtures_Gateway;
use Semla\Data_Access\Fixtures_Results_Gateway;
use Semla\Data_Access\History_Gateway;
use Semla\Data_Access\Table_Gateway;
use Semla\Data_Access\Winner_Gateway;
use WP_CLI;
use function WP_CLI\Utils\make_progress_bar;

class History_Command {
	private $updated = 0;
	private $inserted = 0;

	/**
	 * Create all history pages from the database.
	 *
	 * Creates or updates pages for all tables, flags draws, results,
	 * competition winners. Will overwrite page if already exists.
	 *
	 * This should only be need to run as part of the end of season process, or
	 * if the format of any of the HTML has changed, e.g. if
	 * Cup_Draw_Renderer.php changes.
	 */
	public function update() {
		WP_CLI::log('Creating WordPress history pages from the history database');
		$this->update_tables();
		$this->update_results();
		$this->update_cup_draws();
		$this->update_winners();
		$this->update_group_winners();
		$url = get_option( 'siteurl' );
		$this->insert_post([
			'post_title'    => 'Plate Finals',
			'post_name'     => 'plate-finals',
			'post_content'  => '<ul class="medium-spaced">'
				. '<li><a href="' . $url . '/history/plate-intermediate">Intermediate Plate</a></li>'
				. '<li><a href="' . $url . '/history/plate-minor">Minor Plate</a></li>'
				. '<li><a href="' . $url . '/history/plate-intermediate-east">Intermediate East Plate</a></li>'
				. '<li><a href="' . $url . '/history/plate-intermediate-west">Intermediate West Plate</a></li>'
				. '<li><a href="' . $url . '/history/plate-minor-east">Minor East Plate</a></li>'
				. '<li><a href="' . $url . '/history/plate-minor-west">Minor West Plate</a></li>'
				. '</ul>'
			]);
		Cache::clear_cache('hist');
		WP_CLI::Success('History pages updated: ' . $this->inserted . ' pages inserted, '
			. $this->updated  . ' pages updated');
		Util::clear_lscache();
		// do_action( 'litespeed_purge_posttype', 'history' );
	}

	/**
	 * Create history pages for competition winners (not including league, flags).
	 *
	 * Useful if you have updated competition winners like Sixes or Varsity, but
	 * not run the full end of season update.
	 */
	public function winners() {
		WP_CLI::log('Creating WordPress history winners pages from the history database');
		$this->update_winners();
		WP_CLI::Success('History pages updated: ' . $this->inserted . ' pages inserted, '
			. $this->updated  . ' pages updated');
		Util::clear_lscache();
	}

	/**
	 * Display statistics about how many rows there are on all the history
	 * tables.
	 *
	 * Useful to run before and after the end of season processing.
	 */
	public function stats() {
		$num_posts = wp_count_posts( 'history' );
		if( ! $num_posts )
			WP_CLI::error('Cannot count history pages');
		WP_CLI::log("There are $num_posts->publish history WordPress pages");
		$stats = History_Gateway::get_stats();
		Util::db_check();
		WP_CLI::log('History database table row counts:');
		foreach ($stats as $stat) {
			WP_CLI::log("$stat->table_name: $stat->row_count");
		}
	}

	private function update_tables() {
		$leagues = Competition_Group_Gateway::get_leagues(true);
		Util::db_check();
		foreach ($leagues as $league) {
			$name = $league->id === '1' ? "League" : "$league->name League";
			$years = Table_Gateway::get_tables_years($league->id);
			Util::db_check();
			$progress = make_progress_bar( "$name Tables", count($years) );
			foreach ( $years as $year ) {
				$year = intval($year);
				$tables_page = "$league->page-$year";
				$meta = [[$league->history_page,"$name Champions"]];
				$grid_page = $year > 2002 ? $league->grid_page : '';
				$data = Table_Gateway::get_tables($year,
											$league->id,$league->page,$grid_page);
				Util::db_check();
				$this->insert_post([
					'post_title'    => "$year $name Tables",
					'post_name'		=> $tables_page,
					'post_content'	=> $data
				], $meta);
				$data = Fixtures_Gateway::get_grid($year,$league->id);
				Util::db_check();
				if ($year > 2002) {
					$this->insert_post([
						'post_title'    => "$year $name Results Grid",
						'post_name'		=> str_replace('fixtures','results',$league->grid_page)
											. "-$year",
						'post_content'	=> '<p class="no-print"><a href="' . $tables_page
							. '">League tables</a></p>'. "\n"
							. $data
					], $meta);
				}
				$progress->tick();
			}
			$progress->finish();
		}
	}

	private function update_results() {
		$gateway = new Fixtures_Results_Gateway();
		$years = $gateway->get_results_years();
		Util::db_check();
		$html = '';
		$progress = make_progress_bar( 'Results', count($years) + 1 );
		foreach ( $years as $year ) {
			$this->insert_post([
				'post_title'    => "$year Results",
				'post_name'		=> "results-$year",
			], [['results','Results']]);
			if ($html) $html .= ', ';
			$html .= "<a href=\"/history/results-$year\">$year</a>";
			$progress->tick();
		}
		$this->insert_post([
			'post_title'    => "Results",
			'post_name'		=> "results",
			'post_content'  => "<p>Full results are available for the following years:</p>\n<p>"
				. $html . "</p>"
		]);
		$progress->tick();
		$progress->finish();

	}

	private function update_cup_draws() {
		$years = Cup_Draw_Gateway::get_cup_years();
		Util::db_check();
		$progress = make_progress_bar( 'Cup draws', count($years) );
		foreach ( $years as $year ) {
			if ($year->breadcrumbs) {
				$name = $year->history_group_page ? "$year->name Winners" : $year->name;
				$breadcrumbs = [[$year->history_page, $name]];
			} else {
				$breadcrumbs = false;
			}
			$data = Cup_Draw_Gateway::get_draws($year->year,$year->group_id,'',$year->history_page);
			Util::db_check();
			$this->insert_post([
				'post_title'    => "$year->year $year->name",
				'post_name'		=> "$year->history_page-$year->year",
				'post_content'	=> $data
			], $breadcrumbs, $year->max_rounds);
			$data = Cup_Draw_Gateway::get_draws($year->year,$year->group_id,
				'rounds',$year->history_page);
			Util::db_check();
			$this->insert_post([
				'post_title'    => "$year->year $year->name Rounds",
				'post_name'		=> "$year->history_page-$year->year-rounds",
				'post_content'	=> $data
			], $breadcrumbs,  $year->max_rounds);
			$progress->tick();
		}
		$progress->finish();
	}

	private function update_winners() {
		$competitions = Competition_Gateway::get_history_competitions();
		Util::db_check();
		$progress = make_progress_bar( 'Competition winners', count($competitions) );
		foreach ( $competitions as $competition ) {
			$breadcrumbs = false;
			if ($competition->history_group_page) {
				$breadcrumbs = [[$competition->group_history_page, $competition->group_name]];
			}
			$winners = Winner_Gateway::get_winners($competition);
			if ($competition->description) {
				if (str_starts_with($competition->description,'<')) {
					$winners =  "$competition->description\n$winners";
				} else {
					$winners =  "<p>$competition->description</p>\n$winners";
				}
			}
			Util::db_check();
			$this->insert_post([
				'post_title'    => $competition->name,
				'post_name'		=> $competition->history_page,
				'post_content'	=> $winners
			], $breadcrumbs);
			$progress->tick();
		}
		$progress->finish();
	}

	private function update_group_winners() {
		$competitions = Competition_Group_Gateway::get_history_competition_groups();
		Util::db_check();
		$progress = make_progress_bar( 'League/groups winners', count($competitions) );
		foreach ( $competitions as $competition ) {
			if ($competition->type === 'league') {
				$name = $competition->id === '1' ? "League Champions"
					: "$competition->name League Champions";
			} else {
				$name = "$competition->name Winners";
			}
			$data = Winner_Gateway::get_group_winners($competition->id);
			Util::db_check();
			$this->insert_post([
				'post_title'    => $name,
				'post_name'		=> $competition->history_page,
				'post_content'	=> $data
			]);
			$progress->tick();
		}
		$progress->finish();
	}

	private function insert_post($post, $breadcrumbs=false, $max_rounds=false) {
		$post = array_merge([
			'post_type'     => 'history',
			'post_status'   => 'publish',
			'ping_status'   => 'closed',
			'comment_status' => 'closed',
			'post_author'   => 2
		], $post);
		$old_page = get_page_by_path($post['post_name'],OBJECT,'history');
		if ($old_page) {
			$post['ID'] = $old_page->ID;
			$post['post_date'] = $old_page->post_date;
		}
		$post_id = wp_insert_post( $post, true );
		if( is_wp_error( $post_id ) ) {
			WP_CLI::error('wp_insert_post failed: ' . $post_id->get_error_message());
		}
		// leading _ ensures meta won't appear as a custom field on the edit screen
		if ($breadcrumbs) {
			update_post_meta( $post_id, '_semla_breadcrumbs', $breadcrumbs );
		} elseif ($old_page) {
			delete_post_meta( $post_id, '_semla_breadcrumbs' );
		}
		if ($max_rounds) {
			update_post_meta( $post_id, '_semla_max_flags_rounds', $max_rounds );
		// } elseif ($old_page) {
		// 	delete_post_meta( $post_id, '_semla_max_flags_rounds' );
		}
		if ($old_page) {
			$this->updated++;
		} else {
			$this->inserted++;
		}
	}
}
