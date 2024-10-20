<?php
namespace Semla\Data_Access;
use Semla\Cache;
use Semla\Render\Fixtures_Renderer;
/**
 * Fixtures/results data access
 */
class Fixtures_Results_Gateway {
	private $options;

	/**
	 * Get data for select boxes for results page for a year.
	 * Returns an associative array for team,competition,dates, or false on failure
	 */
	public function get_result_options($year) {
		global $wpdb;

		// options are used on all results pages (for team/club/comp) so caching is wise
		$resource = 'result-' . $year . '.txt';
		$data = Cache::get_cache_value($resource, 'hist');
		if ($data) {
			$this->options = $data;
			return $data;
		}

		// otherwise build arrays from database, and serialize
		$teams = $comps = $dates = [];
		//team
		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT DISTINCT r.home, COALESCE(ta.abbrev,"") AS abbrev
			FROM slh_result AS r
			LEFT JOIN sl_team_abbrev AS ta
			ON ta.team = r.home
			WHERE r.year = %d AND r.comp_id <> 0
			ORDER BY r.home', $year));
		if ($wpdb->last_error) return false;
		foreach ( $rows as $row ) {
			$teams[$row->home] = htmlentities($row->abbrev);
		}

		// competition
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT c.id,
				CASE WHEN c.group_id = 1 THEN c.section_name
					ELSE c.name	END AS name,
				IF (c.type = 'cup',1,0) AS is_cup, hc.where_clause
			FROM slh_competition as hc, sl_competition AS c
			WHERE hc.year = %d AND c.id = hc.comp_id
			AND (c.type = 'cup' OR c.type LIKE 'league%')
			ORDER BY c.seq", $year));
		if ($wpdb->last_error) return false;
		foreach ( $rows as $row ) {
			$comps[$row->name] = [$row->is_cup, $row->where_clause];
		}

		// date
		$rows = $wpdb->get_col( $wpdb->prepare(
			'SELECT DISTINCT match_date FROM slh_result
			WHERE year = %d
			ORDER BY match_date', $year));
		if ($wpdb->last_error) return false;
		foreach ( $rows as $row ) {
			$userDate = date('d M Y', strtotime($row));
			$dates[$userDate] = $row;
		}
		$data = [
			'team' => $teams,
			'comp' => $comps,
			'date' => $dates
		];
		Cache::write_cache_value($resource, $data, 'hist');
		$this->options = $data;
		return $data;
	}

	public function get_results_years() {
		global $wpdb;
		return $wpdb->get_col('SELECT DISTINCT year FROM slh_result');
	}

	/**
	 * Get data for select boxes for fixtures page
	 * Returns an associative array of team,club,competition,dates, or false on failure
	 */
	public function get_fixtures_options() {
		global $wpdb;

		// options are used on all results pages (for team/club/comp) so caching is wise
		$resource = 'fixtures.txt';
		$data = Cache::get_cache_value($resource);
		if ($data) {
			$this->options = $data;
			return $data;
		}

		// otherwise build arrays from database, and serialize
		$dates = [];
		$rows = $wpdb->get_col(
			'SELECT match_date FROM slc_fixture_date ORDER BY match_date');
		if ($wpdb->last_error) return false;
		foreach ( $rows as $row ) {
			$userDate = date('d M Y', strtotime($row));
			$dates[$userDate] = $row;
		}
		$data = Club_Team_Gateway::get_teams_abbrev_club_page(); // team and team_club
		if ($data === false) return false;
		$clubs = Club_Team_Gateway::get_clubs_teams(true);
		if ($clubs === false) return false;
		$data['club'] = $clubs;

		// competition
		$rows = $wpdb->get_results(
			"SELECT c.id,
				CASE WHEN c.group_id = 1 THEN c.section_name
					ELSE c.name	END AS name,
				IF (c.type = 'cup',1,0) AS is_cup, cc.where_clause
			FROM slc_competition as cc, sl_competition AS c
			WHERE c.id = cc.comp_id
			AND (c.type = 'cup' OR c.type LIKE 'league%')
			ORDER BY c.seq");
		if ($wpdb->last_error) return false;
		$comps = [];
		foreach ( $rows as $row ) {
			$comps[$row->name] = [$row->is_cup, $row->where_clause];
		}

		$data['comp'] = $comps;
		$data['date'] = $dates;
		Cache::write_cache_value($resource, $data);
		$this->options = $data;
		return $data;
	}

	/**
	 * @param int year year, or 0 for current
	 */
	public function get_fixtures($year, $type, $arg) {
		global $wpdb;

		$sql = 'SELECT f.match_date, f.competition, f.home, f.away, f.result, f.points_multi';
		if (!$year) {
			$sql .= ", f.venue, f.match_time, CASE
					WHEN f.result = '' THEN (SELECT t.pitch_type FROM slc_team AS t WHERE t.name = COALESCE(f.venue,f.home))
					ELSE null
				END AS pitch_type";
		}
		$sql .=' FROM ';
		if (!$year) {
			$sql .= 'slc_fixture';
			$yr_and = '';
		} else {
			$sql .= 'slh_result';
			$yr_and = 'year=' . $year . ' AND ';
		}
		$sql .= ' AS f';
		$order_by = ' ORDER BY f.id';
		$is_cup = $has_ladders = false;
		switch ($type) {
			case 'team':
				$query = $wpdb->prepare($sql
					. " WHERE $yr_and(f.home = %s OR f.away = %s)$order_by"
					, $arg, $arg);
				break;
			case 'club':
				$teams = explode('|', $this->options['club'][$arg]->teams);
				if (count($teams) === 1) {
					$query = $wpdb->prepare("$sql WHERE (f.home = %s OR f.away = %s)$order_by",
						 $teams[0], $teams[0]);
				} else {
					foreach ($teams as $team) {
						$s[] = '%s';
					}
					$teams = $wpdb->prepare( implode(',',$s), $teams);
					$query = "$sql WHERE (f.home IN ($teams) OR f.away IN ($teams))$order_by";
				}
				break;
			case 'date':
				$date = $this->options['date'][$arg];
				$query = "$sql WHERE {$yr_and}f.match_date='$date'$order_by";
				break;
			case 'comp':
				list ($is_cup, $where) = $this->options['comp'][$arg];
				if (!$is_cup && $where[0] !== '=') {
					$has_ladders = true;
					$where = ' ' . $where;
				}
				$query =  "$sql WHERE {$yr_and}f.comp_id $where$order_by";
				break;
			case 'all':
				if (!$year) {
					$query = "$sql$order_by";
				} else {
					$query = "$sql WHERE f.year = $year$order_by";
				}
				break;
			case 'default':
				$where_dates = self::get_where_dates(array_values($this->options['date']),'f.');
				$query = "$sql $where_dates $order_by";
				break;
			default:
				return '';
		}

		$rows = $wpdb->get_results( $query );
		if ($wpdb->last_error) return DB_Util::db_error();
		if (count($rows) === 0) return '';
		ob_start();
		(new Fixtures_Renderer())->fixtures($year, $rows, $type, $arg, $is_cup, $has_ladders, $this->options);
		return ob_get_clean();
	}

	public static function recent_results() {
		global $wpdb;

		// get dates around current date, get 2 weeks before & after so
		// we always get at least 2 weeks, and narrow it down in code
		// to get the 2 weeks surrounding today
		$rows = $wpdb->get_results(
			'SELECT match_date FROM (
				(SELECT match_date FROM slc_fixture_date
					WHERE match_date < CURDATE()
					ORDER BY match_date DESC
					LIMIT 2)
				UNION ALL
				(SELECT match_date FROM slc_fixture_date
					WHERE match_date >= CURDATE()
					ORDER BY match_date ASC
					LIMIT 2) ) AS a
					ORDER BY match_date');
		if ($wpdb->last_error) return DB_Util::db_error();
		if (count($rows) == 0) return '';
		$dates = [];
		foreach ($rows as $row) {
			$dates[] = $row->match_date;
		}
		$rows = $wpdb->get_results(
			'SELECT match_date, match_time, competition, home, away, result, venue
			FROM slc_fixture ' . self::get_where_dates($dates) . ' ORDER BY id');
		if ($wpdb->last_error) return DB_Util::db_error();
		ob_start();
		require __DIR__ . '/views/recent-results.php';
		return ob_get_clean();
	}

	/**
	 * Return sql for where clause for default fixtures, i.e. when the user hasn't
	 * selected a date/team etc. We return 2 dates around the current date (so if today
	 * is a match date then we get 3 dates), minimum 2 dates so if it's before the
	 * season we get the first 2 dates.
	 */
	private static function get_where_dates($dates,$prefix='') {
		$today_datetime = new \DateTime('now', new \DateTimeZone('Europe/London'));
		$today = $today_datetime->format('Y-m-d');

		$count = count($dates);
		if ($count <= 1) return '';
		if ($count === 2 || $today < $dates[1]) {
			$compare_date = $dates[0];
			$compare_date2 = $dates[1];
		} else {
			$before_last = $count - 2;
			if ($today >= $dates[$before_last]) {
				$compare_date = $today === $dates[$before_last] ?
								$dates[$before_last - 1] : $dates[$before_last];
				$compare_date2 = $dates[$before_last+1];
			} else {
				// start at 1 because it may be equal to today
				$compare_date2 = $dates[0];
				for ($i = 1; $i < $count; $i++ ) {
					$compare_date = $compare_date2;
					$compare_date2 = $dates[$i];
					// if today is a match day then return 2 weeks around
					if ($compare_date2 === $today) {
						if ($i < $count) {
							$compare_date2 = $dates[$i+1];
							break;
						}
						break;
					}
					if ($compare_date2 > $today) {
						break;
					}
				}
			}
		}
		return "WHERE {$prefix}match_date BETWEEN '$compare_date' AND '$compare_date2'";
	}
}
