<div class="postbox">
    <div class="inside">
        <p>This page displays a set of rows for all Flags matches, which can be copied and pasted into the Fixtures Sheet. That way
            you can just enter Flags results on the Flags sheet, and they will automatically get copied over into the fixtures.
            Admittedly this is a bit of a hack, but this is a once a year thing that takes a few minutes, and automating it would be a pain!
            Copy the rows you need, and in the Fixtures Sheet insert as many rows as you need, and then in the Competition
            column right click->Paste Special->Paste Values Only, and repeat until you have copied all the Flags games into the
            correct position on the sheet.</p>
    	<p>Note: Google Sheets can be a bit flaky when pasting formulas, so if you end up with cells like "=Flags!$D$4"
	    	then create a blank sheet and paste all rows into into there, and then copy those rows into the Fixtures sheet.</p>
    </div>
</div>
<?php if ($rows) : ?>
<div class="postbox">
    <div class="inside">
        <pre><?php
$row_count = (count($rows));
$comp_start = 0;
$rounds_short = ['R64', 'R32', 'R16', 'QF','SF','F'];
$comp_offset = 3;
$output = [];
while ($comp_start < $row_count) {
    $comp_id = $rows[$comp_start]->comp_id;
    for ($comp_end = $comp_start+1; $comp_end < $row_count && $rows[$comp_end]->comp_id == $comp_id;
            $comp_end++);
    $rounds = $rows[$comp_end-1]->round;
    $round_offset = count($rounds_short) - $rounds - 1;
    $col = 3;
    for ($i = $comp_start; $i < $comp_end; $i++) {
        $row = $rows[$i];
        $date = explode('-', $row->match_date);
        $col = 3 + ($row->round - 1) * 5;
        $col_letter = chr(64+$col);
        $goal_col_letter = chr(65+$col);

        $row_no = 2**($row->round - 1) + (2**$row->round * ($row->match_num -1)) + $comp_offset;
        $team1 = $col_letter. '$' . $row_no;
        $team1_goals = $goal_col_letter . '$' . $row_no;
        $row_no++;
        $team2 = $col_letter . '$' . $row_no;
        $team2_goals = $goal_col_letter. '$' . $row_no;
        $result = $row->name . ' ' . $rounds_short[$row->round + $round_offset]
            . "\t$date[2]/$date[1]/$date[0]\t";
        if ($row->home_team = 1) {
            $result .= "\t=Flags!$$team1\t=Flags!$$team1_goals\tv\t=Flags!$$team2_goals\t=Flags!$$team2";
        } else {
            $result .= "\t=Flags!$$team2\t=Flags!$$team2_goals\tv\t=Flags!$$team1_goals\t=Flags!$$team1";
        }
        $output[$row->match_date][] = $result;
    }
    $comp_offset = $comp_offset + 6 + $comp_end - $comp_start;
    $comp_start = $comp_end;
}
ksort($output, SORT_STRING);
$first = true;
foreach($output as $k => $v) {
    if ($first) {
        $first = false;
    } else {
        echo "\n\n";
    }
    echo implode("\n",$v);
}
        ?></pre>
    </div>
</div>
<?php endif;
