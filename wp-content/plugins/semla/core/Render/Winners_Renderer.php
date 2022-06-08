<?php
namespace Semla\Render;
/**
 * Displaying winners
 */
class Winners_Renderer {
	private static $first_year;
	private static $prev_header;

	/**
	 * Group winners in a fixed width table, so all columns are always displayed
	 */
	public static function winners_fixed($type, $comps, $rows, $full_page) {
		$with_data = false;
		foreach ($rows as $row) {
			if ($row->has_data) {
				$with_data = true;
				break;
			}
		}
		if ($with_data) {
			self::click_instruction($type);
		}
		echo '<div class="scrollable"><table class="is-style-boxed-striped">'
			, "\n<thead><tr><th>Year</th>";
		foreach ($comps as $comp) {
			self::column_heading($comp);
		}
		echo "</tr></thead><tbody>\n";
		$last_year = 0;
		$comps_reverse = array_reverse($comps);
		foreach ($rows as $row) {
			if ($row->year !== $last_year) {
				if ($last_year !== 0) {
					self::do_fixed_year($last_year, $comps_reverse, $winners, $has_data, $full_page);
				}
				$winners = [];
				$has_data = false;
				$last_year = $row->year;
			}
			$winners[$row->comp_id] = $row->winner;
			if ($row->has_data) $has_data = true;
		}
		self::do_fixed_year($last_year, $comps_reverse, $winners, $has_data, $full_page);
		echo "</tbody></table></div>\n";
	}

	private static function do_fixed_year($year, $comps_reverse, $winners, $has_data, $full_page) {
		self::year_row_header($year,$full_page,$has_data);
		$row = [];
		$have_winners = false;
		foreach ($comps_reverse as $comp) {
			if (empty($winners[$comp->id])) {
				$winner = '';
			} else {
				$winner = $winners[$comp->id];
				$have_winners = true;
			}
			if ($have_winners || $winner) {
				array_unshift($row, $winner);
			}
		}
		echo '<td>', implode('</td><td>',$row) ,"</td></tr>\n";
	}

	private static function column_heading($comp) {
		echo '<th>';
		if (!empty($comp->history_page)) {
			echo "<a href=\"$comp->history_page\">$comp->name</a>";
		} else {
			echo $comp->name;
		}
		echo '</th>';
	}
	private static function year_row_header($year, $full_page, $has_data) {
		echo '<tr><th>';
		if ($has_data) {
			echo '<a href="' . $full_page . '-' . $year . '">' . $year . '</a>';
		} else {
			echo $year;
		}
		echo '</th>';
	}

	private static function click_instruction($type) {
		if ($type === 'league') {
			echo "<p>Click the year for full tables.</p>\n";
		} elseif ($type === 'cup') {
			echo "<p>Click the year for the complete draw.</p>\n";
		}
	}

	/**
	 * Group winners of a variable number of competitions. This will produce
	 * a new HTML table whenever the current year's combination of competitions
	 * changes.
	 */
	public static function winners_variable($type, $comps, $rows, $full_page) {
		self::$first_year = true;
		self::$prev_header = null;
		self::click_instruction($type);
		$prev_year = 0; // previous row's year
		foreach ($rows as $row) {
			if ($row->year <> $prev_year) {
				if ($prev_year <> 0) {
					echo self::do_variable_year
						($prev_year,$cols,$row_comps,$comps,$has_data,$full_page);
				}
				$row_comps = [];
				$cols = [];
				$prev_year = $row->year;
				$has_data = $row->has_data;
			}
			$row_comps[] = $row->comp_id;
			$cols[] = $row->winner;
		}
		echo self::do_variable_year($prev_year,$cols,$row_comps,$comps,$has_data,$full_page);
		echo "</tbody></table></div>\n";
	}
	
	private static function do_variable_year($year, $cols, $row_comps, $comps, $has_data, $full_page) {
		$header = implode(',',$row_comps);
		if ($header <> self::$prev_header) {
			if (self::$first_year) {
				self::$first_year = false;
			} else {
				echo "</tbody></table></div>\n";
			}
			echo '<div class="scrollable"><table class="is-style-boxed-striped">'
				. "\n<thead><tr><th>Year</th>";
			foreach ($row_comps as $comp_id) {
				self::column_heading($comps[$comp_id]);
			}
			echo "</tr></thead>\n<tbody>\n";
			self::$prev_header = $header;
		}
		self::year_row_header($year,$full_page,$has_data);
		echo '<td>' . implode('</td><td>',$cols) . "</td></tr>\n";
	}
}
