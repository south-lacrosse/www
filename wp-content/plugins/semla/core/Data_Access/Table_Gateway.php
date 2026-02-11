<?php
namespace Semla\Data_Access;
use Semla\Render\Table_Renderer;
use Semla\Rest\Rest_Util;

/**
 * Data access for league tables
 */
class Table_Gateway {
	/**
	 * Return html for tables for a year
	 * @param int $year year from history, or 0 for current
	 * @param int $league_id id of the competition group for the league
	 * @param string $page tables page for league, used fot nest/prev page in
	 *   history. Ignored if year is 0
	 * @param string $grid_page page to link to for fixtures grid. Ignored if
	 *   year is 0
	 */
	public static function get_tables($year, $league_id, $page='', $grid_page='') {
		global $wpdb;

		if (!$year) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT c.section_name as name, t.comp_id, t.position, t.team,
					t.played, t.won, t.drawn, t.lost, t.goals_for, t.goals_against, t.goal_avg,
					t.points_deducted, t.points, t.divider, t.form, t.tiebreaker
				FROM slc_table AS t, sl_competition AS c
				WHERE c.id = t.comp_id AND c.group_id = %d
				ORDER BY c.seq, c.id, t.position', $league_id));
			if ($wpdb->last_error) return DB_Util::db_error();
		} else {
			$years = $wpdb->get_row( $wpdb->prepare(
				'SELECT year,
				(SELECT max(year) FROM slh_league_year y1
					WHERE y1.league_id = y.league_id AND y1.year < y.year) AS prev,
				(SELECT min(year) FROM slh_league_year y2
					WHERE y2.league_id = y.league_id AND y2.year > y.year) AS next
				FROM slh_league_year y
				WHERE y.league_id = %d AND y.year = %d', $league_id, $year));
			if ($wpdb->last_error) return false;
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT c.section_name as name, t.comp_id, t.position, t.team,
					t.played, t.won, t.drawn, t.lost, t.goals_for, t.goals_against, t.goal_avg,
					t.points_deducted, t.points, t.points_avg, t.divider, t.tiebreaker
				FROM slh_table AS t, sl_competition AS c
				WHERE c.id = t.comp_id AND t.year = %d AND c.group_id = %d
				ORDER BY c.seq, c.id, t.position', $year, $league_id));
			if ($wpdb->last_error) return false;
		}

		$comp_ids = array_unique( array_column($rows, 'comp_id') );
		$remarks = Competition_Gateway::get_remarks($year,$comp_ids);
		if ($wpdb->last_error) return DB_Util::db_error();

		ob_start();
		if ($year) {
			Table_Renderer::year_navigation($page, $grid_page, $year, $years);
		}
		Table_Renderer::tables($rows, 'league', $year, $remarks);
		return ob_get_clean();
	}

	public static function get_tables_years($league_id) {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare(
			'SELECT year FROM slh_league_year
				WHERE league_id = %d ORDER BY year', $league_id) );
	}

	public static function get_tables_for_team_club($type, $name, $extension) {
		global $wpdb;
		if ($type === 'team') {
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT CASE WHEN c.group_id = 1 THEN c.section_name
					ELSE c.name	END as name,
					t.comp_id, t.position, t.team,
					t.played, t.won, t.drawn, t.lost, t.goals_for, t.goals_against, t.goal_avg,
					t.points_deducted, t.points, t.divider, t.form, t.tiebreaker
				FROM slc_table AS t, slc_table AS t2, sl_competition AS c
				WHERE t2.team = %s
				AND t.comp_id = t2.comp_id
				AND c.id = t.comp_id
				ORDER BY c.seq, c.id, t.position', $name));
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT CASE WHEN c.group_id = 1 THEN c.section_name
					ELSE c.name	END as name,
					t.comp_id, t.position, t.team,
					t.played, t.won, t.drawn, t.lost, t.goals_for, t.goals_against, t.goal_avg,
					t.points_deducted, t.points, t.divider, t.form, t.tiebreaker
				FROM slc_table AS t,
				(SELECT DISTINCT t2.comp_id FROM slc_table AS t2, slc_team AS tm
					WHERE t2.team = tm.name AND tm.club = %s) AS comps
				, sl_competition AS c
				WHERE t.comp_id = comps.comp_id
				AND c.id = t.comp_id
				ORDER BY c.seq, c.id, t.position', $name));
		}
		if ($wpdb->last_error) return false;
		if ($extension !== '.html') {
			$tables = self::create_tables_array($rows);
			return Rest_Util::json_encode($tables);
		}
		ob_start();
		Table_Renderer::tables($rows, 'rest');
		return ob_get_clean();
	}

	/** Create array ready for JSON encode */
	private static function create_tables_array($rows) {
		$comp_id = 0;
		$tables = $teams = [];
		foreach ( $rows as $row ) {
			if ($row->comp_id <> $comp_id) {
				if ($comp_id) {
					$tables[] = self::table_from_teams($table_name, $teams, $has_points_deducted);
				}
				$table_name = $row->name;
				$comp_id = $row->comp_id;
				$teams = [];
				$has_points_deducted = false;
			}
			unset($row->comp_id);
			unset($row->name);
			$teams[] = $row;
			$row->points = floatval($row->points);
			$row->points_deducted = floatval($row->points_deducted);
			if ($row->points_deducted) {
				$has_points_deducted = true;
			}
			if (!$row->tiebreaker) {
				unset($row->tiebreaker);
			}
		}
		if ($comp_id) {
			$tables[] = self::table_from_teams($table_name, $teams, $has_points_deducted);
		}
		return $tables;
	}
	private static function table_from_teams($table_name, $teams, $has_points_deducted) {
		// removed points_deducted field if needed
		if (!$has_points_deducted) {
			foreach($teams as $key => $value){
				unset($teams[$key]->points_deducted);
			}
		}
		return ['name' => $table_name, 'teams' => $teams];
	}

	public static function get_mini_tables() {
		global $wpdb;

		$rows = $wpdb->get_results(
			'SELECT c.section_name, g.name AS league, t.comp_id, t.position, t.team,
				ta.abbrev, t.played, t.points, t.divider
			FROM slc_table AS t
			INNER JOIN sl_competition AS c ON c.id = t.comp_id
			INNER JOIN sl_competition_group AS g ON g.id = c.group_id
			LEFT JOIN sl_team_abbrev AS ta ON ta.team = t.team
			WHERE g.type = "league"
			ORDER BY c.seq, c.id, t.position');
		if ($wpdb->last_error) return DB_Util::db_error();
		if (!$rows) return '';

		$divisions = Competition_Gateway::get_all_divisions();
		if ($wpdb->last_error) return DB_Util::db_error();
		ob_start();
		require __DIR__ . '/views/mini-tables.php';
		return ob_get_clean();
	}

	public static function save_deductions($rows) {
		global $wpdb;
		$result = DB_Util::create_table('new_deduction',
			'`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`comp_id` INT UNSIGNED NOT NULL,
			`team` VARCHAR(50) NOT NULL,
			`penalty` DECIMAL (4,1) NOT NULL,
			`deduct_date` DATE,
			`reason` text NOT NULL,
			PRIMARY KEY (`id`),
			KEY `comp_team_idx` (`comp_id`, `team`)');
		if ($result === false) return false;
		if (count($rows) > 0) {
			foreach ( $rows as $key => $row ) {
				$values[] = $wpdb->prepare( '(%d,%s,%.1f,', array_slice($row,0,3))
					. ($row[3] == '' ? 'null' : "'$row[3]'")
					. $wpdb->prepare( ',%s)', $row[4]);
			}
			$query = 'INSERT INTO new_deduction (comp_id, team, penalty, deduct_date, reason) VALUES ';
			$query .= implode( ",\n", $values );
			$result = $wpdb->query($query);
			if ($result === false) return false;
		}
		DB_Util::add_table_to_rename('deduction');
		return true;
	}

	public static function save_tables($tables) {
		global $wpdb;
		// points deducted and points are decimal(4,1) in history as there were
		// 1/2 point deductions, but don't think we need that for current
		$result = DB_Util::create_table('new_table',
			'`comp_id` SMALLINT UNSIGNED NOT NULL,
			`position` TINYINT UNSIGNED NOT NULL,
			`team` VARCHAR(50) NOT NULL,
			`played` TINYINT NOT NULL,
			`won` TINYINT NOT NULL,
			`drawn` TINYINT NOT NULL,
			`lost` TINYINT NOT NULL,
			`goals_for` SMALLINT NOT NULL,
			`goals_against` SMALLINT NOT NULL,
			`goal_avg` DECIMAL (5,2) NOT NULL,
			`points_deducted` decimal(4,1) NOT NULL,
			`points` decimal(4,1) NOT NULL,
			`divider` BOOLEAN NOT NULL,
			`form` VARCHAR(40) NOT NULL,
			`tiebreaker` BOOLEAN NOT NULL,
			PRIMARY KEY (`comp_id`, `position`),
			UNIQUE KEY `team_comp` (`team`,`comp_id`)');
		if ($result === false) return false;

		$query = 'INSERT INTO new_table (comp_id, position, team, played, won, drawn, lost, goals_for,
			goals_against, goal_avg, points_deducted, points, divider, form, tiebreaker) VALUES ';
		foreach ($tables as $comp_id => $table) {
			$pos = 1;
			foreach ($table as $team) {
				$played = $team->won + $team->drawn + $team->lost;
				$values[] = $wpdb->prepare("($comp_id,$pos,%s,$played, $team->won, $team->drawn,"
					."$team->lost, $team->goals_for, $team->goals_against, $team->goal_avg, $team->points_deducted,"
					."$team->points,$team->divider,'$team->form',$team->tiebreaker)",$team->team);
				$pos++;
			}
		}
		$query .= implode( ",\n", $values );
		$result = $wpdb->query($query);
		if ($result === false) return false;
		DB_Util::add_table_to_rename('table');
		return true;
	}
}
