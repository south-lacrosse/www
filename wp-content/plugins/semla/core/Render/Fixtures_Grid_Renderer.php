<?php
namespace Semla\Render;

class Fixtures_Grid_Renderer {
	private static $keys;

	public static function grid($year, $divisions, $fixtures, $postponed_fixtures) {
		$fixtures_page = $year ? "results-$year" : 'fixtures';
		self::$keys = [];
		foreach ($divisions as $division) {
			$teams = explode('|', $division->teams);
			if (empty($division->teams2)) {
				$column_count = count($teams);
				$not_ladder = true;
			} else {
				$not_ladder = false;
				$teams2 = explode('|', $division->teams2);
				$column_count = count($teams2);
			}
			// Don't put scrollable on the root div as the style for scrollable also makes the
			// offset for the sticky menu not work as a link target.
			echo '<div id="' . esc_attr(str_replace(' ','-',$division->section_name))
				. '" class="alignwide"><div class="scrollable">'
				. '<table class="table-data grid col-hover"';
			if ($column_count < 8) { //
				echo ' style="max-width:' . ($column_count * 6 + 12) . 'em"';
			}
			echo  '><caption><span class="caption-text">'
				. $division->section_name . "</span></caption>\n"
				. '<thead><tr><th class="no-bb"></th><th colspan="';
			if ($not_ladder) {
				echo $column_count . '">Away</th></tr><tr><th>Home</th>';
				foreach (explode('|', $division->minimals) as $key => $minimal) {
					echo '<th><abbr title="' . $teams[$key] . '">' . $minimal . '</abbr></th>';
				}
			} else {
				echo $column_count . '">' . $divisions[$division->ladder_comp_id2]->section_name
					. '</th></tr><tr><th>' . $divisions[$division->ladder_comp_id1]->section_name . '</th>';
				foreach (explode('|', $division->minimals2) as $key => $minimal) {
					echo '<th><abbr title="' . $teams2[$key] . '">' . $minimal . '</abbr></th>';
				}
			}
			echo "</tr></thead>\n<tbody>\n";
			foreach ($teams as $home) {
				echo '<tr><td class="left">';
				if ($not_ladder) {
					echo '<a class="tb-link" href="' . $fixtures_page . '?team='
						. urlencode($home) . '">' . $home . '</a>';
				} else {
					echo $home;
				}
				echo '</td>';
				if ($not_ladder) {
					foreach ($teams as $away) {
						$key = "$division->id|$home|$away";
						echo '<td>';
						if ($home !== $away) {
							if (isset($fixtures[$key])) {
								self::render_fixtures($fixtures[$key]);
							} else if (isset($postponed_fixtures[$key])) {
								self::render_postponed($postponed_fixtures[$key]);
							}
						}
						echo '</td>';
					}
				} else {
					foreach ($teams2 as $away) {
						$key = "$division->id|$home|$away";
						$key2 = "$division->id|$away|$home";
						echo '<td>';
						$away_fixtures = $fixtures[$key2] ?? [];
						foreach ($away_fixtures as $row) {
							if ($row->result) {
								$row->result = preg_replace('/(\d*) - (\d*)/', '$2 - $1',$row->result);
							}
							$row->extra = ' (A)';
						}
						$cell_fixtures = array_merge($fixtures[$key] ?? [], $away_fixtures);
						if ($cell_fixtures) {
							self::render_fixtures($cell_fixtures);
						} else if (isset($postponed_fixtures[$key])) {
							self::render_postponed($postponed_fixtures[$key]);
						} else if (isset($postponed_fixtures[$key2])) {
							self::render_postponed($postponed_fixtures[$key2], ' (A)');
						}
						echo '</td>';
					}
				}
				echo "</tr>\n";
			}
			echo "</tbody></table></div></div>\n";
		}
		if (self::$keys) {
			ksort(self::$keys);
			echo '<p><b>Key:</b> ' . implode(', ', self::$keys) . "</p>\n";
		}
	}

	private static function render_fixtures($rows) {
		$shown = 0;
		foreach ($rows as $row) {
			if ($shown) echo '<br>';
			$shown = true;
			if ($row->result) {
				if ($row->result[0] === 'C') {
					echo 'Canc.';
					self::$keys['C'] = '<i>Canc.</i> = Cancelled';
				} else {
					if (str_contains($row->result, 'w/o')) {
						self::$keys['W'] = '<i>w/o</i> = walkover';
					}
					echo $row->result;
				}
			} else {
				echo date('d M', strtotime($row->match_date));
			}
			if ($row->points_multi > 1) {
				echo ' <sup>*2</sup>';
				self::$keys['*'] = '<i>*2</i> = multiple points';
			}
			if (!empty($row->extra)) echo $row->extra;
		}
	}

	private static function render_postponed($postponed_fixture, $extra = '') {
		switch ($postponed_fixture[0]) {
			case 'R':
				echo 'R - R';
				self::$keys['R'] = '<i>R - R</i> = Rearranged/postponed';
				break;
			case 'A' :
				echo 'Aband.';
				self::$keys['A'] = '<i>Aband.</i> = Abandoned';
				break;
			default:
				echo $postponed_fixture;
		}
		echo $extra;
	}
}
