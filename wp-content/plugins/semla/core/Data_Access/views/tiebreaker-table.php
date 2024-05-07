<?php
$cols = ['New Pos', 'Orig Pos', 'Team', 'H2H Points', 'H2H GD', 'H2H G', 'GD', 'G'];
$last_comp = '';
$last_points = 0;
foreach ($rows as $row) {
	$comp = $row['name'];
	unset($row['name']);
	$orig_points =  $row['original_points'];
	unset($row['original_points']);
	if ($comp !== $last_comp || $orig_points !== $last_points ) {
		if ($last_comp) {
			echo "\n</tbody></table>";
		}
		$last_comp = $comp;
		$last_points = $orig_points;
?>
<h2>Tiebreaker for <?= $comp ?>, <?= $orig_points ?> points</h2>
<table class="widefat striped" role="presentation">
<thead><tr><td><?php echo implode('</td><td>', $cols); ?></td></tr></thead>
<tbody>
<?php
	}
	echo "\n<tr><td>", implode('</td><td>', $row), '</td></tr>';
} ?>
</tbody></table>
