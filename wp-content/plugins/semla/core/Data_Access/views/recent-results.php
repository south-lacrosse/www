<?php
use Semla\Utils\Util;

$last_date = '';
foreach ( $rows as $row ) {
	if ($row->match_date !== $last_date) {
		$last_date = $row->match_date;
		$first_fixture_in_date = true;
		echo '<h2>', date('jS F Y', strtotime($last_date)), // use full month here
			"</h2>\n";
	}
	if ($first_fixture_in_date) {
		$first_fixture_in_date = false;
	} else {
		echo '<br>';
	}
	$home = $row->home;
	if (!str_starts_with($home, 'TBD') && is_numeric(substr($home, -1))) {
		$home .= 's';
	}
	$home = htmlspecialchars($home, ENT_NOQUOTES);
	$away = $row->away;
	if (!str_starts_with($away, 'TBD') && is_numeric(substr($away, -1))) {
		$away .= 's';
	}
	$away = htmlspecialchars($away, ENT_NOQUOTES);
	if ($row->result && $row->result !== 'R - R' && !ctype_digit($row->result[0])) {
		echo "$row->competition $home v $away $row->result";
	} else {
		$result = $row->result ? $row->result : 'v';
		echo "$row->competition $home $result $away";
	}
	if (!$row->result) {
		$extra = '';
		if ($row->match_time !== '14:00:00') {
			$extra = Util::format_time($row->match_time);
		}
		if ($row->venue) {
			if ($extra) $extra .= ' ';
			$extra .= 'at ' . htmlspecialchars($row->venue, ENT_NOQUOTES);
		}
		if ($extra) {
			echo " ($extra)";
		}
	}
}
