<?php
namespace Semla\Data_Access;
/**
 * Data access for Competitions and Divisions
 */
class Competition_Gateway {

	/**
	 * Returns an array of competitions, indexed by competition name including league
	 */
	public static function get_competitions() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT id, name, abbrev, seq, type FROM sl_competition");
		if ($wpdb->last_error) return false;
		$data = [];
		foreach ( $rows as $row ) {
			$data[$row->name] = $row;
		}
		return $data;
	}

	public static function get_current_competitions() {
		global $wpdb;
		$rows = $wpdb->get_results(
			"SELECT name, id, is_cup
			FROM (
				SELECT CASE WHEN c.group_id = 1 THEN c.section_name
					ELSE c.name END AS name, c.id,
					IF(c.type = 'cup',1,0) AS is_cup, c.seq
				FROM sl_competition AS c, slc_division AS d
				WHERE c.id = d.comp_id
				UNION
				SELECT CASE WHEN c.group_id = 1 THEN c.section_name
					ELSE c.name END AS name, c.id,
					IF (c.type = 'cup',1,0) AS is_cup, c.seq
				FROM sl_competition AS c, slc_cup_draw AS cd
				WHERE cd.round = 1 AND cd.match_num = 1
				AND c.id = cd.comp_id
				) a
			ORDER BY seq");
		if ($wpdb->last_error) return false;
		$comps = $cups = [];
		foreach ( $rows as $row ) {
			$comps[$row->name] = $row->id;
			if ($row->is_cup) {
				$cups[$row->id] = 1;
			}
		}
		return [$comps, $cups];
	}

	public static function get_history_competitions() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT c.id, c.name, c.seq, c.section_name, c.head_to_head, c.history_page,
				g.history_group_page, g.name AS group_name,
				g.history_page AS group_history_page,
				IF (c.type = 'cup' AND g.history_page != '',1,0) AS link_to_draws,
				IF (g.history_group_page, c.group_id,NULL) as group_id
			FROM sl_competition c
			LEFT OUTER JOIN (SELECT id, CONCAT(name,' Winners') AS name,
					history_page, history_group_page
				FROM sl_competition_group
				) g ON g.id = c.group_id AND c.type = 'cup'
			WHERE c.history_page != '' AND c.has_history = 1");
	}
	 
	/**
	 * Get divisions for a league, including team names and minimals
	 */
	public static function get_divisions($year, $league_id) {
		global $wpdb;
		$table = $year ? 'slh_table' : 'slc_table';
		$query = "SELECT t.comp_id, c.section_name,
			GROUP_CONCAT(t.team ORDER BY t.team SEPARATOR '|') AS teams,
			GROUP_CONCAT(minimal ORDER BY t.team SEPARATOR '|') AS minimals
			FROM $table t, sl_competition c, sl_team_minimal m
		WHERE ";
		if ($year) {
			$query .= $wpdb->prepare('t.year = %d AND ', $year);
		}
		$query .= $wpdb->prepare(' c.id = t.comp_id AND c.group_id = %d
			AND m.team = t.team
			GROUP BY t.comp_id
			ORDER BY c.seq, t.comp_id', $league_id);
		return $wpdb->get_results($query);
	}
	
	/**
	 * Get divisions
	 */
	public static function get_all_divisions() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT d.comp_id, g.name AS league, g.page, c.section_name
				FROM slc_division d, sl_competition c,
					sl_competition_group g
				WHERE d.comp_id = c.id
				AND g.id = c.group_id
				ORDER BY g.id, c.seq');
	}

	/**
	 * Get division info - promoted/relegated
	 */
	public static function get_division_info() {
		global $wpdb;
		// Use ARRAY_N so we match if divisions are loaded from the fixtures sheet
		$res = $wpdb->get_results(
			'SELECT comp_id, sort_order, promoted, relegated_after
			FROM slc_division ORDER by comp_id', ARRAY_N);
		if ($wpdb->last_error) return false;
		return $res;
	}

	public static function save_divisions($rows) {
		global $wpdb;
		$result = DB_Util::create_table('new_division',
			'`comp_id` SMALLINT UNSIGNED NOT NULL,
			`sort_order` CHAR(1) NOT NULL,
			`promoted` TINYINT NOT NULL,
			`relegated_after` TINYINT NOT NULL,
			PRIMARY KEY (`comp_id`)');
		if ($result === false) return false;
		foreach ( $rows as $key => $row ) {
			$values[] = $wpdb->prepare( "(%d,%s,%d,%d)", $row );
		}
		$query = 'INSERT INTO new_division (comp_id, sort_order, promoted, relegated_after) VALUES ';
		$query .= implode( ",\n", $values );
		$result = $wpdb->query($query);
		if ($result === false) return false;
		DB_Util::add_table_to_rename('division');
		return true;
	}

	public static function save_remarks($rows) {
		global $wpdb;
		$result = DB_Util::create_table('new_remarks',
			'`comp_id` SMALLINT UNSIGNED NOT NULL,
			`remarks` text NOT NULL,
			PRIMARY KEY (`comp_id`)');
		if ($result === false) return false;
		foreach ( $rows as $key => $row ) {
			$values[] = $wpdb->prepare( "(%d,%s)", $row );
		}
		$query = 'INSERT INTO new_remarks (comp_id, remarks) VALUES ';
		$query .= implode( ",\n", $values );
		$result = $wpdb->query($query);
		if ($result === false) return false;
		DB_Util::add_table_to_rename('remarks');
		return true;
	}
}
