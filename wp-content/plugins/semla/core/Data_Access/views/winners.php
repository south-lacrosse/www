<?php
$rup = false;
$wins = [];
$with_data = false;
foreach ($rows as $row) {
	if ($row->runner_up) {
		$rup = true;
		if (!$comp->head_to_head && !$comp->link_to_draws) break;
	}
	if ($comp->head_to_head) {
		if (isset($wins[$row->winner])) {
			$wins[$row->winner]++;
		} else {
			$wins[$row->winner] = 1;
		}
	}
	if ($row->has_data) $with_data = true;
}
if ($comp->group_id && $next_prevs) {
	$prev = $next = '';
	foreach ($next_prevs as $next_prev) {
		if ($next_prev->class === 'p') {
			$prev = "<a href=\"$next_prev->history_page\" rel=\"prev\">« $next_prev->name</a>";
		} else {
			$next = "<a href=\"$next_prev->history_page\" rel=\"next\">$next_prev->name »</a>";
		}
	}
	if ($next) {
		if ($prev) {
			$nav_class = ' prev-next';
		} else {
			// single next link, so make sure the text is right justified
			$nav_class = ' right';
		}
	} else {
		$nav_class = '';
	}
	echo '<nav class="hist-nav', $nav_class,
		'" aria-label="Competitions"><h2 class="screen-reader-text">Competition navigation</h2>', "\n",
		$prev, $next, "\n</nav>\n";
}
if ($with_data) {
	echo "<p class=\"no-print\">Click the year for the complete draw.</p>\n";
}
if ($comp->head_to_head && count($wins) > 0) {
	arsort($wins);
	$total = 0;
	echo '<p>';
	foreach ($wins as $winner => $count) {
		if ($total !== 0) {
			echo ', ';
		}
		echo htmlspecialchars($winner, ENT_NOQUOTES), ' ', $count;
		$total += $count;
	}
	echo ' (Total: ', $total, ")</p>\n";
}
echo '<div class="scrollable"><table class="is-style-boxed-striped">', "\n",
	'<thead><tr><th>Year</th><th>Winner</th>';
if ($rup) {
	echo '<th class="center">Result</th><th>Runner-up</th>';
}
echo "</thead><tbody>\n";
foreach ($rows as $row) {
	echo '<tr><th>';
	if ($row->has_data) {
		echo "<a href=\"$comp->group_history_page-$row->year$fragment\">$row->year</a>";
	} else {
		echo $row->year;
	}
	echo '</th><td>', htmlspecialchars($row->winner, ENT_NOQUOTES), '</td>';
	if ($rup) {
		echo $row->result ? '<td class="center">' : '<td>', $row->result, '</td><td>',
			$row->runner_up ? htmlspecialchars($row->runner_up, ENT_NOQUOTES) : '',
			'</td>';
	}
	echo "</tr>\n";
}
echo "</tbody></table></div>\n";
