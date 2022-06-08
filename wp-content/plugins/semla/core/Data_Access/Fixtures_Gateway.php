<?php
namespace Semla\Data_Access;
/**
 * Data access for the fixtures & results
 */
class Fixtures_Gateway {
	/** Return html for fixtures grid */
	public static function get_grid($year, $league_id) {
		global $wpdb;
		
		$divisions = Competition_Gateway::get_divisions($year, $league_id);
		if ($wpdb->last_error) return DB_Util::db_error();
		if (!$divisions) return;
		foreach ($divisions as $division) {
			$comp_ids[] = $division->comp_id;
		}
		$comp_ids = implode(',',$comp_ids);
		if ($year === 0) {
			$ladder = false;
			@include __DIR__ . '/ladder.php';
			$rows = $wpdb->get_results(
				"SELECT CONCAT(comp_id,'|',home,'|',away) AS comp_ha, match_date, result, points_multi
					FROM slc_fixture
					WHERE comp_id IN ($comp_ids)"
					. ($ladder ? " OR comp_id2 IN ($comp_ids)" : ''));
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT CONCAT(comp_id,'|',home,'|',away) AS comp_ha, match_date, result, points_multi
					FROM slh_result
					WHERE year = %d AND comp_id IN ($comp_ids)", $year));
				// . ($ladder ? " OR comp_id2 IN ($comp_ids)" : ''));
		}
		if ($wpdb->last_error) return DB_Util::db_error();

		$fixtures = [];
		$postponed_fixtures = [];
		foreach ($rows as $row) {
			if ($row->result && strpos('RA',substr($row->result,0,1)) !== false) {
				$postponed_fixtures[$row->comp_ha] = $row->result;
			} else {
				$fixtures[$row->comp_ha][] = $row;
			}
		}

		ob_start();
		require __DIR__ . '/views/fixtures-grid.php';
		return ob_get_clean();
	}
	
	public static function save_fixtures($rows, $ladder) {
		global $wpdb;

		$result = DB_Util::create_table('new_fixture',
			'`id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
			`match_date` DATE NOT NULL,
			`match_time` TIME NOT NULL,
			`comp_id` SMALLINT UNSIGNED NOT NULL,
			`comp_id2` SMALLINT UNSIGNED NOT NULL,
			`competition` VARCHAR(40) NOT NULL,
			`home` VARCHAR(50) NOT NULL,
			`away` VARCHAR(50) NOT NULL,
			`home_goals` TINYINT,
			`away_goals` TINYINT,
			`venue` VARCHAR(50),
			`result` VARCHAR(20) NOT NULL,
			`home_points` TINYINT,
			`away_points` TINYINT,
			`points_multi` TINYINT NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY `date_id` (`match_date`, `id`)'
			. ($ladder ? '' : ', UNIQUE KEY `comp_idx` (`comp_id`,`id`)'));
		if ($result === false) return false;
		foreach ( $rows as $key => $row ) {
			unset($row['sort']);
			unset($row['sort2']);
			$values[] = $wpdb->prepare( '(%s,%s,%d,%d,%s,%s,%s,', array_slice($row,0,7))
				. ($row[7] === null ? 'null' : $row[7]) . ','
				. ($row[8] === null ? 'null' : $row[8]) . ','
				. ($row[9] === null ? 'null' : $wpdb->prepare( '%s', $row[9]))
				. $wpdb->prepare( ',%s,', $row[10])
				. ($row[11] === null ? 'null' : $row[11]) . ','
				. ($row[12] === null ? 'null' : $row[12])
				. ",$row[13])";
		}
		$query = 'INSERT INTO new_fixture (match_date, match_time, comp_id, comp_id2, competition, home, away,
			home_goals, away_goals, venue, result, home_points, away_points, points_multi) VALUES ';
		$query .= implode( ",\n", $values );
		$result = $wpdb->query($query);
		if ($result === false) return false;
		DB_Util::add_table_to_rename('fixture');

		// add a table of all the dates
		$result = DB_Util::create_table('new_fixture_date',
			'`match_date` DATE NOT NULL,
			PRIMARY KEY (match_date)');
		if ($result === false) return false;
		$result = $wpdb->query('INSERT INTO `new_fixture_date` (`match_date`)
			SELECT DISTINCT match_date FROM new_fixture');
		if ($result === false) return false;
		DB_Util::add_table_to_rename('fixture_date');
		return true;
	}
}
