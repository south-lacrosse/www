<?php
namespace Semla\Data_Access;
use Semla\Render\Cup_Draw_Renderer;
/**
 * Data access for cup draws
 */
class Cup_Draw_Gateway {

	/**
	 * @param int $year year from history, or 0 for current
	 * @param int $group_id id of the competition group to display, e.g. Flags
	 * @param string $display empty for default, or "rounds"
	 * @param string $slug slug for links to previous/next year for history
	 */
	public static function get_draws($year, $group_id, $display = '', $slug='') {
		global $wpdb;

		// display "rounds" does not need team name abbreviations
		if ($display) {
			$select_clause = $join_clause = '';
			// only used in history query to ignore byes
			$where_clause = ' AND cd.team1 <> "Bye" AND cd.team2 <> "Bye"';
		} else {
			$select_clause = ' ta1.abbrev AS alias1, ta2.abbrev AS alias2,';
			$join_clause = 'LEFT OUTER JOIN sl_team_abbrev ta1 ON ta1.team = cd.team1
				LEFT OUTER JOIN sl_team_abbrev ta2 ON ta2.team = cd.team2';
			$where_clause = '';
		}
		if (!$year) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT c.section_name, c.related_comp_id, cd.comp_id, cd.round, cd.match_num,
					crd.match_date, cd.team1, cd.team2,$select_clause cd.team1_goals, cd.team2_goals,
					cd.result_extra, cd.home_team
				FROM slc_cup_draw AS cd
				LEFT JOIN sl_competition AS c
				ON c.id = cd.comp_id
				LEFT JOIN slc_cup_round_date AS crd
				ON crd.comp_id = cd.comp_id AND crd.round = cd.round
				$join_clause
				WHERE c.group_id = %d
				ORDER BY c.seq, cd.comp_id, cd.round, cd.match_num", $group_id ));
			if ($wpdb->last_error) return DB_Util::db_error();
			if (count($rows) === 0) return '';
			// group stages - either the tables for normal display, or fixtures in rounds
			// Note: Could include slc_competition in next 2 queries to limit results
			// from slc_competition to the current year, but that doesn't seem to make
			// a noticeable difference to performance
			if (!$display) {
				$group_rows = $wpdb->get_results( $wpdb->prepare(
					'SELECT c.related_comp_id, c.section_name as name, t.comp_id, t.position, t.team,
						t.played, t.won, t.drawn, t.lost, t.goals_for, t.goals_against, t.goal_avg,
						t.points_deducted, t.points, t.divider, t.form, t.tiebreaker
					FROM slc_table AS t, sl_competition AS c
					WHERE c.group_id = %d AND c.type = "cup-group"
					AND t.comp_id = c.id
					ORDER BY c.related_comp_id, c.seq, c.id, t.position', $group_id));
			} else {
				$group_rows = $wpdb->get_results( $wpdb->prepare(
					'SELECT c.related_comp_id, f.comp_id, c.section_name,
						f.home as team1, f.away as team2, f.result
					FROM sl_competition AS c, slc_fixture AS f
					WHERE c.group_id = %d AND c.type = "cup-group"
					AND f.comp_id = c.id AND f.result <> "R - R"
					ORDER BY c.related_comp_id, c.seq, c.id, f.id;', $group_id));
			}
			if ($wpdb->last_error) return DB_Util::db_error();
			$years = false;
		} else {
			$years = $wpdb->get_row( $wpdb->prepare(
				'SELECT year,
				(SELECT max(year) FROM slh_cup_year y1
					WHERE y1.group_id = y.group_id and y1.year < y.year) as prev,
				(SELECT min(year) FROM slh_cup_year y2
					WHERE y2.group_id = y.group_id and y2.year > y.year) as next
				FROM slh_cup_year y
				WHERE y.group_id = %d AND y.year = %d', $group_id, $year));
			if ($wpdb->last_error) return false;
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT c.section_name, c.related_comp_id, cd.comp_id, cd.round, cd.match_num,
					cd.team1, cd.team2,$select_clause
					cd.team1_goals, cd.team2_goals, cd.result_extra, cd.home_team
				FROM slh_cup_draw AS cd
				LEFT JOIN sl_competition AS c
				ON c.id = cd.comp_id
				$join_clause
				WHERE cd.year = %d AND c.group_id = %d $where_clause
				ORDER BY c.seq, cd.comp_id, cd.round, cd.match_num", $year, $group_id ));
			if ($wpdb->last_error) return false;
			if (count($rows) === 0) return '';
			// group stages - either the tables for normal display, or fixtures in rounds
			// First time groups used is 2025, so don't run query before then
			if ($year < 2025) {
				$group_rows = [];
			} elseif (!$display) {
				$group_rows = $wpdb->get_results( $wpdb->prepare(
					'SELECT c.related_comp_id, c.section_name as name, t.comp_id, t.position, t.team,
						t.played, t.won, t.drawn, t.lost, t.goals_for, t.goals_against, t.goal_avg,
						t.points_deducted, t.points, t.points_avg, t.divider, t.tiebreaker
					FROM slh_table AS t, sl_competition AS c
					WHERE c.group_id = %d AND c.type = "cup-group"
					AND t.comp_id = c.id AND t.year = %d
					ORDER BY c.related_comp_id, c.seq, c.id, t.position', $group_id, $year));
			} else {
				$group_rows = $wpdb->get_results( $wpdb->prepare(
					'SELECT c.related_comp_id, r.comp_id, c.section_name,
						r.home as team1, r.away as team2, r.result
					FROM sl_competition AS c, slh_result AS r
					WHERE c.group_id = %d AND c.type = "cup-group"
					AND r.year = %d AND r.comp_id = c.id AND r.result <> "R - R"
					ORDER BY c.related_comp_id, c.seq, c.id, r.id', $group_id, $year));
			}
			if ($wpdb->last_error) return false;
		}

		// need to get remarks for the flags and also group stages
		$comp_ids = array_merge(
			array_unique(array_column($rows, 'comp_id')),
			array_unique(array_column($group_rows, 'comp_id'))
		);
		$remarks = Competition_Gateway::get_remarks($year,$comp_ids);
		if ($wpdb->last_error) return DB_Util::db_error();

		ob_start();
		Cup_Draw_Renderer::cup_draws($year,$display,$years,$rows,$group_rows,$remarks,$slug);
		return ob_get_clean();
	}

	public static function get_cup_years() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT cy.group_id, cy.year, cy.max_rounds, g.name, g.history_page, g.history_group_page,
			CASE WHEN EXISTS (SELECT * FROM sl_competition c
				WHERE c.group_id = cy.group_id AND c.history_page <> '')
				THEN 1 ELSE 0 END AS breadcrumbs
			FROM slh_cup_year cy, sl_competition_group g
			WHERE g.id = cy.group_id
			ORDER BY cy.group_id, cy.year");
	}

	public static function get_cup_fixtures_for_sheet() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT c.name, cd.comp_id, cd.round, cd.match_num,
			crd.match_date, cd.home_team, IF (c.related_comp_id = 0,0,1) AS prelim
			FROM slc_cup_draw AS cd
			LEFT JOIN sl_competition AS c
			ON c.id = cd.comp_id
			LEFT JOIN slc_cup_round_date AS crd
			ON crd.comp_id = cd.comp_id AND crd.round = cd.round
			ORDER BY c.seq, cd.comp_id, cd.round, cd.match_num');
	}

	public static function save_current($rows, $round_dates) {
		global $wpdb;
		$result = DB_Util::create_table('new_cup_draw',
			'`comp_id` SMALLINT UNSIGNED NOT NULL,
			`round` TINYINT UNSIGNED NOT NULL,
			`match_num` TINYINT UNSIGNED NOT NULL,
			`team1` VARCHAR(50) NOT NULL,
			`team2` VARCHAR(50) NOT NULL,
			`team1_goals` TINYINT,
			`team2_goals` TINYINT,
			`result_extra` VARCHAR(20) NOT NULL,
			`home_team` TINYINT NOT NULL,
			PRIMARY KEY (`comp_id`, `round`, `match_num`)');
		if ($result === false) return false;
		foreach ( $rows as $key => $row ) {
			$values[] = "($row[0],$row[1],$row[2],"
				. $wpdb->prepare( '%s,%s,', $row[3], $row[4] )
				. ($row[5] == '' ? 'null' : $row[5]) . ','
				. ($row[6] == '' ? 'null' : $row[6])
				. ",'',$row[7])";
		}
		$query = 'INSERT INTO new_cup_draw (comp_id, round, match_num, team1, team2,
			team1_goals, team2_goals, result_extra, home_team) VALUES ';
		$query .= implode( ",\n", $values );
		$result = $wpdb->query($query);
		if ($result === false) return false;
		DB_Util::add_table_to_rename('cup_draw');

		$result = DB_Util::create_table('new_cup_round_date',
			  '`comp_id` SMALLINT UNSIGNED NOT NULL,
			  `round` TINYINT UNSIGNED NOT NULL,
			  `match_date` DATE NOT NULL,
			  PRIMARY KEY (`comp_id`, `round`)');
		if ($result === false) return false;
		$query = 'INSERT INTO new_cup_round_date (comp_id, round, match_date) VALUES ';
		$values = [];
		foreach ( $round_dates as $row ) {
			$values[] = "($row[0],$row[1],'$row[2]')";
		}
		$query .= implode( ",\n", $values );
		$result = $wpdb->query($query);
		if ($result === false) return false;
		DB_Util::add_table_to_rename('cup_round_date');
		return true;
	}
}
