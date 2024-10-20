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
			"SELECT id, name, abbrev, seq, type, related_comp_id, related_comp_id2 FROM sl_competition");
		if ($wpdb->last_error) return false;
		$data = [];
		foreach ( $rows as $row ) {
			$data[$row->name] = $row;
		}
		return $data;
	}

	public static function get_history_competitions() {
		global $wpdb;
		return $wpdb->get_results(
			"SELECT c.id, c.name, c.seq, c.section_name, c.head_to_head, c.history_page,
				c.description, g.history_group_page, g.name AS group_name,
				g.history_page AS group_history_page,
				IF (c.type = 'cup' AND g.history_page != '',1,0) AS link_to_draws,
				IF (g.history_group_page, c.group_id,NULL) as group_id
			FROM sl_competition c
			LEFT OUTER JOIN (SELECT id, CONCAT(name,' Winners') AS name,
					history_page, history_group_page
				FROM sl_competition_group
				) g ON g.id = c.group_id AND c.type = 'cup'
			WHERE c.history_page != ''");
	}

	/**
	 * Get divisions for a league with team names and minimals, including ladders
	 */
	public static function get_divisions($year, $league_id) {
		global $wpdb;
		$table = $year ? 'slh_table' : 'slc_table';
		$query = "SELECT c.id, c.seq, c.section_name,
			GROUP_CONCAT(t.team ORDER BY t.team SEPARATOR '|') AS teams,
			GROUP_CONCAT(COALESCE(m.minimal, LEFT(t.team,4)) ORDER BY t.team SEPARATOR '|') AS minimals
			FROM sl_competition c
			INNER JOIN $table t ON c.id = t.comp_id
			LEFT JOIN sl_team_minimal m ON m.team = t.team
			WHERE ";
		if ($year) {
			$query .= $wpdb->prepare('t.year = %d AND ', $year);
		}
		$query .= $wpdb->prepare('c.group_id = %d
			GROUP BY c.id', $league_id);
		$rows = $wpdb->get_results($query, OBJECT_K);
		if ($wpdb->last_error) return false;

		// add ladders to divisions
		if ($year) {
			$query = $wpdb->prepare(
				'SELECT c.id, c.seq, c.section_name, c.related_comp_id, c.related_comp_id2
				FROM slh_competition hc, sl_competition c
				WHERE hc.year = %d AND c.id = hc.comp_id AND c.group_id = %d
				AND c.type = "ladder"', $year, $league_id);
		} else {
			$query = $wpdb->prepare(
				'SELECT c.id, c.seq, c.section_name, c.related_comp_id, c.related_comp_id2
				FROM slc_ladder l, sl_competition c
				WHERE c.id = l.comp_id AND c.group_id = %d', $league_id);
		}
		$ladders = $wpdb->get_results($query);
		if ($wpdb->last_error) return false;

		foreach ($ladders as $ladder) {
			$comp1 = $rows[$ladder->related_comp_id];
			$comp2 = $rows[$ladder->related_comp_id2];
			$ladder->teams = $comp1->teams;
			$ladder->minimals = $comp1->minimals;
			$ladder->teams2 = $comp2->teams;
			$ladder->minimals2 = $comp2->minimals;
			$rows[$ladder->id] = $ladder;
		}

		uasort($rows, function($a, $b) {
			$cmp = $a->seq - $b->seq;
			if ($cmp) return $cmp;
			return $a->id - $b->id;
		});

		return $rows;
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
		$result = $wpdb->get_results(
			'SELECT comp_id, sort_order, promoted, relegated_after, where_clause
			FROM slc_division ORDER by comp_id', ARRAY_N);
		if ($wpdb->last_error) return false;
		return $result;
	}

	public static function get_remarks() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT id, name, remarks
			FROM (
				SELECT c.id, CASE WHEN c.group_id = 1 THEN c.section_name
					ELSE c.name END AS name, c.seq
				FROM sl_competition AS c, slc_division AS d
				WHERE c.id = d.comp_id
				UNION ALL
				SELECT c.id, CASE WHEN c.group_id = 1 THEN c.section_name
					ELSE c.name END AS name, c.seq
				FROM sl_competition AS c, slc_cup_draw AS cd
				WHERE cd.round = 1 AND cd.match_num = 1
				AND c.id = cd.comp_id
				) comps
			LEFT JOIN slc_remarks AS r
			ON r.comp_id = comps.id
			ORDER BY seq');
	}

	/**
	 * @return int|false The number of effected rows, or false on error.
	 */
	public static function update_remarks($comp_id, $remarks) {
		global $wpdb;
		if ($remarks) {
			$query = $wpdb->prepare('INSERT INTO slc_remarks (comp_id, remarks) VALUES (%d,%s)
				ON duplicate key update remarks = VALUES(remarks)'
				, $comp_id, $remarks );
			return $wpdb->query($query);
		}
		return $wpdb->delete( 'slc_remarks', [ 'comp_id' => $comp_id ], [ '%d' ] );
	}

	/**
	 * Delete any remarks not in current competitions
	 */
	public static function clean_remarks() {
		global $wpdb;
		return $wpdb->query(
			'DELETE r FROM slc_remarks AS r
			WHERE NOT EXISTS
				(SELECT * FROM slc_division AS d WHERE d.comp_id = r.comp_id)
			AND NOT EXISTS
				(SELECT * FROM slc_cup_draw AS cd
				WHERE cd.round = 1 AND cd.match_num = 1	AND cd.comp_id = r.comp_id)');
	}

	public static function save_divisions_ladders($divisions, $ladders) {
		global $wpdb;
		$result = DB_Util::create_table('new_division',
			'`comp_id` SMALLINT UNSIGNED NOT NULL,
			`sort_order` CHAR(1) NOT NULL,
			`promoted` TINYINT NOT NULL,
			`relegated_after` TINYINT NOT NULL,
			`where_clause` VARCHAR(50) NOT NULL,
			PRIMARY KEY (`comp_id`)');
		if ($result === false) return false;
		foreach ( $divisions as $row ) {
			$values[] = $wpdb->prepare( "(%d,%s,%d,%d,%s)", $row );
		}
		$query = 'INSERT INTO new_division (comp_id, sort_order, promoted, relegated_after, where_clause) VALUES ';
		$query .= implode( ",\n", $values );
		$result = $wpdb->query($query);
		if ($result === false) return false;
		DB_Util::add_table_to_rename('division');

		$result = DB_Util::create_table('new_ladder',
			'`comp_id` SMALLINT UNSIGNED NOT NULL,
			PRIMARY KEY (`comp_id`)');
		if ($result === false) return false;
		if ($ladders) {
			$values = [];
			foreach ( $ladders as $comp ) {
				$values[] = "($comp->id)";
			}
			$query = 'INSERT INTO new_ladder (comp_id) VALUES ';
			$query .= implode( ',', $values );
			$result = $wpdb->query($query);
			if ($result === false) return false;
		}
		DB_Util::add_table_to_rename('ladder');
		return true;
	}
}
