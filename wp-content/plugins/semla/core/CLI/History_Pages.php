<?php
namespace Semla\CLI;
/**
 * Create history pages for all tables/flags draws/results. Will overwrite page if already
 * exists.
 * 
 * This should only be need to run as part of the end of season process, or if the format
 * of any of the HTML has changed, e.g. if Cup_Draw_Renderer.php changes.
 */

use Semla\Cache;
use Semla\Data_Access\Competition_Gateway;
use Semla\Data_Access\Competition_Group_Gateway;
use Semla\Data_Access\Cup_Draw_Gateway;
use Semla\Data_Access\Fixtures_Gateway;
use Semla\Data_Access\Fixtures_Results_Gateway;
use Semla\Data_Access\Table_Gateway;
use Semla\Data_Access\Winner_Gateway;
use WP_CLI;
use function WP_CLI\Utils\make_progress_bar;

class History_Pages {
	private static $updated = 0;
	private static $inserted = 0;

	public static function update_pages() {
		WP_CLI::log('Creating WordPress history pages from the history database');
		self::tables();
		self::results();
		self::cup_draws();
		self::winners();
		self::group_winners();
		$url = get_option( 'siteurl' );
		self::insert_post([
			'post_title'    => 'Plate Finals',
			'post_name'     => 'plate-finals',
			'post_content'  => "<!-- wp:list {\"spacing\":\"medium-spaced\"} -->\n" . '<ul class="medium-spaced">'
				. '<li><a href="' . $url . '/history/plate-intermediate">Intermediate Plate</a></li>'
				. '<li><a href="' . $url . '/history/plate-minor">Minor Plate</a></li>'
				. '<li><a href="' . $url . '/history/plate-intermediate-east">Intermediate East Plate</a></li>'
				. '<li><a href="' . $url . '/history/plate-intermediate-west">Intermediate West Plate</a></li>'
				. '<li><a href="' . $url . '/history/plate-minor-east">Minor East Plate</a></li>'
				. '<li><a href="' . $url . '/history/plate-minor-west">Minor West Plate</a></li>'
				. '</ul>' . "\n<!-- /wp:list -->",
			]);
		Cache::clear_cache('hist');
		WP_CLI::Success('History pages updated: ' . self::$inserted . ' pages inserted, '
			. self::$updated  . ' pages updated');
	}

	private static function db_check() {
		global $wpdb;
		if ($wpdb->last_error) {
			WP_CLI::error('Database error: ' . $wpdb->last_error);
		}
	}

	private static function tables() {
		$leagues = Competition_Group_Gateway::get_leagues(true);
		self::db_check();
		foreach ($leagues as $league) {
			$name = $league->id === '1' ? "League" : "$league->name League";
			$years = Table_Gateway::get_tables_years($league->id);
			self::db_check();
			$progress = make_progress_bar( "$name Tables", count($years) );
			foreach ( $years as $year ) {
				$year = intval($year);
				$tables_page = "$league->page-$year";
				$meta = [[$league->history_page,"$name Champions"]];
				$grid_page = $year > 2002 ? $league->grid_page : '';
				$data = Table_Gateway::get_tables($year,
											$league->id,$league->page,$grid_page);
				self::db_check();
				self::insert_post([
					'post_title'    => "$year $name Tables",
					'post_name'		=> $tables_page,
					'post_content'	=> $data
				], $meta);
				$data = Fixtures_Gateway::get_grid($year,$league->id);
				self::db_check();
				if ($year > 2002) {
					self::insert_post([
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

	private static function results() {
		$gateway = new Fixtures_Results_Gateway();
		$years = $gateway->get_results_years();
		self::db_check();
		$html = '';
		$progress = make_progress_bar( 'Results', count($years) + 1 );
		foreach ( $years as $year ) {
			self::insert_post([
				'post_title'    => "$year Results",
				'post_name'		=> "results-$year",
			], [['results','Results']]);
			if ($html) $html .= ', ';
			$html .= "<a href=\"/history/results-$year\">$year</a>";
			$progress->tick();
		}
		self::insert_post([
			'post_title'    => "Results",
			'post_name'		=> "results",
			'post_content'  => "<p>Full results are available for the following years:</p>\n<p>"
				. $html . "</p>"
		]);
		$progress->tick();
		$progress->finish();

	}

	private static function cup_draws() {
		$years = Cup_Draw_Gateway::get_cup_years();
		self::db_check();
		$progress = make_progress_bar( 'Cup draws', count($years) );
		foreach ( $years as $year ) {
			if ($year->breadcrumbs) {
				$name = $year->history_group_page ? "$year->name Winners" : $year->name;
				$breadcrumbs = [[$year->history_page, $name]];
			} else {
				$breadcrumbs = false;
			}
			$data = Cup_Draw_Gateway::get_draws($year->year,$year->group_id,'',$year->history_page);
			self::db_check();
			self::insert_post([
				'post_title'    => "$year->year $year->name",
				'post_name'		=> "$year->history_page-$year->year",
				'post_content'	=> $data
			], $breadcrumbs, $year->max_rounds);
			$data = Cup_Draw_Gateway::get_draws($year->year,$year->group_id,
				'rounds',$year->history_page);
			self::db_check();
			self::insert_post([
				'post_title'    => "$year->year $year->name Rounds",
				'post_name'		=> "$year->history_page-$year->year-rounds",
				'post_content'	=> $data
			], $breadcrumbs,  $year->max_rounds);
			$progress->tick();
		}
		$progress->finish();
	}

	private static function winners() {
		$competitions = Competition_Gateway::get_history_competitions();
		self::db_check();
		$progress = make_progress_bar( 'Competition winners', count($competitions) );
		foreach ( $competitions as $competition ) {
			$breadcrumbs = false;
			if ($competition->history_group_page) {
				$breadcrumbs = [[$competition->group_history_page, $competition->group_name]];
			}
			$winners = Winner_Gateway::get_winners($competition);
			self::db_check();
			self::insert_post([
				'post_title'    => $competition->name,
				'post_name'		=> $competition->history_page,
				'post_content'	=> $winners
			], $breadcrumbs);
			$progress->tick();
		}
		$progress->finish();
	}

	private static function group_winners() {
		$competitions = Competition_Group_Gateway::get_history_competition_groups();
		self::db_check();
		$progress = make_progress_bar( 'League/groups winners', count($competitions) );
		foreach ( $competitions as $competition ) {
			if ($competition->type === 'league') {
				$name = $competition->id === '1' ? "League Champions"
					: "$competition->name League Champions";
			} else {
				$name = "$competition->name Winners";
			}
			$data = Winner_Gateway::get_group_winners($competition->id);
			self::db_check();
			self::insert_post([
				'post_title'    => $name,
				'post_name'		=> $competition->history_page,
				'post_content'	=> $data
			]);
			$progress->tick();
		}
		$progress->finish();
	}

	private static function insert_post($post, $breadcrumbs=false, $max_rounds=false) {
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
			self::$updated++;
		} else {
			self::$inserted++;
		}
	}
}