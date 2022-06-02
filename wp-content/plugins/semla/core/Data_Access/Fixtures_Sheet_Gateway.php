<?php
namespace Semla\Data_Access;

use Semla\Cache;
use Semla\Data_Access\Competition_Gateway;
use Semla\Data_Access\Cup_Draw_Gateway;
use Semla\Utils\Net_Util;
use WP_Error;

/**
 * Handle update from Fixtures Google Sheet.
 * Info on how to restrict values/columns etc. can be found at
 *    https://developers.google.com/chart/interactive/docs/querylanguage
 */
class Fixtures_Sheet_Gateway {
	// fixtures columns on sheet
	const COMPETITION = 0;
	const DATE=1;
	const TIME=2;
	const HOME=3;
	const HOME_GOALS=4;
	const V=5;
	const AWAY_GOALS=6;
	const AWAY=7;
	const MULTIPLIER=8;
	const VENUE=9;
	// Divisions columns from sheet
	const LEAGUE=0;
	const DIVISION=1;
	const LEAGUE_DIVISION=2;
	const PROMOTED=3;
	const RELEGATED=4;
	const SORT_ORDER=5;
	const TEAMS=6;
	// Teams page
	const TEAM_MINIMAL=4;
	const TEAM_SHORT=5;

	private $url_base;
	private $tables_file;
	private $error;
	private $status = [];
	private $competitions;
	private $divisions;
	private $max_flags_rounds = 0;
	private	$have_ladder = false;

	/**
	 * Update the fixtures/tables/flags from the Google Sheet
	 * @return array|WP_Error on success return array of update messages, on failure a WP_Error
	 */
	public function update($type) {
		$fixtures_sheet_id = get_option('semla_fixtures_sheet_id');
		if (!$fixtures_sheet_id) {
			return new WP_Error('fixtures_id', 'Cannot update as Sheet ID is not set');
		}

		$fp = fopen(__DIR__ . '/lock.txt', 'w');
		if (! flock($fp, LOCK_EX|LOCK_NB)) {
			return new WP_Error('fixtures_lock', 'Someone else is currently updating the fixtures!');
		}
		$this->url_base = 'https://docs.google.com/spreadsheets/d/'
			. $fixtures_sheet_id . '/gviz/tq?tqx=out:csv&sheet=';
		$this->competitions = Competition_Gateway::get_competitions();
		if ($this->competitions === false) {
			return self::db_error('Failed to load competitions');
		}
		$this->error = new WP_Error();
		$this->tables_file = __DIR__.'/empty-tables.txt';
		if ($type === 'update-all') {
			$this->load_divisions_and_teams();
		} else {
			if (!file_exists($this->tables_file)) {
				$this->status[] = 'Divisions/teams not loaded previously, so loading now';
				$this->load_divisions_and_teams();
			} else {
				$this->tables = unserialize(file_get_contents($this->tables_file));
				$this->divisions = Competition_Gateway::get_division_info();
				if ($this->divisions === false) {
					$this->add_db_error('Failed to load division info');
				}
			}
		}
		if ($this->error->has_errors()) return $this->error;

		$this->load_fixtures_deductions();
		$this->load_flags();
		if ($this->error->has_errors()) return $this->error;

		$res = DB_Util::on_success();
		if (is_wp_error($res)) return $res;

		$ladder_file = __DIR__ . '/ladder.php';
		if ($this->have_ladder) {
			file_put_contents($ladder_file,'<?php $ladder=true;');
		} else {
			@unlink($ladder_file);
		}
		update_option('semla_max_flags_rounds', $this->max_flags_rounds, 'no');
		Cache::clear_cache();

		fclose($fp);
		return $this->status;
	}

	private function load_divisions_and_teams() {
		$division_rows = $this->get_rows('Divisions');
		$team_rows = $this->get_rows('Teams');
		if (!$division_rows) {
			$this->error->add('fixtures', 'No Divisions');
		}
		if (!$team_rows) {
			$this->error->add('fixtures', 'No Teams');
		}
		if ($this->error->has_errors()) return;
		$team_minimals = [];
		$team_abbrevs = [];
		foreach ($team_rows as $key => $row) {
			$team_minimals[] = [$row[0],$row[self::TEAM_MINIMAL]];
			if (!empty($row[self::TEAM_SHORT])) {
				$team_abbrevs[] = [$row[0],$row[self::TEAM_SHORT]];
			}
			$team_rows[$key] = array_splice($row,0,4);
		}
		$tables = [];
		foreach ($division_rows as $row) {
			$competition = $row[self::LEAGUE_DIVISION];
			if (empty($this->competitions[$competition])) {
				$this->error->add('fixtures', 'Division does not exist on the competitions table: ' . $competition);
				continue;
			}
			$comp_id = $this->competitions[$competition]->id;
			$table = [];
			for ($i=self::TEAMS; !empty($row[$i]); $i++) {
				$team = new \stdClass();
				$team->team = $row[$i];
				$team->won=0; $team->drawn=0; $team->lost=0;
				$team->goals_for=0; $team->goals_against=0; $team->points_deducted = 0;
				$team->points=0; $team->divider=0; $team->form='';
				$table[$team->team] = $team;
			}
			$tables[$comp_id] = $table;

			if ($row[self::RELEGATED]) {
				$relegated_after = count($table) - $row[self::RELEGATED];
			} else {
				$relegated_after = 0;
			}

			$divisions[] = [$comp_id, $row[self::SORT_ORDER], (int) $row[self::PROMOTED],
				$relegated_after];
		}
		if ($this->error->has_errors()) return;
		if (!Competition_Gateway::save_divisions($divisions)) {
			$this->add_db_error('Failed to save divisions');
			return;
		}
		$this->status[] = 'Loaded info for ' . count($division_rows) . ' divisions';
		$this->divisions = $divisions;

		if (!Club_Team_Gateway::save_teams($team_rows)) {
			$this->add_db_error('Failed to save teams');
			return;
		}
		$this->status[] = 'Loaded info for ' . count($team_rows) . ' teams';
		$affected = Club_Team_Gateway::save_team_minimals($team_minimals);
		if ($affected === false) {
			$this->add_db_error('Failed to save team minimal names');
			return;
		}
		if ($affected) {
			$this->status[] = 'Team minimal names updated';
		}
		$affected = Club_Team_Gateway::save_team_abbrevs($team_abbrevs);
		if ($affected === false) {
			$this->add_db_error('Failed to save team abbreviations');
			return;
		}
		if ($affected) {
			$this->status[] = 'Team name abbreviations updated';
		}

		file_put_contents($this->tables_file, serialize($tables));
		$this->tables = $tables;
	}

	private function load_fixtures_deductions() {
		$rows = $this->get_rows('Deductions');
		if ($rows === null) {
			return;
		}
		// next 3 lines for testing
		$deductions=[];
		foreach($rows as $row) {
			$comp = $row[0];
			if (empty($this->competitions[$comp])) {
				$this->error[] = "Error: Unknown league/division '$comp' in Deductions";
			} else {
				$comp_id = $this->competitions[$comp]->id;
				$deductions[] = [ $comp_id,$row[1],$row[2],$row[3],$row[4]];
				$points_deducted = $row[2];
				$team = $this->tables[$comp_id][$row[1]];
				$team->points -= $points_deducted;
				$team->points_deducted += $points_deducted;
			}
		}
		if ($this->error->has_errors()) return;
		if (!Table_Gateway::save_deductions($deductions)) {
			$this->add_db_error('Failed to save Deductions');
			return;
		}
		$this->status[] = 'Loaded ' . count($rows) . ' deductions';

		$rows = $this->get_rows("Fixtures&tq=select+C,D,E,F,G,H,I,J,K,M+where+(H='v'+or+H='C'+or+H='c'+or+H='C24'+or+H='c24')+and+F!='Bye'+and+J!='Bye'+and+(F!=''+or+J!='')");
		if (!$rows) {
			return;
		}

		$unknown_seq = 0;
		foreach ($this->competitions as $comp) {
			if ($comp->seq > $unknown_seq) $unknown_seq = $comp->seq;
		}
		$unknown_seq++;
		$this->competitions['Friendly'] = (object)
			 ['id'=>0,'abbrev'=>'Friendly','type'=>'Fr','seq'=>$unknown_seq++];

		$this->points = get_option('semla_points');
		if (!$this->points) {
			$this->error->add('fixtures', 'Point values not set up. Please do that on the SEMLA Settings page.');
			return;
		}

		// put sort order for each division into indexed array
		$division_sort = [];
		foreach ($this->divisions as $division) {
			$division_sort[$division[0]] = $division[1]; // comp_id->sort_order
		}

		// make sure we have increasing dates, otherwise someone has forgotten to change
		// the date when copying a row
		$last_date = '';
		$fixtures = $indexed_fixtures = [];
		foreach ($rows as $row) {
			for ($i = 9; $i >= 0; $i-- ) {
				$row[$i] = trim($row[$i]);
			}
			$date = $this->convert_date($row[self::DATE]);
			$competition = $row[self::COMPETITION];

			$time = $row[self::TIME] ? date('H:i:s', strtotime($row[self::TIME])) : '14:00:00';
			$home = empty($row[self::HOME]) ? 'TBD' : $row[self::HOME];
			$away = empty($row[self::AWAY]) ? 'TBD' : $row[self::AWAY];
			if ($date < $last_date) {
				$this->error->add('fixtures', "$date $home v $away, date is out of sequence, last date was $last_date");
				continue;
			}
			$last_date = $date;

			if (empty($row[self::VENUE])
			|| preg_match('/' . $row[self::VENUE] . '/',$row[self::HOME])) {
				$venue = null;
			} else {
				$venue = $row[self::VENUE];
			}
			$multi = empty($row[self::MULTIPLIER]) ? 1 : $row[self::MULTIPLIER];
			$seq = $unknown_seq;
			$comps = explode('/',$competition);
			$comps_count = count($comps);
			if ($comps_count > 2) {
				$this->error->add('fixtures', "More than 2 competitions on 1 line '$competition' in fixtures");
			} elseif ($comps_count === 2) {
				$this->have_ladder = true;
			}
			$comp_id = $comp_id2 = 0;
			$comp_short = '';
			$is_league = false;
			foreach ($comps as $comp) {
				if (isset($this->competitions[$comp])) {
					$c = $this->competitions[$comp];
					if ($c->seq < $seq)
						$seq = $c->seq;
					$comp_short .= ($comp_short ? '/' : '') . ($c->abbrev ? $c->abbrev : $c->name);
					if (!$comp_id) {
						$comp_id = $c->id; 
					} else {
						$comp_id2 = $c->id; 
					}
					if ($c->type === 'league' || $c->type === 'league-prelim') $is_league = true;
				} else {
					$temp = explode(' ', $comp);
					if (count($temp) < 2) {
						$this->status[] = "Warning: Unknown competition '$comp' in fixtures";
						$comp_short .= ($comp_short ? '/' : '') . $comp;
						continue;
					}
					$round = array_pop($temp); // get rid of flags round
					$comp2 = implode(' ', $temp);
					if (empty($this->competitions[$comp2])) {
						$this->status[] = "Warning: Unknown competition '$comp' in fixtures";
						$comp_short .= ($comp_short ? '/' : '') . $comp;
						continue;
					}
					$c = $this->competitions[$comp2];
					if ($c->seq < $seq)
						$seq = $c->seq;
					$comp_short .= ($comp_short ? '/' : '') . ($c->abbrev ? $c->abbrev : $c->name) . ' ' . $round;
					if (!$comp_id) {
						$comp_id = $c->id; 
					} else {
						$comp_id2 = $c->id; 
					}
					if ($c->type === 'league' || $c->type === 'league-prelim') $is_league = true;
				}
			}
			$h_goals = $row[self::HOME_GOALS] == '' ? null : $row[self::HOME_GOALS];
			$a_goals = $row[self::AWAY_GOALS] == '' ? null : $row[self::AWAY_GOALS];
			$result = '';
			$h_points = $a_points = null;
			if (is_numeric($h_goals) && is_numeric($a_goals)) {
				$penalty = $row[self::V][0] == 'C';
				$result = sprintf('%d - %d', $h_goals, $a_goals)
					. ($penalty ? ' w/o' : '');
				if ($is_league) {
					// cater for ladders - most of the time it won't be
					if ($comp_id2 == 0) {
						$h_comp_id = $a_comp_id = $comp_id;
					} elseif (isset($this->tables[$comp_id][$home])) {
						$h_comp_id = $comp_id;
						$a_comp_id = $comp_id2;
					} else {
						$h_comp_id = $comp_id2;
						$a_comp_id = $comp_id;
					}
					if (!isset($this->tables[$h_comp_id][$home])) {
						$this->error->add('fixtures', "$date $home v $away, $home is not in division $competition");
						continue;
					}
					if (!isset($this->tables[$a_comp_id][$away])) {
						$this->error->add('fixtures', "$date $home v $away, $away is not in division $competition");
						continue;
					}
					$h = $this->tables[$h_comp_id][$home];
					$a = $this->tables[$a_comp_id][$away];
					$h->goals_for += ($h_goals * $multi);
					$h->goals_against += ($a_goals * $multi);
					$a->goals_for += ($a_goals * $multi);
					$a->goals_against += ($h_goals * $multi);
					if ($penalty) {
						if ($h_goals > $a_goals) {
							$h->won += $multi;
							$h->form .= 'W';
							$a->lost += $multi;
							$a->form .= 'L';
							$h_points = $this->points['W'];
							$a_points = $this->points[$row[self::V]];
						} else {
							$h->lost += $multi;
							$h->form .= 'L';
							$a->won += $multi;
							$a->form .= 'W';
							$h_points = $this->points[$row[self::V]];
							$a_points = $this->points['W'];
						}
					} else {
						if ($h_goals > $a_goals) {
							$h->won += $multi;
							$h->form .= 'W';
							$a->lost += $multi;
							$a->form .= 'L';
							$h_points = $this->points['W'];
							$a_points = $this->points['L'];
						} elseif ($h_goals < $a_goals) {
							$h->lost += $multi;
							$h->form .= 'L';
							$a->won += $multi;
							$a->form .= 'W';
							$h_points = $this->points['L'];
							$a_points = $this->points['W'];
						} else {
							$h->drawn += $multi;
							$h->form .='D';
							$a->drawn += $multi;
							$a->form .= 'D';
							$h_points = $this->points['D'];
							$a_points = $this->points['D'];
						}
					}
					$h->points += ($h_points * $multi);
					$a->points += ($a_points * $multi);
					// don't store ladder fixtures in indexed array as that is used to check winners/promotion/relegation
					// by record between teams
					if ($h_comp_id === $a_comp_id) {
						$indexed_fixtures["$comp_id|$home|$away"] = [$h_points, $a_points, $h_goals, $a_goals];
					}
				}
			} else {
				switch ($h_goals) {
					case 'V':
						$result = 'Void';
						break;
					case 'C':
						$result = 'Cancelled';
						break;
					case 'A':
						$result = 'Abandoned';
						break;
					default: 
						if ($h_goals) $result = "$h_goals - $a_goals";
				}
				$h_goals = $a_goals = null;
			}
			$fixture = [$date,$time,$comp_id,$comp_id2,
				$comp_short,$home,$away,$h_goals,$a_goals,$venue,$result,$h_points,$a_points,
				$multi,'sort' => $seq];
			if (isset($division_sort[$comp_id]) && $division_sort[$comp_id] === 'V') { // if we want to sort by venue/time
				$fixture['sort2'] = ($venue ?? $home) . $time;
			}
			$fixtures[] = $fixture;
		}
		if ($this->error->has_errors()) return;

		uasort($fixtures, function($a, $b) {
			$cmp = strcmp($a[0],$b[0]); // date
			if ($cmp) return $cmp;
			$cmp = $a['sort'] - $b['sort']; // competition sequence #
			if ($cmp) return $cmp;
			if (isset($a['sort2'])) { // if we want to sort by venue/time
				$cmp = strcmp($a['sort2'], $b['sort2']);
				if ($cmp) return $cmp;
			}
			$cmp = strcmp($a[5], $b[5]); // home
			if ($cmp) return $cmp;
			return strcmp($a[6], $b[6]); // away
		});
		if (!Fixtures_Gateway::save_fixtures($fixtures, $this->have_ladder)) {
			$this->add_db_error('Failed to save fixtures');
			return;
		}
		$this->status[] = 'Loaded ' . count($fixtures) . ' fixtures'
			. ($this->have_ladder ? ' - ladder fixtures found' : '');

		// Now to tables
		foreach ($this->divisions as $division) {
			$comp_id = $division[0];
			$promoted = $division[2];
			$relegated_after = $division[3];
			if (empty($this->tables[$comp_id])) {
				$this->error->add('fixtures',
					"Unknown division comp_id $comp_id. You probably need to 'Update all'.");
				continue;
			}
			$table = $this->tables[$comp_id];
			foreach ($table as $team) {
				$team->played = $team->won + $team->drawn + $team->lost;
				if ($team->goals_against > 0) {
					$team->goal_avg = $team->goals_for / $team->goals_against;
				} elseif ($team->goals_for == 0) {
					$team->goal_avg = 0;
				} else {
					$team->goal_avg = 99;
				}
			}
			uasort($table, [$this, 'cmp_tables']);
			// TODO reorder!
			$table = array_values($table);
			$pos = 1;
			foreach ($table as $team) {
				$team->position = $pos++;
			}
			if ($promoted != 0) {
				$table[$promoted - 1]->divider = 1;
			}
			if ($relegated_after != 0) {
				$table[$relegated_after - 1]->divider = 1;
			}
			$this->tables[$comp_id] = $table;
		}
		if (!Table_Gateway::save_tables($this->tables)) {
			$this->add_db_error('Failed to save tables');
			return;
		}
		$this->status[] = 'Created ' . count($this->tables) . ' league tables';
	}

	/**
	 * Compare 2 rows in league tables tables
	 */
	private function cmp_tables($a, $b) {
		$cmp = $b->points - $a->points;
		if ($cmp) return $cmp;
		$cmp = $b->goal_avg - $a->goal_avg;
		if ($cmp) return $cmp;
		$cmp = strcmp($a->team, $b->team);
	}

	private function load_flags() {
		$rows = $this->get_rows('Flags', true, false);
		if (!$rows) return;

		$row_count = count($rows);
		if ($row_count < 5) return;

        $ha = ['H'=>1,'A'=>2,'NH'=>3,'NA'=>4];
        $matches = $round_dates = [];
		$row = $count = 0;
		while ($row < $row_count) {
			// title
			$comp = $rows[$row][2];
			if (empty($this->competitions[$comp]) || ($this->competitions[$comp]->type != 'cup')) {
				$this->error->add('fixtures', "Unknown flags competition $comp");
				$comp_id = 0;
			} else {
				$comp_id = $this->competitions[$comp]->id;
			}
			$row++;
            if ($row + 3 > $row_count) {
                $this->error->add('fixtures', "Too few rows in flags competition $comp");
                return;
            }
			//dates
            $cur_row = $rows[$row];
			$col = 2;
			$round_cnt = 0;
			while (!empty($cur_row[$col])) {
				$round_cnt++;
                $round_dates[] = [ $comp_id, $round_cnt, $this->convert_date($cur_row[$col]) ];
				$col += 5;
            }
			if ($round_cnt > $this->max_flags_rounds) {
				$this->max_flags_rounds = $round_cnt;
			}
            $r1_matches = 2**($round_cnt-1);
            $start_row = $row + 2;
            $end_row = $start_row + ($r1_matches * 2) - 1;
            if ($end_row > $row_count) {
                $this->error->add('fixtures', "Too few rows in flags competition $comp");
                return;
			}
            $col = 1; // h/a
            $round = 1;
            while ($round < $round_cnt) {
				$num = 0;
				$team1 = false;
                for ($row = $start_row; $row<= $end_row; $row++) {
                    if (!empty($rows[$row][$col])) {
                        if ($team1 === false) { // home
                            $home_team = $ha[ $rows[$row][$col] ];
                            $team1 = $rows[$row][$col+1];
                            $team1_goals = $rows[$row][$col+2];
                        } else {
							$matches[] = [$comp_id, $round, ++$num,
								$team1, $rows[$row][$col+1], $team1_goals,
								$rows[$row][$col+2], $home_team ];
							$team1 = false;
                        }
                    }
                }
                $col +=5;
                $round++;
            }
            // final round - no h/a
			$col ++;
			$team1 = $team2 = '';
			$team1_goals = $team2_goals = null;
            for ($row = $start_row; $row<= $end_row; $row++) {
                if (!empty($rows[$row][$col])) {
                    if ($team1) {
                        $team2 = $rows[$row][$col];
                        $team2_goals = $rows[$row][$col+1];
                        break;
                    }
                    $team1 = $rows[$row][$col];
                    $team1_goals = $rows[$row][$col+1];
                }
			}
			$matches[] = [$comp_id, $round, 1,
				$team1, $team2, $team1_goals, $team2_goals, 0 ];
			$row = $end_row + 1;
			$count++;
		}
		if ($this->error->has_errors()) return;
		if (!Cup_Draw_Gateway::save_current($matches, $round_dates)) {
			$this->add_db_error('Failed to save Flags draws');
			return;
		}
		$this->status[] = $count . ' Flags competitions updated';
	}

	/**
	 * retrieve rows from spreadsheet sheet, ignoring header row
	 * 
	 * @param string $sheet sheet to return rows from, may include query params 
	 * @param boolean $parse_csv if true will parse the rows into an array of elements,
	 * 		otherwise just return the rows
	 * @return array of rows as a string or parses csv
	 */
	private function get_rows($sheet, $parse_csv = true, $remove_header = true) {
		$csv = Net_Util::get_url($this->url_base . $sheet, false);
		if (is_wp_error(($csv))) {
			$this->error->add( $csv->get_error_code(),
				 $csv->get_error_message() . ' (check the Google Sheet is shared so everyone can view)');
			return null;
		}
		$rows = explode( "\n", $csv );
		if ($remove_header) {
			array_shift($rows); // get rid of header
		}
		if (!$parse_csv) return $rows;
		return array_map('str_getcsv', $rows );
	}

	private function convert_date($in_date) {
		$date = explode('/',$in_date);
		if (count($date) < 3) {
			$this->error->add('fixtures', "invalid date found $in_date");
			return $in_date;
		}
        return $date[2] .'-' . str_pad($date[1],2,'0',STR_PAD_LEFT) . '-' . str_pad($date[0],2,'0',STR_PAD_LEFT);
    }

	/**
	 * Revert the last fixtures update to the backup tables.
	 * @return string|WP_Error on success return string with message, on failure a WP_Error
	 */
	public static function revert() {
        global $wpdb;
		$fp = fopen(__DIR__ . '/lock.txt', 'w');
		if (! flock($fp, LOCK_EX|LOCK_NB)) {
			return new WP_Error('fixtures_lock', 'Someone else is currently updating the fixtures!');
		}

		$tables = ['cup_draw', 'cup_round_date', 'deduction', 'fixture', 'fixture_date', 'table'];
		
        $tables_count = $wpdb->get_var(
            'SELECT COUNT(*) FROM information_schema.TABLES 
			WHERE TABLE_CATALOG = "def" AND TABLE_SCHEMA = "' . DB_NAME . '"
				AND TABLE_TYPE = "BASE TABLE"
				AND TABLE_NAME IN ("backup_' . implode('", "backup_', $tables). '")');
        if ($tables_count === null) {
			return self::db_error('Failed to count backup tables');
		}
		if ($tables_count !== '6') {
			return new WP_Error('fixtures', 'Not all backup tables exist, cannot revert');
		}
		$rows = $wpdb->get_results(
			'SELECT TABLE_NAME FROM information_schema.TABLES 
			WHERE TABLE_CATALOG = "def" AND TABLE_SCHEMA = "' . DB_NAME . '"
				AND TABLE_TYPE = "BASE TABLE"
				AND TABLE_NAME LIKE "slc_%"' );
		if ($wpdb->last_error) {
			return self::db_error('Failed to list current tables');
		}
		$slc_tables = [];
		foreach ($rows as $row) {
			$slc_tables[$row->TABLE_NAME] = 1;
		}
		$renames = [];
		foreach ($tables as $table) {
			$slc_table = "slc_$table";
			if (array_key_exists($slc_table, $slc_tables)) {
				$renames[] = "$slc_table TO new_$table";
			}
			$renames[] = "backup_$table TO slc_$table";
		}
		$result = $wpdb->query('RENAME TABLE ' . implode(', ' , $renames));
		if ($result === false) {
			return self::db_error('Failed to rename tables');
		}
		$result = $wpdb->query('DROP TABLE IF EXISTS new_' 
			. implode(', new_', $tables));
		if ($result === false) {
			return self::db_error('Failed to drop replaced tables');
		}
		Cache::clear_cache();

		fclose($fp);
		return 'Fixtures and flags reverted.';
	}

	private function add_db_error($message) {
		global $wpdb;
		$this->error->add('fixtures', "$message. SQL error: $wpdb->last_error");
	}
	private static function db_error($message) {
		global $wpdb;
		return new WP_Error('fixtures', "$message. SQL error: $wpdb->last_error");
	}
}
