<?php
namespace Semla\Data_Access;
/**
 * Update current database tables from the Fixtures Google Sheet.
 */
use Semla\Cache;
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
	const TEAM_CLUB_PAGE = 2;
	const TEAM_MINIMAL=4;
	const TEAM_SHORT=5;
	// Deductions
	const DEDUCT_COMPETITION = 0;
	const DEDUCT_POINTS = 2;
	// Divisions
	const DIVISIONS_COMP_ID = 0;
	const DIVISIONS_SORT_ORDER = 1;
	const DIVISIONS_PROMOTED = 2;
	const DIVISIONS_RELEGATED = 3;
	const DIVISIONS_WHERE = 4;

	private $xlsx; // the spreadsheet
	private $tables; // league tables
	private $points; // points for win/draw/loss
	private $sheet_names;
	// cache file of serialized tables with no games played
	private $tables_file;
	// WP_Error with any error messages. We try to show as many errors as
	// possible so they can all be fixed in one go.
	private $error;
	private $status = []; // array of update messages
	private $competitions;
	private $competition_by_id = null;
	private $divisions;
	private $teams; // array keyed on team for validating team names
	private $max_flags_rounds = 0;

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
				$team_names = Club_Team_Gateway::get_team_names();
				if ($team_names === false) {
					$this->add_db_error('Failed to load team names');
				} else {
					$this->teams = array_flip($team_names);
				}
				$this->divisions = Competition_Gateway::get_division_info();
				if ($this->divisions === false) {
					$this->add_db_error('Failed to load division info');
				}
			}
		}
		if ($this->error->has_errors()) return $this->error;

		$this->load_fixtures_deductions();
		$this->load_flags();
		$this->load_remarks();
		if ($this->error->has_errors()) return $this->error;

		$res = DB_Util::on_success();
		if (is_wp_error($res)) return $res;

		update_option('semla_max_flags_rounds', $this->max_flags_rounds, 'no');
		Cache::clear_cache();

		$datetime = (new \DateTime('now', new \DateTimeZone('Europe/London')))->format('d/m/Y H:i:s');
		update_option('semla_fixtures_datetime', $datetime, 'no');

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
		$team_minimals = $team_abbrevs = $this->teams = [];
		$club_slugs = Club_Gateway::get_club_slugs();
		if ($club_slugs === false) {
			$this->add_db_error('Failed to load club slugs');
			return;
		}
		$club_slugs = array_flip($club_slugs);
		foreach ($team_rows as $key => $row) {
			$team_name = $row[self::TEAM_NAME];
			$this->teams[$team_name] = 1;
			if (!empty($row[self::TEAM_CLUB_PAGE])) {
				if (!array_key_exists($row[self::TEAM_CLUB_PAGE], $club_slugs)) {
					$this->error->add('fixtures', "Unknown team page for $team_name");
					continue;
				}
			}
			if (!empty($row[self::TEAM_MINIMAL])) {
				$team_minimals[] = [$team_name,$row[self::TEAM_MINIMAL]];
			}
			if (!empty($row[self::TEAM_SHORT])) {
				$team_abbrevs[] = [$team_name,$row[self::TEAM_SHORT]];
			}
			$team_rows[$key] = array_splice($row,0,4);
		}
		$divisions = $tables = $ladders = [];
		foreach ($division_rows as $row) {
			$competition = $row[self::LEAGUE_DIVISION];
			if (empty($this->competitions[$competition])) {
				$this->error->add('fixtures', 'Division does not exist on the competitions table: ' . $competition);
				continue;
			}
			$comp = $this->competitions[$competition];
			if ($comp->type === 'ladder') {
				$ladders[] = $comp;
				continue;
			}
			$comp_id = $comp->id;
			$table = [];
			for ($i = self::TEAMS; !empty($row[$i]); $i++) {
				$team = new \stdClass();
				$team->team = $row[$i];
				if (!array_key_exists($team->team, $this->teams)) {
					$this->error->add('fixtures', "Team $team->team in division $competition is not on Teams sheet");
					continue;
				}
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

			$divisions[$comp_id] = [$comp_id, $row[self::SORT_ORDER][0], (int) $row[self::PROMOTED],
				$relegated_after, "=$comp_id"];
		}
		foreach ($ladders as $ladder) {
			foreach ([$ladder->ladder_comp_id1, $ladder->ladder_comp_id2] as $ladder_comp_id) {
				/** Add ladder to where clause for division so ladder matches will appear on
				 *  fixtures for division */
				$where = $divisions[$ladder_comp_id][self::DIVISIONS_WHERE];
				if ($where[0] === '=') {
					$divisions[$ladder_comp_id][self::DIVISIONS_WHERE] = "IN($ladder_comp_id,$ladder->id)";
				} else {
					$divisions[$ladder_comp_id][self::DIVISIONS_WHERE] = substr($where,0,-1) . ",$ladder->id)";
				}
			}
		}
		if ($this->error->has_errors()) return;
		if (!Competition_Gateway::save_divisions_ladders($divisions, $ladders)) {
			$this->add_db_error('Failed to save divisions/ladders');
			return;
		}
		$this->status[] = 'Loaded info for ' . count($divisions) . ' divisions';
		if ($ladders) $this->status[] = 'Loaded info for ' . count($ladders) . ' ladders';
		$this->divisions = $divisions;

		if (!Club_Team_Gateway::save_teams($team_rows)) {
			$this->add_db_error('Failed to save teams');
			return;
		}
		$this->status[] = 'Loaded info for ' . count($team_rows) . ' teams';

		if ($team_minimals) {
			$affected = Club_Team_Gateway::save_team_minimals($team_minimals);
			if ($affected === false) {
				$this->add_db_error('Failed to save team minimal names');
				return;
			}
			if ($affected) { // $affected is 2 per update, 1 per insert - so don't put in message
				$this->status[] = 'Team minimal names updated';
			}
		}

		if ($team_abbrevs) {
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
			$division_sort[$division[self::DIVISIONS_COMP_ID]] = $division[self::DIVISIONS_SORT_ORDER];
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
			$comp_id = 0;
			$comp_short = '';
			$is_league = false;

			if (isset($this->competitions[$competition])) {
				$c = $this->competitions[$competition];
				$seq = $c->seq;
				$comp_short = $c->abbrev ?? $c->name;
				$comp_id = $c->id;
				if ($c->type === 'ladder') {
					$ladder_comp_id1 = $c->ladder_comp_id1;
					$ladder_comp_id2 = $c->ladder_comp_id2;
					$is_league = true;
				} else {
					$ladder_comp_id1 = 0;
					if (str_starts_with($c->type, 'league')) $is_league = true;
				}
			} else {
				$temp = explode(' ', $competition);
				if (count($temp) < 2) {
					$this->status[] = "Warning: Unknown competition '$competition' in fixtures row $row_no";
					$comp_short = $competition;
				} else {
					$round = array_pop($temp); // get rid of flags round
					$comp_minus_round = implode(' ', $temp);
					if (empty($this->competitions[$comp_minus_round])) {
						$this->status[] = "Warning: Unknown competition '$competition' in fixtures row $row_no";
						$comp_short = $competition;
					} else {
						$c = $this->competitions[$comp_minus_round];
						$seq = $c->seq;
						$comp_short = ($c->abbrev ?? $c->name) . ' ' . $round;
						$comp_id = $c->id;
					}
				}
			}

			$h_goals = $this->parse_goals($row[$home_goals_col]);
			$a_goals = $this->parse_goals($row[$away_goals_col]);
			$result = '';
			$h_points = $a_points = null;

			if ($is_league) {
				// cater for ladders - most of the time it won't be
				if ($ladder_comp_id1 === 0) {
					$h_comp_id = $a_comp_id = $comp_id;
				} elseif (isset($this->tables[$ladder_comp_id1][$home])) {
					$h_comp_id = $ladder_comp_id1;
					$a_comp_id = $ladder_comp_id2;
				} else {
					$h_comp_id = $ladder_comp_id2;
					$a_comp_id = $ladder_comp_id1;
				}
				if (!isset($this->tables[$h_comp_id][$home])) {
					$this->error->add('fixtures', "$date $home v $away, $home is not in division $competition, row $row_no");
					continue;
				}
				if (!isset($this->tables[$a_comp_id][$away])) {
					$this->error->add('fixtures', "$date $home v $away, $away is not in division $competition,  row $row_no");
					continue;
				}
			}

			if (is_int($h_goals) && is_int($a_goals)) {
				$penalty = $v[0] === 'C';
				$result = sprintf('%d - %d', $h_goals, $a_goals)
					. ($penalty ? ' w/o' : '');
				if ($is_league) {
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
			$fixture = [$date,$time,$comp_id,$comp_short,
				$home,$away,$h_goals,$a_goals,$venue,$result,$h_points,$a_points,
				$multi,'sort' => $seq];
			if (isset($division_sort[$comp_id]) && $division_sort[$comp_id] === 'V') { // if we want to sort by venue/time
				$fixture['sort2'] = ($venue ?? $home) . $time;
			}
			$fixtures[] = $fixture;
		}
		if ($this->error->has_errors()) return;

		usort($fixtures, static function($a, $b) {
			$cmp = strcmp($a[0],$b[0]); // date
			if ($cmp) return $cmp;
			$cmp = $a['sort'] - $b['sort']; // competition sequence #
			if ($cmp) return $cmp;
			if (isset($a['sort2'])) { // if we want to sort by venue/time
				$cmp = strcmp($a['sort2'], $b['sort2']);
				if ($cmp) return $cmp;
			}
			$cmp = strcmp($a[4], $b[4]); // home
			if ($cmp) return $cmp;
			return strcmp($a[5], $b[5]); // away
		});
		if (!Fixtures_Gateway::save_fixtures($fixtures)) {
			$this->add_db_error('Failed to save fixtures');
			return;
		}
		$this->status[] = 'Loaded ' . count($fixtures) . ' fixtures';

		// Now to tables
		if (!Tiebreaker_Gateway::create_table()) {
			$this->add_db_error('Failed to create tiebreaker table');
			return;
		}
		$division_order = $this->get_division_order();
		foreach ($this->divisions as $division) {
			$comp_id = $division[self::DIVISIONS_COMP_ID];
			$promoted = $division[self::DIVISIONS_PROMOTED];
			$relegated_after = $division[self::DIVISIONS_RELEGATED];
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
			if (array_key_exists($comp_id, $division_order)) {
				// "Division Order" sheet has specific order
				$team_order = $division_order[$comp_id];
				if (count($team_order) !== count($table)) {
					$this->error->add('fixtures', 'Division Order for ' . $this->get_competition_name($comp_id)
						. ' has wrong number of teams');
					continue;
				}
				$new_table = [];
				foreach ($table as $team) {
					if (!array_key_exists($team->team, $team_order)) {
						$this->error->add('fixtures', 'Division Order for ' . $this->get_competition_name($comp_id)
						. ' has unknown team ' . $team->team);
						continue;
					}
					$new_table[$team_order[$team->team]] = $team;
				}
				$table = $new_table;
				ksort($table);
			} else {
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
				['played' => 0, 'points' => 0, 'goal_diff' => 0, 'wins' => 0,
					'position' => $i + 1, 'row' => $table[$i]];
		}
		// calculate the head2head record for all teams on the same points
		foreach ($fixtures as $fixture) {
			if (array_key_exists($fixture['h'], $tiebreakers) && array_key_exists($fixture['a'], $tiebreakers)) {
				$this->add_h2h($tiebreakers[$fixture['h']], $fixture['h_data']);
				$this->add_h2h($tiebreakers[$fixture['a']], $fixture['a_data']);
			}
		}
		// tiebreaker teams must have played at least once of the other teams
		foreach ($tiebreakers as $team => $tiebreaker) {
			if ($tiebreaker['played'] === 0) {
				$this->status[] = 'A tie-break occurred for ' . $this->get_competition_name($comp_id)
				. ', start position ' . ($start + 1)
				. ', but it was ignored as at least one team had played none of the others';
				return;
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
		$h2h_row['played'] += 1;
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
		return $a['position'] - $b['position'];
	}

	private function get_competition_name($comp_id) {
		if ($this->competition_by_id == null) {
			// lazy load array
			$this->competition_by_id = array_column($this->competitions, 'name', 'id');
		}
		return $this->competition_by_id[$comp_id];
	}

	private function get_division_order() {
		if (!array_key_exists('Division Order', $this->sheet_names)) {
			return [];
		}
		$division_order = [];
		$rows = $this->get_rows('Division Order', false);
		$comps = '';
		$row_count = count($rows);
		for ($col = count($rows[0]) - 1; $col >= 0; $col--) {
			$comp = $rows[0][$col];
			if (empty($this->competitions[$comp])) {
				$this->error->add('fixtures', "Division Order sheet: Competition $comp does not exist");
				continue;
			}

			$teams = [];
			$pos = 1;
			for ($row = 1; $row < $row_count; $row++) {
				if (empty($rows[$row][$col])) break;
				$teams[$rows[$row][$col]] = $pos++;
			}
			$division_order[$this->competitions[$rows[0][$col]]->id] = $teams;

			if ($comps !== '') $comps .= ', ';
			$comps .= $comp;
		}
		$this->status[] = "Loaded division order for $comps";
		return $division_order;
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
				$num = $team1_goals = 0;
				$team1 = false;
				$home_team = '';
				for ($row = $start_row; $row<= $end_row; $row++) {
					if (!empty($rows[$row][$col])) {
						if ($team1 === false) { // home
							$home_team = $ha[ $rows[$row][$col] ];
							$team1 = $rows[$row][$col+1];
							$team1_goals = $rows[$row][$col+2];
						} else {
							$team2 = $rows[$row][$col+1];
							$matches[] = [$comp_id, $round, ++$num,
								$team1, $team2, $team1_goals,
								$rows[$row][$col+2], $home_team ];
							$this->validate_flags_team($comp, $team1);
							$this->validate_flags_team($comp, $team2);
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
				$this->validate_flags_team($comp, $team1);
				$this->validate_flags_team($comp, $team2);
				$row = $end_row + 3;
			$count++;
		}
		if ($this->error->has_errors()) return;
		if (!Cup_Draw_Gateway::save_current($matches, $round_dates)) {
			$this->add_db_error('Failed to save Flags draws');
			return;
		}
		$this->status[] = "$count Flags competitions updated";
	}

	private function validate_flags_team($comp, $team) {
		if (!$team || $team === 'Bye') return;
		if (!array_key_exists($team, $this->teams)) {
			$this->error->add('fixtures', "Team $team in competition $comp is not on Teams sheet");
		}
	}

	private function load_remarks() {
		$remarks = [];
		// Remarks sheet is optional
		if (array_key_exists('Remarks', $this->sheet_names)) {
			foreach ($this->get_rows('Remarks') as $row) {
				if (empty($row[0])) continue;
				if (empty($this->competitions[$row[0]])) {
					$this->error->add('fixtures', "Remarks sheet: Competition $row[0] does not exist");
					continue;
				}
				$remarks[] = [$this->competitions[$row[0]]->id, $row[1]];
			}
		}
		if (!Competition_Gateway::save_remarks($remarks)) {
			$this->add_db_error('Failed to save Remarks');
			return;
		}
		if ($remarks) $this->status[] = count($remarks) . ' remarks updated';
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
		if (!array_key_exists($sheet, $this->sheet_names)) {
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

		$tables = ['cup_draw', 'cup_round_date', 'deduction', 'fixture', 'fixture_date',
			'table', 'tiebreaker', 'remarks'];

		$tables_count = $wpdb->get_var(
			'SELECT COUNT(*) FROM information_schema.TABLES
			WHERE TABLE_CATALOG = "def" AND TABLE_SCHEMA = "' . DB_NAME . '"
				AND TABLE_TYPE = "BASE TABLE"
				AND TABLE_NAME IN ("backup_' . implode('", "backup_', $tables). '")');
		if ($tables_count === null) {
			return self::db_error('Failed to count backup tables');
		}
		if ($tables_count != count($tables)) {
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
