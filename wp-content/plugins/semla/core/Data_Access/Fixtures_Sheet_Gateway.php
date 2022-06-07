<?php
namespace Semla\Data_Access;
/**
 * Update current database tables from the Fixtures Google Sheet.
 */
use Semla\Cache;
use Semla\Data_Access\Competition_Gateway;
use Semla\Data_Access\Cup_Draw_Gateway;
use Semla\Utils\Net_Util;
use WP_Error;

class Fixtures_Sheet_Gateway {
	// Divisions columns from sheet
	const LEAGUE=0;
	const DIVISION=1;
	const LEAGUE_DIVISION=2;
	const PROMOTED=3;
	const RELEGATED=4;
	const SORT_ORDER=5;
	const TEAMS=6;
	// Teams page
	const TEAM_NAME=0;
	const TEAM_MINIMAL=4;
	const TEAM_SHORT=5;
	// Deductions
	const DEDUCT_COMPETITION = 0;
	const DEDUCT_POINTS = 2;

	private $xlsx;
	private $sheet_names;
	private $tables_file;
	private $error;
	private $status = [];
	private $competitions;
	private $competition_by_id = null;
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

		$res = $this->fetch_google_sheet($fixtures_sheet_id);
		if (is_wp_error($res)) return $res;

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
		if (!$division_rows) {
			$this->error->add('fixtures', 'No Divisions');
		}
		$team_rows = $this->get_rows('Teams');
		if (!$team_rows) {
			$this->error->add('fixtures', 'No Teams');
		}
		if ($this->error->has_errors()) return;
		$team_minimals = [];
		$team_abbrevs = [];
		foreach ($team_rows as $key => $row) {
			$team_minimals[] = [$row[self::TEAM_NAME],$row[self::TEAM_MINIMAL]];
			if (!empty($row[self::TEAM_SHORT])) {
				$team_abbrevs[] = [$row[self::TEAM_NAME],$row[self::TEAM_SHORT]];
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
				$team->points=0; $team->divider=0; $team->form=''; $team->tiebreaker = 0;
				$table[$team->team] = $team;
			}
			$tables[$comp_id] = $table;

			if ($row[self::RELEGATED]) {
				$relegated_after = count($table) - $row[self::RELEGATED];
			} else {
				$relegated_after = 0;
			}

			$divisions[] = [$comp_id, $row[self::SORT_ORDER][0], (int) $row[self::PROMOTED],
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
		if ($affected) { // $affected is 2 per update, 1 per insert - so don't put in message
			$this->status[] = 'Team minimal names updated';
		}

		if (count($team_abbrevs) > 0) {
			$affected = Club_Team_Gateway::save_team_abbrevs($team_abbrevs);
			if ($affected === false) {
				$this->add_db_error('Failed to save team abbreviations');
				return;
			}
			if ($affected) { // $affected is 2 per update, 1 per insert - so don't put in message
				$this->status[] = 'Team name abbreviations updated';
			}
		}

		file_put_contents($this->tables_file, serialize($tables));
		$this->tables = $tables;
	}

	private function load_fixtures_deductions() {
		$rows = $this->get_rows('Deductions');
		if ($rows === null) {
			return;
		}
		$deductions=[];
		foreach($rows as $row) {
			$comp = $row[self::DEDUCT_COMPETITION];
			if (empty($this->competitions[$comp])) {
				$this->error->add('fixtures_deduct', "Unknown league/division '$comp' in Deductions");
				continue;
			}
			$comp_id = $this->competitions[$comp]->id;
			$points_deducted = trim($row[self::DEDUCT_POINTS]);
			if (!is_numeric($points_deducted)) {
				$this->error->add('fixtures_deduct', "Deduction $points_deducted is not numeric");
				continue;
			}
			$points_deducted = (float)$points_deducted;
			if ($points_deducted <= 0 || $points_deducted > 10
			|| $points_deducted !== round($points_deducted,1)) {
				$this->error->add('fixtures_deduct', "Deduction $points_deducted is invalid (must be > 0, <= 10, max 1 decimal place)");
				continue;
			}
			$deductions[] = [$comp_id,$row[1],$points_deducted,$row[3],$row[4]];
			$team = $this->tables[$comp_id][$row[1]];
			$team->points -= $points_deducted;
			$team->points_deducted += $points_deducted;
		}
		if ($this->error->has_errors()) return;
		if (!Table_Gateway::save_deductions($deductions)) {
			$this->add_db_error('Failed to save Deductions');
			return;
		}
		$this->status[] = 'Loaded ' . count($rows) . ' deductions';

		$rows = $this->get_rows('Fixtures', false);
		if (!$rows || count($rows) === 1) {
			return;
		}

		$row0 = $rows[0];
		unset($rows[0]);
		$headings = ['Competition' => 1, 'Date' => 1, 'Time' => 1, 'Home' => 1,
			'Home Goals' => 1, 'v' => 1, 'Away Goals' => 1, 'Away' => 1, 'X' => 1,
			'Venue' => 1];
		// find all the headings and set _col variables accordingly
		for ($i = count($row0) - 1; $i >= 0; $i--) {
			if (isset($headings[$row0[$i]])) {
				$var = str_replace(' ', '_',strtolower($row0[$i])) . '_col';
				$$var = $i;
				unset($headings[$row0[$i]]);
			}
		}
		if (count($headings) > 0) {
			$this->error->add('fixtures', 'Missing headings on Fixtures sheet: '
				. implode(', ', array_keys($headings)));
			return;
		}

		$unknown_seq = 0;
		foreach ($this->competitions as $comp) {
			if ($comp->seq > $unknown_seq) $unknown_seq = $comp->seq;
		}
		$unknown_seq++;
		$this->competitions['Friendly'] = (object)
			 ['id'=>0,'name'=>'Friendly','abbrev'=>'Friendly','type'=>'Fr','seq'=>$unknown_seq++];

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
		$fixtures = $division_fixtures = [];
		foreach ($rows as $row_no => $row) {
			$row_no++;
			$v = trim($row[$v_col]);
			if ($v === '') continue;
			$date = $row[$date_col];
			if (is_int($date)) {
				$this->error->add('fixtures', "Date is parsed as an integer. Format issues? Fixtures row $row_no: $date");
				continue;
			}
			if ($date === '') continue;
			if (strlen($date) !== 19) {
				$this->error->add('fixtures', "Invalid date format on Fixtures row $row_no: $date");
				continue;
			}

			$home = trim($row[$home_col]);
			$away = trim($row[$away_col]);
			if ( ($home === '' && $away === '') || $home === 'Bye' || $away === 'Bye') {
				continue;
			}

			// flags games may have only 1 team into the round, so mark the team as TBD
			if ($home === '') $home = 'TBD';
			if ($away === '') $away = 'TBD';

			$date = substr($date, 0, 10); // cell will have date/time, so need to remove time part
			$competition = trim($row[$competition_col]);

			$time = $row[$time_col];
			if (is_int($time)) {
				$this->error->add('fixtures', "Time is parsed as an integer. Format issues? Fixtures row $row_no: $time");
				continue;
			}
			if ($time === '') {
				$time = '14:00:00';
			} else {
				if (strlen($time) !== 19) {
					$this->error->add('fixtures', "Invalid time format on Fixtures row $row_no: $time");
					continue;
				}
				$time = substr($time, 11); // cell will have date/time
			}
			if ($date < $last_date) {
				$this->error->add('fixtures', "Fixtures row $row_no is out of sequence, date is $date, last date was $last_date");
				continue;
			}
			$last_date = $date;

			$venue = trim($row[$venue_col]);
			if ($venue === '' || preg_match('/' . $venue . '/', $home)) {
				$venue = null;
			}
			$multi = empty($row[$x_col]) ? 1 : $row[$x_col];
			$seq = $unknown_seq;
			$comps = explode('/',$competition);
			$comps_count = count($comps);
			if ($comps_count > 2) {
				$this->error->add('fixtures', "More than 2 competitions on 1 line '$competition' in fixtures row $row_no");
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
						$this->status[] = "Warning: Unknown competition '$comp' in fixtures row $row_no";
						$comp_short .= ($comp_short ? '/' : '') . $comp;
						continue;
					}
					$round = array_pop($temp); // get rid of flags round
					$comp2 = implode(' ', $temp);
					if (empty($this->competitions[$comp2])) {
						$this->status[] = "Warning: Unknown competition '$comp' in fixtures row $row_no";
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

			$h_goals = $this->parse_goals($row[$home_goals_col]);
			$a_goals = $this->parse_goals($row[$away_goals_col]);
			$result = '';
			$h_points = $a_points = null;
			if (is_int($h_goals) && is_int($a_goals)) {
				$penalty = $v[0] === 'C';
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
						$this->error->add('fixtures', "$date $home v $away, $home is not in division $competition, row $row_no");
						continue;
					}
					if (!isset($this->tables[$a_comp_id][$away])) {
						$this->error->add('fixtures', "$date $home v $away, $away is not in division $competition,  row $row_no");
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
							$a_points = $this->points[$v];
							$h_win = $multi;
							$a_win = 0;
						} else {
							$h->lost += $multi;
							$h->form .= 'L';
							$a->won += $multi;
							$a->form .= 'W';
							$h_points = $this->points[$v];
							$a_points = $this->points['W'];
							$h_win = 0;
							$a_win = $multi;
						}
					} else {
						if ($h_goals > $a_goals) {
							$h->won += $multi;
							$h->form .= 'W';
							$a->lost += $multi;
							$a->form .= 'L';
							$h_points = $this->points['W'];
							$a_points = $this->points['L'];
							$h_win = $multi;
							$a_win = 0;
						} elseif ($h_goals < $a_goals) {
							$h->lost += $multi;
							$h->form .= 'L';
							$a->won += $multi;
							$a->form .= 'W';
							$h_points = $this->points['L'];
							$a_points = $this->points['W'];
							$h_win = 0;
							$a_win = $multi;
						} else {
							$h->drawn += $multi;
							$h->form .='D';
							$a->drawn += $multi;
							$a->form .= 'D';
							$h_points = $this->points['D'];
							$a_points = $this->points['D'];
							$h_win = $a_win = 0;
						}
					}
					$h->points += ($h_points * $multi);
					$a->points += ($a_points * $multi);
					// don't store ladder fixtures in division fixtures as that is used to check winners/promotion/relegation
					// by record between teams inside a division
					if ($h_comp_id === $a_comp_id) {
						$division_fixtures[$comp_id][] = ['h' => $home, 'a' => $away,
							'h_data' => ['points' => $h_points, 'goal_diff'=> $h_goals - $a_goals, 'win' => $h_win],
							'a_data' => ['points' => $a_points, 'goal_diff'=> $a_goals - $h_goals, 'win' => $a_win],
						];
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

		usort($fixtures, function($a, $b) {
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
		if (!Tiebreaker_Gateway::create_table()) {
			$this->add_db_error('Failed to create tiebreaker table');
			return;
		}
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
			usort($table, [$this, 'cmp_tables']);
			
			if ($table[0]->played > 0) {
				if ($table[0]->points === $table[1]->points) {
					// top = 2nd, order by head-to-head
					$this->tiebreaker_reorder($comp_id, $table, 0, $division_fixtures[$comp_id]);
					if ($this->error->has_errors()) return;
				}
				if ($promoted != 0 && $table[$promoted]->points > 0
				&& $table[$promoted]->points === $table[$promoted-1]->points
				// check we haven't already done a tie-break for champions
				&& $table[$promoted]->points !== $table[0]->points) {
					$this->tiebreaker_reorder($comp_id, $table, $promoted - 1, $division_fixtures[$comp_id]);
					if ($this->error->has_errors()) return;
				}
				if ($relegated_after != 0 && $table[$relegated_after]->points > 0
				&& $table[$relegated_after]->points === $table[$relegated_after-1]->points
				// check we haven't already done a tie-break which could have gone this far
				&& $table[$relegated_after]->points !== $table[$promoted]->points) {
					$this->tiebreaker_reorder($comp_id, $table, $relegated_after - 1, $division_fixtures[$comp_id]);
					if ($this->error->has_errors()) return;
				}
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
	 * Goals could be int, text, or an int as a text field, so coerce into correct format
	 */
	private function parse_goals($goals) {
		if (is_int($goals)) return $goals;
		$goals = trim($goals);
		if ($goals === '') return null;
		if (is_numeric($goals)) return (int)$goals;
		return $goals;
	}

	/**
	 * Reorder table for those equal on points into head to head order
	 * Also flag if teams have same points etc.
	 */
	private function tiebreaker_reorder($comp_id, &$table, $row, $fixtures) {
		$tiebreakers = [];
		$size = count($table);
		$points = $table[$row]->points;
		$start = $row;
		for ($i = $row - 1; $i >= 0 && $table[$i]->points === $points; $i--) {
			$start = $i;
		}
		for ($i = $start; $i < $size && $table[$i]->points === $points; $i++) {
			$tiebreakers[$table[$i]->team] =
				['points' => 0, 'goal_diff' => 0, 'wins' => 0, 'position' => $i + 1, 'row' => $table[$i]];
		}
		// calculate the head2head record for all teams on the same points
		foreach ($fixtures as $fixture) {
			if (array_key_exists($fixture['h'], $tiebreakers) && array_key_exists($fixture['a'], $tiebreakers)) {
				$this->add_h2h($tiebreakers[$fixture['h']], $fixture['h_data']);
				$this->add_h2h($tiebreakers[$fixture['a']], $fixture['a_data']);
			}
		}
		// uasort to keep the key for team
		uasort($tiebreakers, [$this, 'cmp_tiebreaker']);

		$i = $start;
		$order_changed = false;
		foreach ($tiebreakers as $team => $tiebreaker) {
			if ($table[$i]->team !== $team) {
				$order_changed = true;
				$table[$i] = $tiebreaker['row'];
				$table[$i]->tiebreaker = 1;
			}
			$i++;
		}
		if (!Tiebreaker_Gateway::save($comp_id, $start + 1, $points, $tiebreakers)) {
			$this->add_db_error('Failed to save tiebreakers table');
			return;
		}
		$this->status[] = 'A tie-break occurred for ' . $this->get_competition_name($comp_id)
			. ', start position ' . ($start + 1)
			. ', positions ' . ($order_changed ? 'WERE ' : 'were NOT ') . 'changed';
	}

	private function add_h2h(&$h2h_row, $data) {
		$h2h_row['points'] += $data['points'];
		$h2h_row['goal_diff'] += $data['goal_diff'];
		$h2h_row['wins'] += $data['win'];
	}

	/**
	 * Compare 2 rows in league tables tables
	 */
	private function cmp_tables($a, $b) {
		$cmp = $b->points - $a->points;
		if ($cmp) return $cmp;
		if ($b->goal_avg !== $a->goal_avg)
			return ($a->goal_avg > $b->goal_avg) ? -1 : 1;
		return strcmp($a->team, $b->team);
	}

	/**
	 * Compare 2 rows in tiebreaker
	 */
	private function cmp_tiebreaker($a, $b) {
		$cmp = $b['points'] - $a['points'];
		if ($cmp) return $cmp;
		$cmp = $b['goal_diff'] - $a['goal_diff'];
		if ($cmp) return $cmp;
		$cmp = $b['wins'] - $a['wins'];
		if ($cmp) return $cmp;
		return strcmp($a['row']->team, $b['row']->team);
	}

	private function get_competition_name($comp_id) {
		if ($this->competition_by_id == null) {
			// lazy load array
			$this->competition_by_id = array_column($this->competitions, 'name', 'id');
		}
		return $this->competition_by_id[$comp_id];
	}

	private function load_flags() {
		$rows = $this->get_rows('Flags', false);
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
				$date = $cur_row[$col];
				if (is_int($date)) {
					$this->error->add('flags', "Date is parsed as an integer. Format issues? Flags row $row col $col: $date");
				} elseif (strlen($date) !== 19) {
					$this->error->add('flags', "Invalid date format on Flags row $row col $col: $date");
				} else {
					$round_cnt++;
					$round_dates[] = [ $comp_id, $round_cnt, substr($date, 0, 10) ];
				}
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
			$row = $end_row + 3;
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
	 * Fetch the Google Sheet, and parse it into a SimpleXLSX object
	 * @return bool true on success, false otherwise
	 */
	private function fetch_google_sheet($fixtures_sheet_id) {
		$xlsx = Net_Util::get_url("https://docs.google.com/spreadsheets/d/$fixtures_sheet_id/export?format=xlsx",
		// expect an Excel sheet. If something went wrong, e.g. the sheet is not shared, then
		// we get an html page
			'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
		if (is_wp_error($xlsx)) {
			if ($xlsx->get_error_code() === 'curl_unexpected_response_type') {
				$xlsx->add('fixtures', 'Check the Google Sheet is shared');
			}
			return $xlsx;
		}
		$this->xlsx = SimpleXLSX::parseData($xlsx);

		// testing
		// $this->xlsx = SimpleXLSX::parse(dirname(__DIR__,5) . '/Test.xlsx');
		if (!$this->xlsx) {
			return new WP_Error('xlsx_parse', 'Error parsing xlsx file: '. SimpleXLSX::parseError());
		}
		$this->sheet_names = array_flip($this->xlsx->sheetNames());
		return true;
	}

	/**
	 * retrieve rows from sheet, ignoring header row
	 * @param string $sheet sheet to return rows from
	 * @return array|null array of rows, or null on failure
	 */
	private function get_rows($sheet, $remove_header = true) {
		if (!array_key_exists($sheet,$this->sheet_names)) {
			$this->error->add('fixtures', "Unknown sheet $sheet");
			return null;
		}

		$rows = $this->xlsx->rows($this->sheet_names[$sheet]);
		if ($remove_header) {
			unset($rows[0]); // get rid of header
		}
		return $rows;
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
