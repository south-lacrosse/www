BEGIN:VCALENDAR
PRODID:-//SEMLA//Fixtures Calendar 1.0//EN
VERSION:2.0
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:<?= $team ?> Fixtures
X-WR-TIMEZONE:Europe/London
BEGIN:VTIMEZONE
TZID:Europe/London
X-LIC-LOCATION:Europe/London
BEGIN:DAYLIGHT
TZOFFSETFROM:+0000
TZOFFSETTO:+0100
TZNAME:BST
DTSTART:19700329T010000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:+0100
TZOFFSETTO:+0000
TZNAME:GMT
DTSTART:19701025T020000
RRULE:FREQ=YEARLY;BYDAY=-1SU;BYMONTH=10
END:STANDARD
END:VTIMEZONE
<?php
use Semla\Utils\UUID;

if ($rows) {
    $date = explode('-',$rows[0]->match_date);
    if ($date[1] < 6) {
        $date[0]--;
    }
    $dtstamp = $date[0] . '0901T120000Z';
    foreach ($rows as $row) {
        $summary = $row->home ? $row->home : '?';
        $summary .= ' v ';
        $summary .= $row->away ? $row->away : '?';
        if ($row->venue) {
            $summary .= " at $row->venue";
        }
        $summary .= ' (';
        if ($row->pitch_type) {
            $summary .= $row->pitch_type . ', ';
        }
        $summary .= "$row->competition)";
        $uuid = UUID::v5(UUID::NS_URL, "$team$row->match_date$row->match_time");
        $start = strtotime($row->match_time);
        $end = $start + 7200; // +2 hours
        $date = str_replace('-','',$row->match_date);
// Note: PHP removes line feeds after a closing short tag, therefore
// we need extra line feeds to work properly
?>
BEGIN:VEVENT
UID:<?= $uuid ?>@southlacrosse.org.uk
SUMMARY:<?= $summary ?>

DTSTART;TZID=Europe/London:<?= $date . date('\THis', $start); ?>

DTEND;TZID=Europe/London:<?= $date . date('\THis', $end);?>

DTSTAMP:<?= $dtstamp ?>

END:VEVENT
<?php }
} ?>
END:VCALENDAR