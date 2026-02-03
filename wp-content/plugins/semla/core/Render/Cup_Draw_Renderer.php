<?php
namespace Semla\Render;
use Semla\Utils\Util;

/**
 * Rendering of cup draws
 */
class Cup_Draw_Renderer {
	const ROUNDS_LONG = ['Last 64', 'Last 32', 'Last 16', 'Quarter Final','Semi Final','Final'];
	const ROUNDS_SHORT = ['R64', 'R32', 'R16', 'QF','SF'];
	private static $rounds_offset; // where the root competition rounds start in the above arrays

	/**
	 * @param int $year year from history, or 0 for current
	 * @param string $display empty for default bracket (grid) view, or "rounds"
	 * @param object $years previous and next years for navigation
	 * @param array $rows rows from database for draw
	 * @param array $group_rows rows from database for group stages. Will be league
	 *    tables for default display, or group fixtures for "rounds"
	 * @param array $remarks array of strings keyed by competition id
	 * @param string $slug slug for links to previous/next year for history
	 */
	public static function cup_draws($year, $display, $years, $rows, $group_rows, $remarks, $slug='') {
		$is_bracket = $display === ''; // only 2 values allowed, so make a boolean

		// the $rows may contain root level competitions (Minor Flags), and also possibly
		// prelims (Play-In).
		// split the $rows into 2 arrays which are keyed by competition id, first
		// is $cups to main cup competitions, second is $prelims for preliminary competitions
		$cups = $prelims = [];
		$start = $row_no = $comp_id = $related_comp_id = 0;
		foreach ($rows as $row) {
			if ($row->comp_id !== $comp_id) {
				if ($comp_id) {
					if ($related_comp_id) {
						$prelims[$related_comp_id][$comp_id] = array_slice($rows, $start, $row_no - $start);
					} else {
						$cups[$comp_id] = array_slice($rows, $start, $row_no - $start);
					}
				}
				$start = $row_no;
				$comp_id = $row->comp_id;
				$related_comp_id = $row->related_comp_id;
			}
			$row_no++;
		}
		if ($comp_id) {
			if ($related_comp_id) {
				$prelims[$related_comp_id][$comp_id] = array_slice($rows, $start, $row_no - $start);
			} else {
				$cups[$comp_id] = array_slice($rows, $start, $row_no - $start);
			}
		}
		// split group stages by their flags comp_id
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
			$query = $is_bracket ? '' : '-rounds';
			echo '<nav class="hist-nav prev-center-next" aria-label="Draws">',
				'<h2 class="screen-reader-text">Draws navigation</h2>',
				"\n";
			if ($years->prev) {
				echo '<a href="', $slug, $years->prev, $query, '" rel="prev">« ', $years->prev, '</a>';
			} else {
				echo '<div></div>';
			}
			echo '<a class="center" href="', $slug, $year,
				$is_bracket ? '-rounds' : '', '">View ', $is_bracket ? 'Rounds' : 'Bracket',
				'</a>';
			if ($years->next) {
				echo '<a href="', $slug, $years->next, $query,
					'" rel="next">', $years->next, ' »</a>';
			}
			echo "\n</nav>\n";
		} else {
			if ($year) {
				$url = "$slug-$year";
				if ($is_bracket) $url .= '-rounds';
			} else {
				$url = get_post()->post_name;
				if ($is_bracket) {
					$url .= '-rounds';
				} else {
					$url = substr($url, 0, -7);
				}
			}
			echo '<p><a href="', $url, '">View ', $is_bracket ? 'Rounds' : 'Bracket', "</a></p>\n";
			if (!$year) {
				echo '<p><strong>Please note:</strong> dates given are those initially scheduled for the round, however the',
					' actual fixtures may be rearranged or postponed. Please check the <a href="/fixtures">complete fixtures list</a>',
					" for the exact date, time, and location of matches.</p>\n";
			}
		}

		$section_class = $is_bracket ? ' class="alignwide"' : '';
		$h2_class = 'after-nav';
		$rounds_long_count = count(self::ROUNDS_LONG);
		foreach ($cups as $comp_id => $matches) {
			$last_match = end($matches);
			$has_section = !empty($last_match->section_name);
			if ($has_section) {
				echo '<section', $section_class, ' id="', Util::make_id($last_match->section_name),
					'"><h2', $h2_class ? ' class="' . $h2_class . '"' : '', '>',
					$last_match->section_name, "</h2>\n";
			}
			$h2_class = 'mt-large';

			self::$rounds_offset = $rounds_long_count - $last_match->round - 1;
			// limit prelims/groups to this competition before call
			$comp_prelims = $prelims[$comp_id] ?? [];
			$comp_groups = $groups[$comp_id] ?? [];
			if ($is_bracket) {
				self::render_competition($comp_id, $year, $has_section, $matches,
					$comp_prelims, $comp_groups, $remarks);
			} else {
				self::render_competition_rounds($comp_id, $year, $matches,
					$comp_prelims, $comp_groups, $remarks);
			}
			if ($has_section) echo "</section>\n";
		}
	}

	/**
	 * Rounds view, which is matches listed per round in an HTML table, and all matches for groups
	 */
	private static function render_competition_rounds($comp_id, $year, $matches, $prelims, $groups, $remarks) {
		if ($prelims) {
			foreach ($prelims as $prelim_id => $prelim_matches) {
				self::render_draw_rounds(true, false, $year, $prelim_matches, $remarks[$prelim_id]->remarks ?? '');
			}
		}
		if ($groups) {
			self::render_draw_rounds(true, true, $year, $groups, $remarks);
		}
		self::render_draw_rounds(false, false, $year, $matches, $remarks[$comp_id]->remarks ?? '');
	}

	/**
	 * @param $remarks string|array for groups will be array keyed by comp_id, otherwise will be string
	 */
	private static function render_draw_rounds($is_prelim, $is_groups, $year, $matches, $remarks) {
		$last_match = end($matches);
		$final_round = $is_groups ? -1 : $last_match->round;
		$prev = 0;
		foreach ($matches as $match) {
			$compare = $is_groups ? $match->comp_id : $match->round;
			$round = $match->round ?? 0;
			if ($compare <> $prev) {
				if ($prev) {
					echo '</tbody></table></div>', "\n";
					if ($is_groups && isset($remarks[$prev])) {
						echo '<p>', $remarks[$prev]->remarks, "</p>\n";
					}
				}
				echo '<div class="scrollable"><table class="table-data cup-draw"><caption><span class="caption-text">';
				if ($is_groups) {
					echo $match->section_name;
				} elseif ($is_prelim) {
					echo "$match->section_name Round $round";
				} else {
					echo self::ROUNDS_LONG[$round + self::$rounds_offset];
				}
				if (isset($match->match_date)) echo ', ', date('j M Y', strtotime($match->match_date));
				echo "</span></caption>\n<colgroup>",
					$year ? '' : '<col class="min-width">',
					'<col class="home"><col class="min-width"><col class="away">',
					"\n<tbody>\n";
				$prev = $compare;
			}
			$home_team = $match->home_team ?? 1;
			if ($home_team > 2) { // neutral home/away
				$home_team -= 2;
			}
			$team1_first = $home_team < 2;
			if (isset($match->result)) {
				// match has a result only for group fixtures, in which case home_team will be 1
				$score = $match->result ? $match->result : 'v';
			} elseif (isset($match->team1_goals)) {
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
			$match_num = $match->match_num ?? '';
			echo '<tr>';
			if (!$year) {
				echo '<td>', ($round <> $final_round ? $match_num : '&nbsp;'), '</td>';
			}
			echo '<td class="home">',
				self::team_name($team1_first,$team1_first,$match,$round,$is_prelim,$match_num),
				'</td><td class="result">' . $score . '</td><td class="away">',
				self::team_name(!$team1_first,$team1_first,$match,$round,$is_prelim,$match_num),
				"</td></tr>\n";
		}
		echo '</tbody></table></div>', "\n";
		if ($is_groups) {
			if (isset($remarks[$prev]))	echo '<p>', $remarks[$prev]->remarks, "</p>\n";
		} else {
			if ($remarks) echo '<p>', $remarks, "</p>\n";
		}
	}

	private static function team_name($want_team1,$team1_first,$match,$round,$is_prelim,$match_num) {
		$team = (string) ($want_team1 ? (empty($match->team1) ? '' : $match->team1)
				: (empty($match->team2) ? '' : $match->team2));
		if ($team || $round === '1') return htmlspecialchars($team, ENT_NOQUOTES);
		$match_offset = ($team1_first && !$want_team1) || (!$team1_first && $want_team1) ? 1 : 0;
		$win_match = ($match_num * 2) - 1 + $match_offset;
		$winner_round = $is_prelim ? "R$round" : self::ROUNDS_SHORT[$round + self::$rounds_offset - 1];
		return "Winner $winner_round match $win_match";
	}

	private static function render_competition($comp_id, $year, $has_section, $matches, $prelims, $groups, $remarks) {
		foreach ($prelims as $related_comp_id => $prelim_matches) {
			if ($prelim_matches[0]->section_name) {
				echo '<h3>', $prelim_matches[0]->section_name, "</h3>\n";
			}
			self::render_draw_grid(true, $prelim_matches, $has_section,
				$remarks[$related_comp_id]->remarks ?? '');
		}
		if (($groups)) {
			Table_Renderer::tables($groups, 'cup', $year, $remarks);
		}
		if (($prelims || $groups)) {
			echo "<h3>Knockout Stages</h3>\n";
		}
		self::render_draw_grid(false, $matches, $has_section, $remarks[$comp_id]->remarks ?? '');
	}

	private static function render_draw_grid($is_prelim, $matches, $has_section, $remarks) {
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
		// on is to put in Byes for all round 1 matches, so there should always
		// be a round 1 match 1 in the future.

		// If you want to put this back it was removed in commit
		//  "remove flags handling of empty first match"
		$match_count = count($matches);
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
		$final_round = $matches[$match_count - 1]->round;
		// Note: flags MUST be the first class as the single-history template
		// checks for 'ul class="flags' to enqueue flags.css
		echo '<ul class="flags', $has_section ? '' : ' alignwide', "\">\n";
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
				echo "<li>\n", '<div class="round-title"><h3>',
					$is_prelim ? "Round $round" : self::ROUNDS_LONG[$round + self::$rounds_offset],
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
		if ($remarks) echo '<p>', $remarks, "</p>\n";
	}
}
