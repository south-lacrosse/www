<?php
namespace Semla\Render;
use Semla\Utils\Util;
/**
 * Displaying league tables
 */
class Table_Renderer {
	/**
	 * @param string $page base slug for page, next/prev links will be this + -year
	 * @param string $grid_page fixtures grid page, if there are fixtures for the year
	 * @param int $year
	 * @param object $years previous and next years for navigation
	 */
	public static function year_navigation($page,$grid_page,$year,$years) {
		if (!$years->next && !$years->prev && !$grid_page) return;

		if ($grid_page) {
			if ($years->next || $years->prev) {
				$nav_class = ' prev-center-next';
			} else {
				$nav_class = '';
			}
		} else {
			if ($years->next) {
				if ($years->prev) {
					$nav_class = ' prev-next';
				} else {
					// single next link, so make sure the text is right justified
					$nav_class = ' right';
				}
			} else {
				$nav_class = '';
			}
		}
		echo '<nav class="hist-nav', $nav_class,
			'" aria-label="Tables"><h2 class="screen-reader-text">Tables navigation</h2>', "\n";
		if ($years->prev) {
			echo '<a href="', $page, '-', $years->prev, '" rel="prev">« ',
				$years->prev, "</a>";
		} elseif ($grid_page) {
			echo "<div></div>";
		}
		if ($grid_page) {
			echo '<a class="center" href="',
				str_replace('fixtures','results',$grid_page),
				'-', $year, '">Results Grid</a>';
		}
		if ($years->next) {
			echo '<a href="', $page, '-', $years->next, '" rel="next">',
				$years->next, ' »</a>';
		}
		echo "\n</nav>\n";
	}

	/**
	 * Render tables
	 * @param array  $rows rows of all tables to generate
	 * @param string $format format to generate (only minor differences), can be
	 *               league, cup, or rest
	 * @param int    $year
	 * @param array  $remarks array of strings keyed by competition id
	 */
	public static function tables($rows, $format, $year = 0, $remarks = null) {
		if (count($rows) === 0) {
			echo '<p>No League data</p>';
			return;
		}
		$comp_id = 0;
		$teams = [];
		$has_tiebreaker = 0;
		foreach ( $rows as $row ) {
			if ($row->comp_id <> $comp_id) {
				if ($comp_id) {
					echo self::table($teams[0]->name, $teams, $year, $format);
				}
				if (isset($remarks[$comp_id])) {
					echo '<p>', $remarks[$comp_id]->remarks, '</p>';
				}
				$comp_id = $row->comp_id;
				$teams = [];
			}
			$teams[] = $row;
			if ($row->tiebreaker) {
				$has_tiebreaker = true;
			}
		}
		if ($comp_id) {
			echo self::table($teams[0]->name, $teams, $year, $format);
			if (isset($remarks[$comp_id])) {
				echo '<p>', $remarks[$comp_id]->remarks, '</p>';
			}
		}
		if ($has_tiebreaker) {
			echo "\n<p><i>*</i> = position changed because of tie-break rules</p>";
		}
	}

	/**
	 * Render a single league table
	 */
	private static function table($division_name, $teams, $year, $format) {
		$team0 = $teams[0];
		$wdl_cols = $team0->won > 0 || !$year;
		$fa_cols = $team0->goals_for > 0;
		$points_col = $team0->points > 0 || !$year;
		$points_avg_col = isset($team0->points_avg);
		$goal_avg_col = $team0->goal_avg > 0;
		$form_col = $format !== 'cup' && !empty($team0->form);
		$deducted_col = false;
		foreach ( $teams as $team ) {
			if ($team->points_deducted > 0) {
				$deducted_col = true;
				break;
			}
		}
		if ($format === 'rest') {
			$class = '';
		} elseif ($format === 'cup') {
			$class = ' league-table';
		} elseif ($year === 0) {
			$class = ' league-table-current';
		} elseif (!$wdl_cols && !$fa_cols) {
			$class = ' league-table-thin';
		} else {
			$class = ' league-table';
		}

		// Don't need outer div wrapper for cups as we don't want the id (it will be on the main
		// competition, and not on the group table), and the enclosing section is already alignwide
		if ($format !== 'cup') {
			// Note: id is not put on table as we can't style the table to cater for the position="sticky"
			// menu (by default the target will be at the top of the viewport, but this is underneath the
			// sticky menu), so we add a div as the target.
			// Also we can't put scrollable on that div as the style for scrollable also makes the
			// offset for the sticky menu not work.
			echo '<div id="', Util::make_id($division_name), '"',
				$format === 'rest' ? '' : ' class="alignwide"', '>';
		}
		echo '<div class="scrollable"><table class="table-data', $class,
			'"><caption><span class="caption-text">',
			"$division_name</span></caption>\n<thead><tr>",
			'<th></th><th class="left">Team</th><th><abbr title="Matches played">P</abbr></th>';
		if ($wdl_cols)
			echo '<th><abbr title="Matches won">W</abbr></th><th><abbr title="Matches drawn">D</abbr></th>',
				'<th><abbr title="Matches lost">L</abbr></th>';
		if ($fa_cols)
			echo '<th class="hide-sml"><abbr title="Goals for">F</abbr></th>',
				'<th class="hide-sml"><abbr title="Goals against">A</abbr></th>',
				'<th class="hide-sml"><abbr title="Goal difference">GD</abbr></th>';
		if ($goal_avg_col)
			echo '<th class="hide-sml"><abbr title="Goal average">GAvg</abbr></th>';
		if ($deducted_col)
			echo '<th><abbr title="Points deducted">-</abbr></th>';
		if ($points_col)
			echo '<th><abbr title="Points">Pts</abbr></th>';
		if ($points_avg_col)
			echo '<th class="hide-sml"><abbr title="Points average">PtsAvg</abbr></th>';
		if ($form_col)
			echo '<th class="hide-sml">Form</th>';
		echo "</tr></thead>\n<tbody>\n";
		foreach ($teams as $team) {
			echo '<tr', !empty($team->divider) ? ' class="divider"' : '',
				'><td>', $team->position, '</td><td class="left">';
			$esc_team = htmlspecialchars($team->team, ENT_NOQUOTES);
			if ($format === 'rest') {
				echo $esc_team;
			} elseif ($year == 0) {
				echo '<a class="no-ul font-semibold" href="/fixtures?team=',
					urlencode($team->team), '">', $esc_team, '</a>';
			} elseif ($year >= 2003) {
				echo '<a class="no-ul font-semibold" href="results-', $year, '?team=',
					urlencode($team->team), '">', $esc_team, '</a>';
			} else {
				echo $esc_team;
			}
			if ($team->tiebreaker) echo '<sup>*</sup>';
			echo '</td><td>', $team->played, '</td>';
			if ($wdl_cols)
				echo '<td>', $team->won, '</td><td>', $team->drawn, '</td><td>',
					$team->lost, '</td>';
			if ($fa_cols)
				echo '<td class="hide-sml">', $team->goals_for,
					'</td><td class="hide-sml">', $team->goals_against,
					'</td><td class="hide-sml">', ($team->goals_for - $team->goals_against),
					'</td>';
			if ($goal_avg_col) {
				if (isset($team->goal_avg)) {
					$val = number_format($team->goal_avg,2);
				} else {
					$val = '';
				}
				echo '<td class="hide-sml">', $val, '</td>';
			}
			if ($deducted_col)
				echo '<td>', $team->points_deducted > 0 ? floatval($team->points_deducted) : '', '</td>';
			if ($points_col)
				echo '<td class="', $format === 'rest' ? 'sl-' : '', 'points">', floatval($team->points), '</td>';
			if ($points_avg_col)
				echo '<td class="hide-sml">', number_format($team->points_avg, 2), '</td>';
			if ($form_col) {
				echo '<td class="hide-sml">', isset($team->form) ? substr($team->form,-5) : '', '</td>';
			}
			echo "</tr>\n";
		}
		echo '</tbody></table></div>', $format !== 'cup' ? '</div>' : '', "\n";
	}
}
