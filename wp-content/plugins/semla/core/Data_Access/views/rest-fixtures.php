<div class="sl-wrapper">
<table class="sl-fixtures">
<thead><tr><th>Date</th><th>Home</th><th></th><th>Away</th><th>Competition</th></tr></thead>
<tbody><?php
$last_date = null;
foreach ($rows as $row) {
    if ($row->match_date !== $last_date) {
        $date = date('d M Y', strtotime($row->match_date));
        $last_date = $row->match_date;
    } else {
        $date = '';
    }
    if ($row->result == '') {
        $time = explode(':',$row->match_time);
        if ($time[0] > 12) {
            $ampm = 'pm';
            $time[0] -= 12;
        } elseif ($row->match_time === '12:00:00') {
            $ampm = 'pm';
        } else {
            $ampm = 'am';
        }
        $result = $time[0];
        if ($time[1] !== '00') {
            $result .= ':' . $time[1];
        }
        $result .= $ampm;
        if ($row->pitch_type) {
            $result .= ' ' . $row->pitch_type;
        }
        if ($row->venue) {
            $result .= "<br>at $row->venue";
        }
    } else {
        $result = $row->result;
    }
    ?>
<tr><td><?= $date ?></td><td><?= $row->home ?></td><td><?= $result ?></td><td><?= $row->away ?></td><td><?= $row->competition ?></td></tr>
<?php
} ?>
</tbody></table>
</div>
