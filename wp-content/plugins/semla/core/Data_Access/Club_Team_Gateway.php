<?php
namespace Semla\Data_Access;
/**
 * Data access for clubs and teams for current fixtures tables
 */
class Club_Team_Gateway {

	/**
	 * @param bool $remove_key_from_object WP returns an associative array,
	 * with the key also in the object. Set to true to remove the key from
	 * the object
	 */
	public static function get_clubs_teams($remove_key_from_object=false) {
		global $wpdb;
		$rows = $wpdb->get_results('SELECT club, club_page,
				GROUP_CONCAT(name ORDER BY name SEPARATOR \'|\') AS teams
			FROM slc_team
			GROUP BY club, club_page
			ORDER BY club', OBJECT_K);
		if ($wpdb->last_error) return false;
		if ($remove_key_from_object) {
			foreach ( $rows as $row ) {
				unset($row->club);
			}
		}
		return $rows;
	}

	public static function get_team_names() {
		global $wpdb;
		$res = $wpdb->get_col('SELECT name FROM slc_team ORDER BY name');
		if ($wpdb->last_error) return false;
		return $res;
	}

	public static function get_teams_for_club($club) {
		global $wpdb;
		$res = $wpdb->get_col($wpdb->prepare(
			'SELECT name FROM slc_team WHERE club = %s',$club));
		if ($wpdb->last_error) return false;
		return $res;
	}

	/**
	 * Get all teams, with their abbreviations and club page
	 * @return array 2 arrays, 'teams' for team->abbrev, and 'team_club' for team->club page
	 */
	public static function get_teams_abbrev_club_page() {
		global $wpdb;
		$rows = $wpdb->get_results('SELECT t.name, COALESCE(ta.abbrev,"") as abbrev, t.club_page
			FROM slc_team AS t
			LEFT JOIN sl_team_abbrev AS ta
			ON ta.team = t.name
			ORDER BY t.name');
		if ($wpdb->last_error) return false;
		$teams = [];
		$team_club = [];
		foreach ( $rows as $row ) {
			$teams[$row->name] = htmlentities($row->abbrev);
			$team_club[$row->name] = $row->club_page;
		}
		return ['team' => $teams, 'team_club' => $team_club];
	}

	public static function get_current_teams_meta() {
		global $wpdb;
		return $wpdb->get_results(
			'SELECT t.name AS team, COALESCE(ta.abbrev,"") as abbrev,
				COALESCE(tm.minimal,"") as minimal
			FROM slc_team AS t
			LEFT JOIN sl_team_abbrev AS ta ON ta.team = t.name
			LEFT JOIN sl_team_minimal AS tm ON tm.team = t.name
			ORDER BY t.name');
	}

	/**
	 * @return string|null|false team name for alias, or false on error
	 */
	public static function team_from_alias($alias) {
		global $wpdb;
		$res = $wpdb->get_var( $wpdb->prepare(
			'SELECT team FROM sl_calendar_team ct WHERE ct.alias = %s
			AND (ct.team = "REMOVED" OR EXISTS (SELECT * FROM slc_team t WHERE t.name = ct.team))'
			, $alias));
		if ($wpdb->last_error) return false;
		return $res;
	}

	/**
	 * @return int|false The number of effected rows, or false on error.
	 */
	public static function update_abbrev($team, $abbrev) {
		global $wpdb;
		if ($abbrev) {
			$query = $wpdb->prepare('INSERT INTO sl_team_abbrev (team, abbrev) VALUES (%s,%s)
				ON duplicate key update abbrev = VALUES(abbrev)'
				, $team, $abbrev );
			return $wpdb->query($query);
		}
		return $wpdb->delete( 'sl_team_abbrev', [ 'team' => $team ] );
	}

	/**
	 * @return int|false The number of effected rows, or false on error.
	 */
	public static function update_minimal($team, $minimal) {
		global $wpdb;
		if ($minimal) {
			$query = $wpdb->prepare('INSERT INTO sl_team_minimal (team, minimal) VALUES (%s,%s)
				ON duplicate key update minimal = VALUES(minimal)'
				, $team, $minimal );
			return $wpdb->query($query);
		}
		return $wpdb->delete( 'sl_team_minimal', [ 'team' => $team ] );
	}

	/**
	 * @return boolean|null whether the team is valid, null on error
	 */
	public static function validate_team($team) {
		global $wpdb;
		$res = $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM slc_team WHERE name = %s', $team));
		if ($res === null) return $res;
		return (bool) $res;
	}

	/**
	 * @return boolean|null whether the club is valid, null on error
	 */
	public static function validate_club($club) {
		global $wpdb;
		$res = $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(*) FROM slc_club WHERE name = %s', $club));
		if ($res === null) return $res;
		return (bool) $res;
	}

	public static function save_teams($rows) {
		global $wpdb;
		$result = DB_Util::create_table('new_team',
			'`name` CHAR(50) NOT NULL,
			`club` VARCHAR(50) NOT NULL,
			`club_page` VARCHAR(50) NOT NULL,
			`pitch_type` VARCHAR(10) NOT NULL,
			PRIMARY KEY (`name`),
			KEY `club_idx` (`club`)');
		if ($result === false) return false;
		foreach ( $rows as $key => $row ) {
			$values[] = $wpdb->prepare( "(%s,%s,%s,%s)", $row );
		}
		$query = 'INSERT INTO new_team (name, club, club_page, pitch_type) VALUES ';
		$query .= implode( ",", $values );
		$result = $wpdb->query($query);
		if ($result === false) return false;
		DB_Util::add_table_to_rename('team');

		$result = DB_Util::create_table('new_club',
			'`name` CHAR(50) NOT NULL,
			`team_count` SMALLINT NOT NULL,
			PRIMARY KEY (`name`)');
	   	if ($result === false) return false;
		$result = $wpdb->query('INSERT INTO new_club (name, team_count)
			SELECT club, COUNT(*) FROM new_team
			GROUP BY club ORDER BY club');
		if ($result === false) return false;
		DB_Util::add_table_to_rename('club');
		return true;
	}
}
