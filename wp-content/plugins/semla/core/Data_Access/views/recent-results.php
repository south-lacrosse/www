<?php

use Semla\Utils\Util;

$last_date = '';
foreach ( $rows as $row ) {
	if ($row->match_date !== $last_date) {
		$last_date = $row->match_date;
		$first_fixture_in_date = true;
		echo '<h2>' . date('jS F Y', strtotime($last_date)) // use full month here
			. "</h2>\n";
	}
	if ($first_fixture_in_date) {
		$first_fixture_in_date = false;
	} else {
		echo '<br>';
	}
	$result = $row->result ? $row->result : 'v';
	$home = $row->home;
	if (is_numeric(substr($home, -1))) {
		$home .= 's';
	}
	if (is_numeric(substr($row->away, -1))) {
		$row->away .= 's';
	}
	if ($row->result && $row->result !== 'R - R' && !ctype_digit($row->result[0])) {
		echo "$row->competition $home v $row->away $row->result";
	} else {
		echo "$row->competition $home $result $row->away";
	}
	if (!$row->result) {
		$extra = '';
		if ($row->match_time !== '14:00:00') {
			$extra = Util::format_time($row->match_time);
		}
		if ($row->venue) {
			if ($extra) $extra .= ' ';
			$extra .= "at $row->venue";
		}
		if ($extra) {
			echo " ($extra)";
		}
	}
}
