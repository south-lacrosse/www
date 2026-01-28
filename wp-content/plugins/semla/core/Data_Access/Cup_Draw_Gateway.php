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

		if (!$year) {
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT c.section_name, cd.comp_id, cd.round, cd.match_num,
					crd.match_date, cd.team1, cd.team2, ta1.abbrev AS alias1,
					ta2.abbrev AS alias2, cd.team1_goals, cd.team2_goals,
					cd.result_extra, cd.home_team
				FROM slc_cup_draw AS cd
				LEFT JOIN sl_competition AS c
				ON c.id = cd.comp_id
				LEFT JOIN slc_cup_round_date AS crd
				ON crd.comp_id = cd.comp_id AND crd.round = cd.round
				LEFT OUTER JOIN sl_team_abbrev ta1
				ON ta1.team = cd.team1
				LEFT OUTER JOIN sl_team_abbrev ta2
				ON ta2.team = cd.team2
				WHERE c.group_id = %d
				ORDER BY c.seq, cd.comp_id, cd.round, cd.match_num', $group_id ));
			if ($wpdb->last_error) return DB_Util::db_error();
			// group stages
			$group_rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT c.related_comp_id, c.section_name as name, t.comp_id, t.position, t.team,
					t.played, t.won, t.drawn, t.lost, t.goals_for, t.goals_against, t.goal_avg,
					t.points_deducted, t.points, t.divider, t.form, t.tiebreaker
				FROM slc_table AS t, sl_competition AS c
				WHERE c.id = t.comp_id AND c.group_id = %d
				ORDER BY c.related_comp_id, c.seq, c.id, t.position', $group_id));
			if ($wpdb->last_error) return DB_Util::db_error();
			// TODO: optimize?? not that many rows, so do we need to join?
			$remarks = $wpdb->get_results(
				'SELECT comp_id, remarks FROM slc_remarks', OBJECT_K);
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
			// group stages
			$group_rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT c.related_comp_id, c.section_name as name, t.comp_id, t.position, t.team,
					t.played, t.won, t.drawn, t.lost, t.goals_for, t.goals_against, t.goal_avg,
					t.points_deducted, t.points, t.points_avg, t.divider, t.tiebreaker
				FROM slh_table AS t, sl_competition AS c
				WHERE c.id = t.comp_id AND t.year = %d AND c.group_id = %d
				ORDER BY c.related_comp_id, c.seq, c.id, t.position', $year, $group_id));
			if ($wpdb->last_error) return false;

			// TODO: optimize?? not that many rows, so do we need to join?
			$remarks = $wpdb->get_results( $wpdb->prepare(
				'SELECT comp_id, remarks
				FROM slh_remarks
				WHERE year = %d', $year), OBJECT_K);
			if ($wpdb->last_error) return false;
			$rows = $wpdb->get_results( $wpdb->prepare(
				'SELECT c.section_name, cd.comp_id, cd.round, cd.match_num,
					cd.team1, cd.team2, ta1.abbrev AS alias1, ta2.abbrev AS alias2,
					cd.team1_goals, cd.team2_goals, cd.result_extra, cd.home_team
				FROM slh_cup_draw AS cd
				LEFT JOIN sl_competition AS c
				ON c.id = cd.comp_id
				LEFT OUTER JOIN sl_team_abbrev ta1
				ON ta1.team = cd.team1
				LEFT OUTER JOIN sl_team_abbrev ta2
				ON ta2.team = cd.team2
				WHERE cd.year = %d AND c.group_id = %d
				ORDER BY c.seq, cd.comp_id, cd.round, cd.match_num', $year, $group_id ));
			if ($wpdb->last_error) return false;
		}
		if (count($rows) === 0) return '';
		ob_start();
		Cup_Draw_Renderer::cup_draw($year,$display,$years,$rows,$group_rows,$remarks,$slug);
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
		return $wpdb->get_results('SELECT c.name, cd.comp_id, cd.round, cd.match_num,
			crd.match_date, cd.home_team
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
