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
				$ladder = false;
			} else {
				$ladder = true;
				$teams2 = explode('|', $division->teams2);
				$column_count = count($teams2);
			}
			// Don't put scrollable on the root div as the style for scrollable also makes the
			// offset for the sticky menu not work as a link target.
			echo '<div id="' . esc_attr(str_replace(' ','-',$division->section_name))
				. '" class="alignwide"><div class="scrollable">'
				. '<table class="table-data grid col-hover'
				. ($ladder ? ' ladder': '').  '"';
			if ($column_count < 8) { //
				echo ' style="max-width:' . ($column_count * 6 + 12) . 'em"';
			}
			echo  '><caption><span class="caption-text">'
				. $division->section_name . "</span></caption>\n"
				. '<thead><tr><th rowspan="2">';
			if (! $ladder) {
				echo 'Home</th><th colspan="' . $column_count . '">Away</th></tr><tr>';
				foreach (explode('|', $division->minimals) as $key => $minimal) {
					echo '<th><abbr title="' . htmlentities($teams[$key]) . '">' . htmlspecialchars($minimal, ENT_NOQUOTES) . '</abbr></th>';
				}
			} else {
				echo $divisions[$division->related_comp_id]->section_name . '</th><th colspan="'
					. $column_count . '">' . $divisions[$division->related_comp_id2]->section_name
					. '</th></tr><tr>';
				foreach (explode('|', $division->minimals2) as $key => $minimal) {
					echo '<th><abbr title="' . htmlentities($teams2[$key]) . '">' . htmlspecialchars($minimal, ENT_NOQUOTES) . '</abbr></th>';
				}
			}
			echo "</tr></thead>\n<tbody>\n";
			foreach ($teams as $home) {
				$esc_home = htmlspecialchars($home, ENT_NOQUOTES);
				echo '<tr><th>';
				if (! $ladder) {
					echo '<a class="no-ul" href="' . $fixtures_page . '?team='
						. urlencode($home) . '">' . $esc_home . '</a>';
				} else {
					echo $esc_home;
				}
				echo '</th>';
				if (! $ladder) {
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
				echo ' <sup>*' . $row->points_multi . '</sup>';
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
