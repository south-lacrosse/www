<?php
$leagues = [];
foreach ($divisions as $division) {
	$leagues[$division->league] = $division->page;
}
$do_tabs = count($leagues) > 1;
$first_tab = true;
if ($do_tabs) {
	echo '<ul class="tabs" role="tablist">', "\n";
	foreach ($leagues as $league => $tables_page) {
		if ($first_tab) {
			$active = ' active';
			$selected='true';
			$first_tab = false;
		} else {
			$active = '';
			$selected='false';
		}
		?>
<li class="tab-item" role="presentation">
<a class="tab<?= $active ?>" id="<?= $tables_page ?>-tab" data-toggle="tab" href="/<?= $tables_page ?>" role="tab" aria-controls="<?= $tables_page ?>-pane" aria-selected="<?= $selected ?>"><?= $league ?></a>
</li><?php
	}
	echo "</ul>\n<div class=\"tab-content\">\n";
}
$first_tab = true;
$last_league = '';
foreach ($rows as $row) {
	if ($row->league !== $last_league) {
		$tables_page = $leagues[$row->league];
		if ($last_league) {
			echo "</tbody></table>\n";
			if ($do_tabs) {
				echo "</div>\n";
			}
		}
		if ($do_tabs) {
			if ($first_tab) {
				$show = ' show';
				$first_tab = false;
			} else {
				$show = '';
			}
			echo '<div class="tab-pane', $show, '" id="', $tables_page,
				'-pane" role="tabpanel" aria-labelledby="', $tables_page,
				'-tab">', "\n";
		}
		$last_league = $row->league;
		$last_comp_id = 0;
	}
	if ($row->comp_id <> $last_comp_id) {
		if ($last_comp_id) echo "</tbody></table>\n";
		echo '<table class="table-data"><caption><a href="/', $tables_page, '#',
			Semla\Utils\Util::make_id($row->section_name), '" class="caption-text">',
			$row->section_name, '</a></caption><thead><tr>',
			'<th></th><th class="left">Team</th><th><abbr title="Matches played">P</abbr></th><th><abbr title="Points">Pts</abbr></th>',
			"</tr></thead>\n<tbody>\n";
		$last_comp_id = $row->comp_id;
	}
	echo '<tr', !empty($row->divider) ? ' class="divider"' : '', '><td>', $row->position, '</td><td class="left">',
		'<a class="no-ul font-semibold" href="fixtures?team=',
		urlencode($row->team), '">', htmlspecialchars($row->abbrev ? $row->abbrev : $row->team, ENT_NOQUOTES),
		'</a></td><td>', $row->played, '</td><td class="points">', floatval($row->points), '</td>',
		"</tr>\n";
}
echo "</tbody></table>\n";
if ($do_tabs) echo "</div>\n</div>\n";
