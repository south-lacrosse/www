<?php
$fixtures_page = $year ? "results-$year" : 'fixtures';
$keys = [];
foreach ($divisions as $division) {
	$teams = explode('|', $division->teams);
	// Note: id is not put on table as we can't style the table to cater for the position="sticky"
	// menu (by default the target will be at the top of the viewport, but this is underneath the
	// sticky menu), so we add a div as the target.
	// Also we can't put scrollable on that div as the style for scrollable also makes the
	// offset for the sticky menu not work.
	echo '<div id="' . esc_attr(str_replace(' ','-',$division->section_name))
		. '"><div class="scrollable"><table class="table-data grid col-hover"><caption><span class="caption-text">'
		. $division->section_name . "</span></caption>\n" . '<thead><tr><th class="no-bb"></th><th colspan="'
		. count($teams) . '">Away</th></tr><tr><th>Home</th>';
	foreach (explode('|', $division->minimals) as $key => $minimal) {
		echo '<th><abbr title="' . $teams[$key] . '">' . $minimal . '</abbr></th>';
	}
	echo "</tr></thead>\n<tbody>\n";
	foreach ($teams as $home) {
		echo '<tr><td class="left"><a class="tb-link" href="' . $fixtures_page . '?team='
			. urlencode($home) . '">' . $home . '</a></td>';
		foreach ($teams as $away) {
			$key = "$division->comp_id|$home|$away";
			echo '<td>';
			if ($home !== $away) {
				if (isset($fixtures[$key])) {
					$done = 0;
					foreach ($fixtures[$key] as $row) {
						if ($done) echo '<br>';
						$done = true;
						if ($row->result) {
							if ($row->result[0] === 'C') {
								echo '<abbr title="Cancelled">Canc.</abbr>';
								$keys['C'] = '<i>Canc.</i> = Cancelled';
							} else {
								echo $row->result;
							}
						} else {
							echo date('d M', strtotime($row->match_date));
						}
						if ($row->points_multi > 1) {
							echo ' <sup>*2</sup>';
							$keys['m'] = '<i>*2</i> = multiple points';
						}
					}
				} else if (isset($postponed_fixtures[$key])) {
					switch ($postponed_fixtures[$key][0]) {
						case 'R':
							echo '<abbr title="Rearranged/postponed">R - R</abbr>';
							$keys['R'] = '<i>R - R</i> = Rearranged/postponed';
							break;
						case 'A' :
							echo '<abbr title="Abandoned">Aband.</abbr>';
							$keys['A'] = '<i>Aband.</i> = Abandoned';
							break;
						default:
							echo $postponed_fixtures[$key];
					}
				}
			}
			echo '</td>';
		}
		echo "</tr>\n";
	}
	echo "</tbody></table></div></div>\n";
}
if (!empty($keys)) {
	echo '<p><b>Key:</b> ' . implode(', ', $keys) . "</p>\n";
}
