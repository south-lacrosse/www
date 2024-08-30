<?php
use Semla\Rest\Rest;

$clubs_url = rest_url($request->get_route()) . '/';
$teams_url = substr($clubs_url, 0, -6) . 'teams/';
?>
<table><thead><tr><th>Club</th><th>Teams</th></tr></thead>
<tbody>
<?php foreach ($clubs as $name => $club) : ?>
<tr><td><a href="<?= $clubs_url . Rest::encode_club_team($name) ?>"><?= $name ?></a></td>
<td><?php
	$teams = [];
	foreach (explode('|', $club->teams) as $team) {
		$teams[] = '<a href="' . $teams_url . Rest::encode_club_team($team)
			. '">' . htmlspecialchars($team, ENT_NOQUOTES) . '</a>';
	}
	echo implode(', ',$teams) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
