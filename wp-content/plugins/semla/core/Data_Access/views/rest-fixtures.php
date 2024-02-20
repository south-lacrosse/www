<div class="sl-wrapper">
<table class="sl-fixtures">
<thead><tr><th>Date</th><th>Home</th><th></th><th>Away</th><th>Competition</th></tr></thead>
<tbody><?php

use Semla\Utils\Util;

$last_date = null;
foreach ($rows as $row) {
	if ($row->match_date !== $last_date) {
		$date = date('d M Y', strtotime($row->match_date));
		$last_date = $row->match_date;
	} else {
		$date = '';
	}
	if ($row->result) {
		$result = $row->result;
	} else {
		$result = Util::format_time($row->match_time);
		if ($row->pitch_type) {
			$result .= ' ' . $row->pitch_type;
		}
	}
	if ($row->points_multi > 1) {
		$result .= ' <sup>*' . $row->points_multi . '</sup>';
	}
	if (!$row->result && $row->venue) {
		$result .= "<br>at $row->venue";
	}
?>
<tr><td><?= $date ?></td><td><?= $row->home ?></td><td><?= $result ?></td><td><?= $row->away ?></td><td><?= $row->competition ?></td></tr>
<?php
} ?>
</tbody></table>
</div>
