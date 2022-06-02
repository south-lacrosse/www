<?php
$clubs_url = rest_url($request->get_route()) . '/';
$teams_url = substr($clubs_url, 0, -6) . 'teams/';
?>
<table><thead><tr><th>Club</th><th>Teams</th></tr></thead>
<tbody>
<?php foreach ($clubs as $name => $club) : ?>
<tr><td><a href="<?= $clubs_url . urlencode($name) ?>"><?= $name ?></a></td>
<td><?php
    $teams = [];
    foreach (explode('|', $club->teams) as $team) {
        $teams[] = '<a href="' . $teams_url . urlencode($team) . '">' . $team . '</a>';
    }
    echo implode(', ',$teams) ?></td></tr>
<?php endforeach; ?>
</tbody></table>
