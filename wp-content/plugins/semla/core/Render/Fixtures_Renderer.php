<?php
namespace Semla\Render;

use Semla\Utils\Util;

class Fixtures_Renderer {
	private $query_type;
	private $options;
	private $date_in_heading;
	private $show_competition;
	private $show_round;
	private $has_fixture = false;
	private $last_date = null;
	private $last_year = null;
	private $last_month = null;
	private $last_day = null;

	const MONTHS = ['<abbr title="January">Jan</abbr>', '<abbr title="February">Feb</abbr>',
		'<abbr title="March">Mar</abbr>', '<abbr title="April">Apr</abbr>',
		'May', '<abbr title="June">Jun</abbr>',
		'<abbr title="July">Jul</abbr>', '<abbr title="August">Aug</abbr>',
		'<abbr title="September">Sep</abbr>', '<abbr title="October">Oct</abbr>',
		'<abbr title="November">Nov</abbr>', '<abbr title="December">Dec</abbr>'];

	public function fixtures($year, $rows, $type, $arg, $is_cup, $has_ladders, $options) {
		$keys = [];
		$this->query_type = $type;
		$this->options = $options;
		$this->date_in_heading = $type === 'date' || $type === 'default';
		$this->show_competition = $has_ladders || $type !== 'comp';
		$this->show_round = ! $this->show_competition && $is_cup;

		foreach ( $rows as $row ) {
			$this->check_date_change($row->match_date);
			$team_link = !$year && $row->result == '';
			$this->td_team($row->home,' class="home"', $team_link);
			$hl = '';
			if ($row->result) {
				switch ($row->result[0]) {
					case 'R':
						$result = '<abbr title="Rearranged/postponed">R - R</abbr>';
						$keys['R'] = '<i>R - R</i> = Rearranged/postponed';
						break;
					case 'C' :
						$result = '<abbr title="Cancelled">Canc.</abbr>';
						$keys['C'] = '<i>Canc.</i> = Cancelled';
						break;
					case 'A' :
						$result = '<abbr title="Abandoned">Aband.</abbr>';
						$keys['A'] = '<i>Aband.</i> = Abandoned';
						break;
					default:
						$result = $row->result;
						if (str_contains($result, 'w/o')) {
							$keys['W'] = '<i>w/o</i> = walkover';
						}
				}
			} else {
				$result = Util::format_time($row->match_time);
				if ($result !== '2pm')  {
					$hl = ' hl';
				}
				if ($row->pitch_type) {
					$result .= ' ' . $row->pitch_type;
					$hl = ' hl';
				}
			}
			if ($row->points_multi > 1) {
				$result .= ' <sup>*' . $row->points_multi . '</sup>';
				$keys['*'] = '<i>*2</i> = multiple points';
			}
			if (!$row->result && $row->venue) {
				$result .= "<br>at $row->venue";
				$hl = ' hl';
			}
			echo '<td class="result' . $hl . '">' . $result . '</td>';
			$this->td_team($row->away,' class="away"', $team_link);
			if ($this->show_competition) {
				echo '<td class="comp">' . $row->competition . '</td>';
			} else if ($this->show_round) {
				// round should be the last word
				$pos = strrpos($row->competition, ' ');
				$round = $pos === false ? $row->competition : substr($row->competition, $pos + 1);
				echo '<td class="comp">' . str_replace('Final', 'F', $round) . '</td>';
			}
			echo "</tr>\n";
		}
		echo "</tbody></table></div>\n";

		if (!empty($keys)) {
			ksort($keys);
			echo '<p><b>Key:</b> ' . implode(', ', $keys) . "</p>\n";
		}
		if ($year == 0) {
			if ($type === 'team') {
				$cal_url = site_url( rest_get_url_prefix() . '/semla/v1/teams/' . str_replace(' ','_',$arg) . '/fixtures.ics');
				echo '<p class="no-print">';
				if (!empty($options['team_club'][$arg])) {
					echo 'Go to <a href="clubs/'.$options['team_club'][$arg].'">club page</a> or ';
				}
				echo '<b>Subscribe:</b> <a href="' . $cal_url . '">iCalendar link</a>'
					. ' | <a rel="nofollow" href="https://calendar.google.com/calendar/render?cid='
					. str_replace('https://', 'webcal://', $cal_url) . '">add to Google Calendar</a></p>'."\n";
			} elseif ($type === 'club' && !empty($options['club'][$arg]->club_page)) {
				echo '<p class="no-print">Go to <a href="clubs/'.$options['club'][$arg]->club_page.'">club page</a></p>'."\n";
			}
		}
	}
	/**
	 * Checks if date has changed, and if so emits a header.
	 * Then emits start of row with date formatted as necessary - so
	 * we don't repeat date if multiple results on the same day
	 */
	private function check_date_change($fixture_date) {
		if ($this->date_in_heading) {
			// new heading for each date
			if ($fixture_date !== $this->last_date) {
				$this->last_date = $fixture_date;
				$this->date_heading($fixture_date);
			}
			echo '<tr>';
			return;
		}
		list ($year, $month, $day) = explode('-', $fixture_date);
		// new heading just for year change
		if ($year !== $this->last_year) {
			$this->last_year = $year;
			$this->last_month = null;
			$this->last_day = null;
			$this->date_heading($fixture_date);
		}
		echo '<tr>';

		// output the correct day/month - don't show again if unchanged
		if ($this->query_type === 'all') {
			if ($month !== $this->last_month || $day !== $this->last_day) {
				$this->last_month = $month;
				$this->last_day = $day;

				echo '<td>' . self::MONTHS[$month -1]
					. '</td><td>' .$day . '</td>';
			} else {
				echo '<td></td><td></td>';
			}
		} else {
			if ($month !== $this->last_month) {
				$this->last_month = $month;
				$this->last_day = null;
				echo '<td>' . self::MONTHS[$month -1] . '</td>';
			} else {
				echo '<td></td>';
			}
			if ($day !== $this->last_day) {
				$this->last_day = $day;
				echo '<td>' .$day . '</td>';
			} else {
				echo '<td></td>';
			}
		}
	}

	private function date_heading($fixture_date) {
		if ($this->has_fixture) {
			echo "</tbody></table></div>\n";
		}
		$this->has_fixture = true;
		if ($this->query_type === 'date') {
			echo '<hr>';
		}
		echo "\n" . '<div class="scrollable"><table class="table-data fixtures-'
			. $this->query_type . '">';
		if ($this->query_type !== 'date') {
			echo '<caption><span class="caption-text">';
			if ($this->date_in_heading) {
				echo date('jS F ', strtotime($fixture_date)); // use full month here
			}
			echo substr($fixture_date,0,4) . "</span></caption>\n";
		}
		echo '<thead><tr>';
		if (!$this->date_in_heading) {
			echo '<th colspan="2" class="min-width">Date</th>';
		}
		echo '<th class="home">Home</th><th class="min-width"></th><th class="away">Away</th>';
		if ($this->show_competition) {
			echo '<th class="comp"><abbr title="Competition">Comp</abbr></th>';
		} else if ($this->show_round) {
			echo '<th class="comp"><abbr title="Round">Rnd</abbr></th>';
		}
		echo "</tr></thead>\n<tbody>\n";
	}

	private function td_team($team, $extra, $addLink = false) {
		if ($team && isset($this->options['team'][$team])) {
			if (!empty($this->options['team'][$team]) ) {
				$short = $this->options['team'][$team];
				if ($addLink && !empty($this->options['team_club'][$team])) {
					$uri = $this->options['team_club'][$team];
					echo "<td$extra><a class=\"tb-link no-ul\" href=\"/clubs/$uri\" data-sml-text=\"$short\"><span>$team</span></a></td>\n";
				} else {
					echo "<td$extra data-sml-text=\"$short\"><span>$team</span></td>\n";
				}
				return;
			}
			if ($addLink) {
				$uri = $this->options['team_club'][$team];
				echo "<td$extra><a class=\"tb-link no-ul\" href=\"/clubs/$uri\">$team</a></td>\n";
				return;
			}
		}
		echo "<td$extra>$team</td>";
	}

}
