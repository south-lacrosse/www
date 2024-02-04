<?php
namespace Semla\Data_Access;

use Semla\Cache;
use WP_Error;

class Lacrosse_Play_Gateway {
	private $lp_competition_id;
	private $competitions;
	// TODO: add back?? if remove also delete get_competition
	// private $unknown_competition = [];
	private $status = []; // array of update messages
	private $error;
	private $ch; // cURL handle
	private $max_flags_rounds = null;
	const GROUPS_FILE =  __DIR__.'/lacrosseplay_groups.txt';
	const DIVISIONS_WHERE = 4;

	public function __construct() {
		$this->ch = curl_init();
		curl_setopt_array($this->ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_CONNECTTIMEOUT => 20,
			CURLOPT_TIMEOUT => 60,
			CURLOPT_ENCODING => '', // all supported encodings
			CURLOPT_HTTPHEADER => Lacrosse_Play_Config::$headings,
		]);
		// Uncomment to not check the SSL certificates on Windows, as that can
		// sometimes cause problems
		// if (PHP_OS_FAMILY === 'Windows') {
		// 	curl_setopt_array($this->ch, [
		// 		CURLOPT_SSL_VERIFYHOST => false,
		// 		CURLOPT_SSL_VERIFYPEER => false,
		// 	]);
		// }

		// Uncomment to debug
		// curl_setopt_array($this->ch, [
		// 	CURLOPT_VERBOSE => true,
		// 	CURLOPT_STDERR => fopen(__DIR__ . '/curl.log', 'w+'),
		// ]);

		$this->error = new WP_Error();
	}

	public function get_competition_name($competition_id) {
		if (! $competitions = $this->get_data('competitions/published')) {
			return $this->error;
		}
		foreach ($competitions as $competition) {
			if ($competition->id === $competition_id) {
				return $competition->name;
			}
		}
		return false;
	}

	/**
	 * Update the competition data and fixtures/tables/flags
	 * @param bool $load_competition_data whether to load competition and teams
	 * @param string|array $data array of data to load (fixtures, tables, flags)
	 * @return array|WP_Error on success return array of update messages, on failure a WP_Error
	 */
	public function update($load_competition_data, $data = []) {
		$this->lp_competition_id = get_option('semla_lp_competition_id');
		if (!$this->lp_competition_id) {
			return new WP_Error('lp_competition_id', 'Cannot update as LacrossePlay Competition ID is not set');
		}
		if (!class_exists(Lacrosse_Play_Config::class)) {
			return new WP_Error('lp_config',
				'LacrossePlay configuration file Lacrosse_Play_Config.php does not exist. '
				. 'Please contact the Webmaster.');
		}

		$fp = fopen(__DIR__ . '/lock.txt', 'w');
		if (! flock($fp, LOCK_EX|LOCK_NB)) {
			return new WP_Error('fixtures_lock', 'Someone else is currently updating the fixtures!');
		}

		if ($load_competition_data) $this->load_teams();

		if (!file_exists(self::GROUPS_FILE)
		|| $load_competition_data) {
			$this->load_competitions();
			if ($this->error->has_errors()) return $this->error;
		} else {
			$this->competitions = unserialize(file_get_contents(self::GROUPS_FILE));
		}

		foreach ($data as $option => $value) {
			if (!$value) continue;
			$method = "update_$option";
			if (method_exists($this, $method)) {
				$this->$method();
			}
		}
		if ($this->error->has_errors()) return $this->error;

		$result = DB_Util::on_success();
		if (is_wp_error($result)) return $result;

		if ($this->max_flags_rounds) {
			update_option('semla_max_flags_rounds', $this->max_flags_rounds, 'no');
		}
		Cache::clear_cache();

		$datetime = (new \DateTime('now', new \DateTimeZone('Europe/London')))->format('d/m/Y H:i:s');
		update_option('semla_lacrosseplay_datetime', $datetime, 'no');

		if ($load_competition_data) {
			if (Competition_Gateway::clean_remarks() === false) {
				global $wpdb;
				$this->status[] = "Update completed, except failed to clean remarks. SQL error: $wpdb->last_error";
			}
		}

		fclose($fp);
		return $this->status;
	}

	private function load_competitions() {
		if (! $groups = $this->get_data('competitions/groups?competitionId=' . $this->lp_competition_id))
			return;
		if (!$competitions = Competition_Gateway::get_competitions()) {
			$this->add_db_error('Failed to load competitions');
			return;
		}
		// TODO:  fudge for now, competition names should match!!
		$aliases = [
			'Division 2 (West)' => 'SEMLA Division 2 West Conference',
			'Division 2 (East)' => 'SEMLA Division 2 East Conference',
			'London 1' => 'Local London D1',
			'London 2' => 'Local London D2',
			'London 3' => 'Local London D3',
			'Division 2A' => 'SEMLA Division 2',
		];
		$lp_competitions = $divisions = $ladders = [];
		foreach ($groups as $group) {
			$comp = null;
			if (isset($competitions[$group->name])) {
				$comp = $competitions[$group->name];
			} elseif (isset($aliases[$group->name])) {
				$comp = $competitions[$aliases[$group->name]];
			} else {
				// fudge for now, competition names should match!!
				foreach (['Local ','SEMLA '] as $league) {
					if (isset($competitions[$league . $group->name])) {
						$comp = $competitions[$league . $group->name];
						break;
					}
					if (isset($competitions[$league . $group->name . ' Division'])) {
						$comp = $competitions[$league . $group->name . ' Division'];
						break;
					}
				}
				if (!$comp) {
					$this->error->add('lp',"Unknown group $group->name");
				}
			}
			if ($comp->type === 'ladder') {
				$ladders[] = $comp;
				continue;
			}
			$lp_competitions[$group->name] = (object) [
				'id' => (int) $comp->id,
				'abbrev' => $comp->abbrev,
				'type' => $comp->type,
				// Using sequence from SEMLA, could use $group->ordering which should match position in array
				'seq' => $comp->seq,
				'playEachOther' => $group->playEachOther ?? 0,
				'totalEntrants' => $group->totalEntrants,
			];
			$divisions[] = [
				$comp->id,
				'D',0,0,// sort, promoted, relegated_after
				'=' . $comp->id
			];
		}
		if ($this->error->has_errors()) return;

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

		if (!Competition_Gateway::save_divisions_ladders($divisions, $ladders)) {
			$this->add_db_error('Failed to save divisions/ladders');
			return;
		}
		$this->status[] = 'Loaded ' . count($lp_competitions) . ' groups, created '
			. count($divisions) . ' divisions';
		if ($ladders) $this->status[] = 'Loaded info for ' . count($ladders) . ' ladders';

		$lp_competitions['Friendly'] = (object)
			['id'=>0,'abbrev'=>'Friendly','type'=>'friendly','seq'=>99999,'playEachOther'=>0, 'totalEntrants'=>0];
		file_put_contents(self::GROUPS_FILE, serialize($lp_competitions));
		$this->competitions = $lp_competitions;
	}

	private function load_teams() {
		if (! $teams = $this->get_data('competitions/teams?competitionId=' . $this->lp_competition_id))
			return;
		$club_slugs_aliases = Club_Gateway::get_club_slugs_aliases();
		if ($club_slugs_aliases === false) {
			$this->add_db_error('Failed to load club slugs and aliases');
			return;
		}
		extract($club_slugs_aliases);
		$team_rows = [];
		$invalid_club = false;
		foreach ($teams as $team) {
			if (!property_exists($team, 'region')) {
				$this->error->add('lp',"Team $team->name not in a region");
				continue;
			}
			if ($team->region !== Lacrosse_Play_Config::$region) {
				$this->error->add('lp',"Team $team->name not in valid region $team->region");
				continue;
			}
			$club = $club_alias[$team->club] ?? strtr($team->club, [
				' Lacrosse Club' => '',
				' LC' => '',
				' Mens' => '',
				' Men\'s' => '',
			]);
			if (!isset($club_slug[$club])) {
				$slug = '';
				$this->status[] =
					"$team->name's club is does not match a published club: <b>$team->club</b>";
				$invalid_club = true;
			} else {
				$slug = $club_slug[$club];
			}
			// name, club, club_page, pitch_type
			$team_rows[] = [$team->name,$club,$slug,''];
		}
		if ($invalid_club) {
			$this->status[] =
				'<br><b>For unknown clubs</b> either change the name on LacrossePlay'
				. ' to match, or set the LacrossePlay Club name for the Club by'
				. ' going the the <a href="edit.php?post_type=clubs&page=semla_club_alias">'
				. 'Club Alias page</a>.';
		}
		if ($this->error->has_errors()) {
			return;
		}
		if (!Club_Team_Gateway::save_teams($team_rows)) {
			$this->add_db_error('Failed to save teams');
			return;
		}
		$this->status[] = 'Loaded ' . count($team_rows) . ' teams';
	}

	private function update_tables() {
		if (! $standings = $this->get_data('competitions/standings?competitionId=' . $this->lp_competition_id))
			return;

		$total_entrants = array_reduce( $this->competitions, function ($sum, $competition) {
			if (str_starts_with($competition->type, 'league')) {
				$sum += $competition->totalEntrants;
			}
			return $sum;
		}, 0);
		if ($total_entrants !== count($standings)) {
			$this->error->add('lp',
				'Failed to load tables as standings/divisions team count mismatch. Standings API call returned '
					. count($standings) . ", divisions contain $total_entrants."
				);
			return;
		}
		$rows = [];
		$team = current($standings);
		$tables_count = 0;
		$form_letter = ['W','L','D'];
		foreach ($this->competitions as $competition) {
			if (! str_starts_with($competition->type, 'league')) continue;
			$tables_count++;
			for ($i = $competition->totalEntrants; $i > 0; $i--) {
				if ($team->goalsAgainst > 0) {
					$goal_avg = $team->goalsFor / $team->goalsAgainst;
				} elseif ($team->goalsFor == 0) {
					$goal_avg = 0;
				} else {
					$goal_avg = 99;
				}
				$form = '';
				if (property_exists($team, 'form')) {
					foreach ($team->form as $result) {
						$form .= $form_letter[$result->outcome];
					}
				}
				$rows[] = [
					$competition->id, $team->position, $team->name,
					$team->won, $team->drawn, $team->lost,
					$team->goalsFor, $team->goalsAgainst, $goal_avg, $team->minusPoints,
					$team->points,
					0, // $team->divider,
					$form,
					0 // $team->tiebreaker
				];
				$team = next($standings);
			}
		}
		if ($this->error->has_errors()) return;
		usort($rows, function($a, $b) {
			$cmp = $a[0] - $b[0];
			if ($cmp) return $cmp;
			return $a[1] - $b[1];
		});
		if (!Table_Gateway::save_tables($rows)) {
			$this->add_db_error('Failed to save tables');
			return;
		}
		$this->status[] = "Created $tables_count league tables, with $total_entrants entrants";
	}

	private function update_flags() {
		// if (! $cups = $this->get_data('competitions/???competitionId=' . $this->lp_competition_id))
		// 	return;
		$matches = $round_dates = [];
		$count = 0;

		// BEGIN test
		$file = __DIR__ . '/rounds.txt';
		if (file_exists($file)) {
			$flags = json_decode(file_get_contents($file));
		} else {
			$flags = [];
		}
		// END test

		// foreach ($cups as $flags) {
			$comp_id = 46; //senior flags
			// if (empty($this->competitions[$comp]) || ($this->competitions[$comp]->type != 'cup')) {
			// 	$this->error->add('fixtures', "Unknown flags competition $comp");
			// 	$comp_id = 0;
			// } else {
			// 	$comp_id = $this->competitions[$comp]->id;
			// }
			$round_no = 0;
			foreach ($flags as $round) {
				$round_no++;
				// $round->name
				$match_no = 1;
				foreach ($round->matches as $match) {
					$matches[] = [$comp_id, $round_no, $match_no++,
						str_starts_with($match->homeName, 'Winner of') ? null : $match->homeName,
						str_starts_with($match->awayName, 'Winner of') ? null : $match->awayName,
						null, null, 0 ];
				}
			}
			$count++;
			if ($round_no > $this->max_flags_rounds) {
				$this->max_flags_rounds = $round_no;
			}
		// }
		if ($this->error->has_errors()) return;

		if (!Cup_Draw_Gateway::save_current($matches, $round_dates)) {
			$this->add_db_error('Failed to save tables');
			return;
		}
		$this->status[] = "$count Flags competitions updated";
		return;
	}

	private function update_fixtures() {
		if (! $fixtures = $this->get_data('matches/all?competitionId=' . $this->lp_competition_id))
			return;
		$rows = [];
		$tz = new \DateTimeZone( 'Europe/London' );
		foreach ($fixtures as $fixture) {
			if (!property_exists($fixture,'group')
			|| !property_exists($fixture,'date')
			|| !property_exists($fixture,'homeName')) {
				$this->status[] = "Ignored invalid fixture:"
					. (!property_exists($fixture,'group') ? ', no group' : '')
					. (!property_exists($fixture,'date') ? ', no date' : '')
					. (!property_exists($fixture,'homeName') ? ', no homeName' : '')
					. ' '
					. json_encode($fixture);
				continue;
			}
			if (!isset($this->competitions[$fixture->group])) {
				$this->error->add('lp',"Unknown fixture group $fixture->group");
				continue;
			}
			if ($fixture->friendly) {
				$comp = $this->competitions['Friendly'];
			} else {
				$comp = $this->competitions[$fixture->group];
			}

			if (!property_exists($fixture,'time')) {
				$time = '14:00:00';
			} else {
				// TODO: doesn't seem to have local time
				$dt = new \DateTime( $fixture->time );
				$dt->setTimeZone( $tz );
				$time = $dt->format( 'H:i:s' );
			}
			$result = '';
			if (property_exists($fixture,'homeScore')) {
				$result = "$fixture->homeScore - $fixture->awayScore"
				. (property_exists($fixture,'forfeit') ? ' w/o' : '');;
			} elseif (property_exists($fixture,'postponed')) {
				$result = 'R - R';
			}
			$venue = null;
			if (property_exists($fixture,'venue')) {
				// TODO: venue
				// venue = club

				// TODO: pitch
			}
			$rows[] = [substr($fixture->date,0,10),$time,$comp->id,$comp->abbrev,
				$fixture->homeName,$fixture->awayName,$fixture->homeScore ?? null,$fixture->awayScore ?? null,$venue,$result,null,null,
				1, 'sort' => $comp->seq];
			// TODO: Fixtures_Sheet_Gateway allowed fixtures for divisions to have
			// different sort orders, e.g. Midlands by venue/time. That has been ignored
			// for now
		}
		if ($this->error->has_errors()) return;
		usort($rows, static function($a, $b) {
			$cmp = strcmp($a[0],$b[0]); // date
			if ($cmp) return $cmp;
			$cmp = $a['sort'] - $b['sort']; // competition sequence #
			if ($cmp) return $cmp;
			// if (isset($a['sort2'])) { // if we want to sort by venue/time
			// 	$cmp = strcmp($a['sort2'], $b['sort2']);
			// 	if ($cmp) return $cmp;
			// }
			$cmp = strcmp($a[4], $b[4]); // home
			if ($cmp) return $cmp;
			return strcmp($a[5], $b[5]); // away
		});

		if (!Fixtures_Gateway::save_fixtures($rows)) {
			$this->add_db_error('Failed to save fixtures');
			return;
		}
		$this->status[] = 'Loaded ' . count($rows) . ' fixtures';
	}

	/**
	 * Retrieve data from the LacrossePlay API, and decode into PHP objects
	 */
	private function get_data($uri) {
		$url = Lacrosse_Play_Config::$url . $uri;
		curl_setopt($this->ch, CURLOPT_URL, $url);

		$data = curl_exec($this->ch);

		if (curl_errno($this->ch)) {
			$this->error->add('lp','cURL error ' . curl_errno($this->ch) . ': '
				. curl_error($this->ch)	. ", URL: $url");
			return null;
		}
		$response_code = curl_getinfo($this->ch, CURLINFO_RESPONSE_CODE);
		if ($response_code != 200) {
			$this->error->add('lp',"Invalid HTTP response code: $response_code, data: $data");
			return null;
		}
		$content_type = curl_getinfo($this->ch, CURLINFO_CONTENT_TYPE);
		if ('application/json' !== strtok($content_type, ';')) {
			$this->error->add('lp',"Unexpected content type from API: $content_type, URL $url");
			return null;
		}

		return json_decode($data);
	}

	// private function get_competition($competition) {
	// 	if (empty($this->competitions[$competition])) {
	// 		if (empty($this->unknown_competition[$competition])) {
	// 			$this->unknown_competition[$competition] = 1;
	// 			$this->error->add('lp',"Unknown competition $competition");
	// 		}
	// 	}
	// 	return $this->competitions[$competition];
	// }

	private function add_db_error($message) {
		global $wpdb;
		$this->error->add('fixtures', "$message. SQL error: $wpdb->last_error");
	}
}
