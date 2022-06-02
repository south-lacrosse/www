<?php
namespace Semla\Render;
use Semla\Utils\Util;
/**
 * Displaying league tables
 */
class Table_Renderer {
	public static function year_navigation($page,$grid_page,$year,$years) {
		if (!$years->next && !$years->prev) return;
		echo '<nav class="hist-nav" role="navigation">'
			. '<h2 class="screen-reader-text">Tables navigation</h2>'
			. "\n";
		if ($years->prev || $grid_page) {
			echo  '<div class="left-nav">';
			if ($years->prev) {
				echo '<a href="' . $page
					. '-' . $years->prev . '">« ' . $years->prev . ' </a>';
			} else {
				echo '&nbsp;';
			}
			echo  '</div>' . "\n";
		}
		if ($grid_page) {
			echo '<div class="center-nav"><a href="'
				. str_replace('fixtures','results',$grid_page)
				. '-' . $year . '">Results Grid</a></div>' . "\n";
		}
		if ($years->next) {
			echo '<div class="right-nav"><a href="' . $page
				. '-' . $years->next .'">' . $years->next
				. ' »</a></div>';
		}
		echo "</nav>\n";
	}

	public static function tables($rows, $year = 0, $remarks = null, $links = false) {
		if (count($rows) === 0) {
			echo '<p>No League data</p>';
			return;
		}
		$comp_id = 0;
		$teams = [];
		foreach ( $rows as $row ) {
			if ($row->comp_id <> $comp_id) {
				if ($comp_id) {
					echo self::table($teams[0]->name, $teams, $year, $links);
				}
				if (isset($remarks[$comp_id])) {
					echo '<p>' . $remarks[$comp_id]->remarks . '</p>';
				}
				$comp_id = $row->comp_id;
				$teams = [];
			}
			$teams[] = $row;
		}
		if ($comp_id) {
			echo self::table($teams[0]->name, $teams, $year,$links);
			if (isset($remarks[$comp_id])) {
				echo '<p>' . $remarks[$comp_id]->remarks . '</p>';
			}
		}
	}
	
	/**
	 * Render a single league table
	 * @param string $division_name
	 * @param array $teams teams in table, already sorted
	 * @param number $year, 0 for current
	 * @return string
	 */
	private static function table($division_name, $teams, $year, $links ) {
		$team0 = $teams[0];
		$wdl_cols = $team0->won > 0 || !$year ? true : false;
		$fa_cols = $team0->goals_for > 0 ? true : false;
		$points_col = $team0->points > 0 || !$year ? true : false;
		$points_avg_col = isset($team0->points_avg) ? true : false;
		$goal_avg_col = $team0->goal_avg ? true : false;
		$form_col = isset($team0->form) ? true : false;
		$deducted_col = false;
		foreach ( $teams as $team ) {
			if ($team->points_deducted > 0)
				$deducted_col = true;
		}
		
		// Note: id is not put on table as we can't style the table to cater for the position="sticky"
		// menu (by default the target will be at the top of the viewport, but this is underneath the
		// sticky menu), so we add a div as the target.
		// Also we can't put scrollable on that div as the style for scrollable also makes the
		// offset for the sticky menu not work.
		echo '<div id="' . Util::make_id($division_name) . '"><div class="scrollable">'
			. '<table class="table-data "><caption><span class="caption-text">'	. $division_name . "</span></caption>\n<thead><tr>"
			. '<th></th><th class="left">Team</th><th><abbr title="Matches played">P</abbr></th>';
		if ($wdl_cols)
			echo '<th><abbr title="Matches won">W</abbr></th><th><abbr title="Matches drawn">D</abbr></th>'
				. '<th><abbr title="Matches lost">L</abbr></th>';
		if ($fa_cols)
			echo '<th class="hide-sml"><abbr title="Goals for">F</abbr></th>'
				. '<th class="hide-sml"><abbr title="Goals against">A</abbr></th>'
				. '<th class="hide-sml"><abbr title="Goal difference">GD</abbr></th>';
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
			echo '<tr' . (!empty($team->divider) ? ' class="divider"' : '') .  '><td>' . $team->position . '</td><td class="left">';
			if (!$links) {
				echo $team->team;
			} elseif ($year == 0) {
				echo '<a class="tb-link" href="/fixtures?team='
					. urlencode($team->team) . '">' . $team->team . '</a>';
			} elseif ($year >= 2003) {
				echo '<a class="tb-link" href="results-' . $year . '?team='
					. urlencode($team->team) . '">' . $team->team . '</a>';
			} else {
				echo $team->team;
			}
			echo '</td><td>' . $team->played . '</td>';
			if ($wdl_cols)
				echo '<td>' . $team->won . '</td><td>' . $team->drawn . '</td><td>' . $team->lost . '</td>';
			if ($fa_cols)
				echo '<td class="hide-sml">' . $team->goals_for . '</td><td class="hide-sml">' . $team->goals_against
					. '</td><td class="hide-sml">' . ($team->goals_for - $team->goals_against) . '</td>';
			if ($goal_avg_col) {
				if (isset($team->goal_avg)) {
					$val = number_format($team->goal_avg,2);
				} else {
					$val = '';
				}
				echo '<td class="hide-sml">' . $val . '</td>';
			}
			if ($deducted_col)
				echo '<td>' . ($team->points_deducted > 0 ? floatval($team->points_deducted) : '') . '</td>';
			if ($points_col)
				echo '<td>' . floatval($team->points) . '</td>';
			if ($points_avg_col)
				echo '<td class="hide-sml">' . number_format($team->points_avg, 2) . '</td>';
			if ($form_col) {
				echo '<td class="hide-sml">' . (isset($team->form) ? substr($team->form,-5) : '') . '</td>';
			}
			echo "</tr>\n";
		}
		echo "</tbody></table></div></div>\n";
	}
}
