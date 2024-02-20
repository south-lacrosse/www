<?php
namespace Semla\Data_Access;
use Semla\Rest\Rest_Util;

/**
 * Fixtures data for REST requests
 */
class Rest_Fixtures_Gateway {

	public static function get_fixtures($type, $name, $extension) {
		global $wpdb;

		if ($type === 'team') {
			$where = $wpdb->prepare('=%s',$name);
		} else {
			$teams = Club_Team_Gateway::get_teams_for_club($name);
			if ($teams === false) return false;
			if (count($teams) === 1) {
				$where = $wpdb->prepare('=%s',$teams[0]);
			} else {
				foreach ($teams as $team) {
					$s[] = '%s';
					$names[] = $team;
				}
				$teams = $wpdb->prepare( implode(',',$s), $names);
				$where = " IN ($teams)";
			}
		}
		$rows = $wpdb->get_results(
			"SELECT f.match_date, f.competition, f.home, f.away, f.result, f.points_multi,
				f.venue, f.match_time, CASE
				WHEN f.result = '' THEN (SELECT t.pitch_type FROM slc_team AS t WHERE t.name = COALESCE(f.venue,f.home))
				ELSE null
			END AS pitch_type
			FROM slc_fixture AS f
			WHERE (f.home$where OR f.away$where)
			ORDER BY f.id");
		if ($wpdb->last_error) return false;
		if ($extension !== '.html') {
			foreach ($rows as $row) {
				if (empty($row->venue)) {
					unset($row->venue);
				}
				if (empty($row->pitch_type)) {
					unset($row->pitch_type);
				}
				if (!empty($row->result)) {
					unset($row->match_time);
					unset($row->venue);
				}
				if ($row->points_multi == 1) {
					unset($row->points_multi);
				}
			}
			return Rest_Util::json_encode($rows);
		}
		ob_start();
		require __DIR__ . '/views/rest-fixtures.php';
		return ob_get_clean();
	}

	public static function get_fixtures_ics($team) {
		global $wpdb;

		$where = $wpdb->prepare('=%s',$team);
		$rows = $wpdb->get_results(
			"SELECT f.match_date, f.competition, f.home, f.away,
				f.venue, f.match_time, t.pitch_type
			FROM slc_fixture AS f
			LEFT JOIN slc_team AS t
			ON t.name = COALESCE(f.venue,f.home)
			WHERE (f.home$where OR f.away$where) AND (f.result = '' OR f.home_goals IS NOT NULL)
			ORDER BY f.id");
		if ($wpdb->last_error) return false;
		ob_start();
		require __DIR__ . '/views/rest-fixtures.ics.php';
		return ob_get_clean();
	}

	public static function log_fixtures_ics($team) {
		global $wpdb;
		return $wpdb->query($wpdb->prepare(
			'INSERT INTO `sl_calendar_log` (team) VALUES (%s)
			ON DUPLICATE KEY UPDATE access_cnt=access_cnt+1'
			, $team ));
	}
}
