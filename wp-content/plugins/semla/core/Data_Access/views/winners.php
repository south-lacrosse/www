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
	echo '<nav class="hist-nav"><h2 class="screen-reader-text">Competition navigation</h2>', "\n";
	foreach ($next_prevs as $next_prev) {
		if ($next_prev->class === 'p') {
			$class = 'nav-previous';
			$name = "« $next_prev->name";
		} else {
			$class = 'nav-next';
			$name = "$next_prev->name »";
		}
		echo "<div class=\"$class\"><a href=\"$next_prev->history_page\">$name</a></div>\n";
	}
	echo "</nav>\n";
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
		echo htmlspecialchars($winner, ENT_NOQUOTES) . ' ' . $count;
		$total += $count;
	}
	echo ' (Total: ' .$total  . ")</p>\n";
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
	echo '</th><td>' . htmlspecialchars($row->winner, ENT_NOQUOTES) . '</td>';
	if ($rup) {
		echo ($row->result ? '<td class="center">' : '<td>'), $row->result, '</td><td>'
			. ($row->runner_up ? htmlspecialchars($row->runner_up, ENT_NOQUOTES) : '')
			. '</td>';
	}
	echo "</tr>\n";
}
echo "</tbody></table></div>\n";
