<?php
namespace Semla\Render;
use Semla\Utils\Util;

/**
 * Rendering of cup draws
 */
class Cup_Draw_Renderer {
	const ROUNDS_LONG = ['Last 64', 'Last 32', 'Last 16', 'Quarter Final','Semi Final','Final'];
	const ROUNDS_SHORT = ['R64', 'R32', 'R16', 'QF','SF'];

	/**
	 * @param int $year year from history, or 0 for current
	 * @param string $display empty for default, or "rounds"
	 * @param object $years previous and next years for navigation
	 * @param array $rows rows from database for draw
	 * @param array $group_rows rows from database for group stages
	 * @param array  $remarks array of strings keyed by competition id
	 * @param string $slug slug for links to previous/next year for history
	 */
	public static function cup_draw($year,$display,$years,$rows,$group_rows,$remarks,$slug='') {
		// split group stage "divisions" by their flags comp_id
		$groups = [];
		$start = $row_no = $related_comp_id = 0;
		foreach ($group_rows as $row) {
			if ($row->related_comp_id !== $related_comp_id) {
				if ($related_comp_id) {
					$groups[$related_comp_id] = array_slice($group_rows, $start, $row_no - $start);
				}
				$start = $row_no;
				$related_comp_id = $row->related_comp_id;
			}
			$row_no++;
		}
		if ($row_no) {
			$groups[$related_comp_id] = array_slice($group_rows, $start, $row_no - $start);
		}

		if ($years && ($years->next || $years->prev)) {
			$slug .= '-';
			$query = $display ? '-rounds' : '';
			echo '<nav class="hist-nav prev-center-next" aria-label="Draws">',
				'<h2 class="screen-reader-text">Draws navigation</h2>',
				"\n";
			if ($years->prev) {
				echo '<a href="', $slug, $years->prev, $query, '" rel="prev">« ', $years->prev, '</a>';
			} else {
				echo '<div></div>';
			}
			echo '<a class="center" href="', $slug, $year,
				$display ? '' : '-rounds', '">View ', $display ? 'Bracket' : 'Rounds',
				'</a>';
			if ($years->next) {
				echo '<a href="', $slug, $years->next, $query,
					'" rel="next">', $years->next, ' »</a>';
			}
			echo "\n</nav>\n";
		} else {
			if ($year) {
				$url = "$slug-$year";
				if (!$display) $url .= '-rounds';
			} else {
				$url = get_post()->post_name;
				if (!$display) {
					$url .= '-rounds';
				} else {
					$url = substr($url, 0, -7);
				}
			}
			echo '<p><a href="', $url, '">View ', $display ? 'Bracket' : 'Rounds', "</a></p>\n";
			if (!$year) {
				echo '<p><strong>Please note:</strong> dates given are those initially scheduled for the round, however the',
					' actual fixtures may be rearranged or postponed. Please check the <a href="/fixtures">complete fixtures list</a>',
					" for the exact date, time, and location of matches.</p>\n";
			}
		}

		if ($display) {
			echo self::get_draw_tables($year, $rows, $groups, 'after-nav', $remarks);
		} else {
			$comp_id = 0;
			$h2_class = 'after-nav';
			foreach ( $rows as $row ) {
				if ($row->comp_id <> $comp_id) {
					if ($comp_id) {
						echo self::get_draw($comp_id, $year, $matches, $groups, $h2_class, $remarks);
						$h2_class = 'mt-large';
					}
					$comp_id = $row->comp_id;
					$matches = [];
				}
				$matches[] = $row;
			}
			if ($comp_id) {
				echo self::get_draw($comp_id, $year, $matches, $groups, $h2_class, $remarks);
			}
		}
	}

	private static function get_draw_tables($year, $matches, $groups, $h2_class, $remarks) {
		$prev_comp = 0;

		$rounds_long_count = count(self::ROUNDS_LONG);
		$comp_final_round = [];
		// figure out final round for each competition
		foreach ($matches as $match) {
			$comp_id = $match->comp_id;
			$comp_final_round[$comp_id] = $match->round;
		}
		foreach ($matches as $match) {
			$comp_id = $match->comp_id;
			if ($comp_id <> $prev_comp) {
				if ($prev_comp) {
					echo '</tbody></table></div>';
					if (isset($remarks[$prev_comp]))
						echo '<p>', $remarks[$prev_comp]->remarks, "</p>\n";
					if ($section) echo "</section>\n";
				}
				$section = !empty($match->section_name);
				if ($section) {
					echo '<section id="', Util::make_id($match->section_name), '"><h2',
						$h2_class ? ' class="' . $h2_class . '"' : '',
						'>', $match->section_name, "</h2>\n";
				}
				if (!empty($groups[$comp_id])) {
					Table_Renderer::tables($groups[$comp_id], 'cup', $year, $remarks);
				}
				$h2_class = 'mt-large';
				$prev_comp = $comp_id;
				$final_round = $comp_final_round[$comp_id];
				$offset = $rounds_long_count - $final_round - 1;
				$prev_round = 0;
			}
			$round = $match->round;
			if ($round <> $prev_round) {
				if ($prev_round) {
					echo '</tbody></table></div>', "\n";
				}
				echo '<div class="scrollable"><table class="table-data cup-draw"><caption><span class="caption-text">',
					self::ROUNDS_LONG[$round + $offset],
					isset($match->match_date) ? ', ' . date('j M Y', strtotime($match->match_date)) : '',
					'</span></caption>';
				if ($round === $final_round) {
					echo '<colgroup><col class="min-width"><col class="home"><col class="min-width"><col class="away">';
				} else {
					echo '<thead><tr><th class="min-width"></th><th class="home">Home</th><th class="min-width"></th><th class="away">Away</th></tr></thead>';
				}
				echo "\n<tbody>\n";
				$prev_round = $round;
			}
			$home_team = $match->home_team;
			if ($home_team > 2) { // neutral home/away
				$home_team -= 2;
			}
			$team1_first = $home_team < 2;
			if (isset($match->team1_goals) && $match->team1_goals != null) {
				if ($team1_first) {
					$score = $match->team1_goals . ' - ' . $match->team2_goals;
				} else {
					$score = $match->team2_goals . ' - ' . $match->team1_goals;
				}
			} elseif ($match->team1 === 'Bye' || $match->team2 === 'Bye') {
				$score = '';
			} else {
				$score = 'v';
			}
			$match_num = $match->match_num;
			echo '<tr><td>', ($round <> $final_round ? $match_num : '&nbsp;'),
				'</td><td class="home">',
				self::team_name($team1_first,$team1_first,$match,$round,$offset,$match_num),
				'</td><td class="result">' . $score . '</td><td class="away">',
				self::team_name(!$team1_first,$team1_first,$match,$round,$offset,$match_num),
				"</td></tr>\n";
		}
		if ($prev_comp) {
			echo "</tbody></table></div>\n";
			if (isset($remarks[$prev_comp]))
				echo '<p>', $remarks[$prev_comp]->remarks, "</p>\n";
			if ($section) echo "</section>\n";
		}
	}

	private static function team_name($want_team1,$team1_first,$match,$round,$offset,$match_num) {
		$team = (string) ($want_team1 ? (empty($match->team1) ? '' : $match->team1)
				: (empty($match->team2) ? '' : $match->team2));
		if ($team || $round === '1') return htmlspecialchars($team, ENT_NOQUOTES);
		$match_offset = ($team1_first && !$want_team1) || (!$team1_first && $want_team1) ? 1 : 0;
		$win_match = ($match_num * 2) - 1 + $match_offset;
		return 'Winner ' . self::ROUNDS_SHORT[$round + $offset - 1] . ' match ' . $win_match;
	}

	private static function get_draw($comp_id, $year, $matches, $groups, $h2_class, $remarks) {
		$match_count = count($matches);
		// We can shrink round 1 to the same size as round 2 IF all matches in round 2
		//  don't have 2 matches feeding into it, i.e. we cannot have

		// r1 match 1 \
		//              r2 match 1
		// r1 match 2 /

		//  as that won't fit if round 1 and round 2 are the same height, but we
		//  can shrink if we have

		// r1 match 1 - r2 match 1
		// r1 match 3 - r2 match 2

		// Note: If the 1st round cannot be shrunk and the 1st match in round 1
		// is missing then we get whitespace at the top of all the rounds. We
		// used to remove that by adding extra CSS classes, but as it only
		// happened in 1 year we removed the extra CSS, and simply changed the
		// history so there is always a match 1. The standard practice from now
		// on is to put in Byes fort all round 1 matches, so there should always
		// be a round 1 match 1 in the future.

		// If you want to put this back it was removed in commit
		//  "remove flags handling of empty first match"
		if ($match_count == 1) {
			$shrink_round1 = false;
		} else {
			$shrink_round1 = true;
			$last_match = 0;
			foreach ( $matches as $match ) {
				if ($match->round <> 1) {
					break;
				}
				if ($match->match_num % 2 == 0) { // even match
					if ($match->match_num == $last_match + 1) {
						// if we had the matching odd match then we can't shrink round 1
						$shrink_round1 = false;
						break;
					}
				}
				$last_match = $match->match_num;
			}
		}
		// end doesn't work for simplexml data - so use index
		$final_round = $matches[$match_count - 1]->round;
		$count = count(self::ROUNDS_LONG);
		$rounds = array_slice(self::ROUNDS_LONG, $count - $final_round, $final_round);

		$match0 = $matches[0];
		$section = !empty($match0->section_name);
		if ($section) {
			echo '<section class="alignwide" id="', Util::make_id($match0->section_name), '"><h2',
				$h2_class ? ' class="' . $h2_class . '"' : '', '>',
				$match0->section_name, "</h2>\n";
		}
		if (!empty($groups[$comp_id])) {
			Table_Renderer::tables($groups[$comp_id], 'cup', $year, $remarks);
			echo "<h3>Knockout Stages</h3>\n";
		}
		// Note: flags MUST be the first class as the single-history template
		// checks for 'ul class="flags' to enqueue flags.css
		echo '<ul class="flags', $section ? '' : ' alignwide', "\">\n";
		$prev_round = 0;
		$last_match = 0;
		foreach ($matches as $match) {
			$round = $match->round;
			if ($prev_round <> $round) {
				if ($prev_round <> 0) {
					echo '</ul></li>';
				}
				$rClass = $round;
				if ($shrink_round1 && $match->round > 1) {
					$rClass--;
				}
				echo "<li>\n", '<div class="round-title"><h3>', $rounds[$prev_round],
					'</h3>', isset($match->match_date) ? date('j M Y ', strtotime($match->match_date)) : '',
					'</div>', "\n" , '<ul class="r', $rClass, '">';
				$prev_round = $round;
				$last_match = 0;
			}
			if ($shrink_round1 && $round == 1) {
				// convert to new match number, must be half the even match number
				$modified_match = ($match->match_num + ($match->match_num % 2)) / 2;
				$missed_matches = $modified_match - $last_match - 1;
				for ($i = $missed_matches; $i > 0; $i--) {
					echo '<li class="empty"></li>';
				}
				$last_match = $modified_match;
				$after = '<div class="line1 across"></div>';
			} elseif ($round < $final_round) {
				// TODO: this only works on round 1, which is OK for all current configurations
				// but this may need to be applied to all rounds.
				if ($round == 1) {
					$missed_matches =  $match->match_num - $last_match - 1;
					if ($missed_matches) {
						// if current round is even, and we have missed rounds, then
						// we need to make sure the horizontal line to the next round is added
						if ($match->match_num % 2 == 0) {
							for ($i = $missed_matches - 1; $i > 0; $i--) {
								echo '<li class="empty"></li>';
							}
							echo '<li class="empty"><div class="line2"></div></li>';
						} else {
							for ($i = $missed_matches; $i > 0; $i--) {
								echo '<li class="empty"></li>';
							}
						}
					}
					$last_match = $match->match_num;
				}
				// need to add lines pointing to next round
				if ($match->match_num % 2 == 0) {
					$after = '<div class="line1 up"></div>';
				} else {
					$after = '<div class="line1 down"></div><div class="line2"></div>';
				}
			} else {
				$after = '';
			}
			switch ($match->home_team) {
				case 0:
					$ha1 = $ha2 = '';
					break;
				case 1:
					$ha1 = 'H '; $ha2 = 'A ';
					break;
				case 2:
					$ha2 = 'H '; $ha1 = 'A ';
					break;
				case 3:
					$ha1 = 'NH '; $ha2 = 'NA ';
					break;
				case 4:
					$ha2 = 'NH '; $ha1 = 'NA ';
					break;
			}
			echo '<li><div class="match-panel"><div>', $ha1;
			if (!empty($match->team1)) {
				echo htmlspecialchars(empty($match->alias1) ? $match->team1 : $match->alias1, ENT_NOQUOTES);
			}
			echo  (isset($match->team1_goals) && $match->team1_goals != null ? '<span class="score">' . $match->team1_goals . '</span>' : ''),
				'</div><div>', $ha2;
			if (!empty($match->team2)) {
				echo htmlspecialchars(empty($match->alias2) ? $match->team2 : $match->alias2, ENT_NOQUOTES);
			}
			echo (isset($match->team2_goals) && $match->team2_goals != null ? '<span class="score">' . $match->team2_goals . '</span>' : ''),
				'</div></div>', $after, '</li>';
		}
		echo "</ul></li></ul>\n";
		if (isset($remarks[$comp_id]))
			echo '<p>', $remarks[$comp_id]->remarks, "</p>\n";
		if ($section) echo "</section>\n";
	}
}
