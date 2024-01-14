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
$year = date('Y');
$dtstamp = $year . '0101T060000Z';
$uuid = UUID::v5(UUID::NS_URL, "$team$dtstamp");
// Note: PHP removes line feeds after a closing short tag, therefore
// we need extra line feeds to work properly
$firstMonday = date("Ymd", strtotime("first monday of $year-01"));
?>
BEGIN:VEVENT
UID:<?= $uuid ?>@southlacrosse.org.uk
SUMMARY:Remove calendar subscription to SEMLA
  <?= $team ?> Fixtures
DESCRIPTION:You are subscribed to the SEMLA
  <?= $team ?>

  Fixtures calendar\, however that team is no longer entered in the
  league and has no fixtures. Please unsubscribe from this calendar.
  Thank you.
 \n\nTo subscribe to another team's calendar visit
  https://www.southlacrosse.org.uk/fixtures and select your team\,
  then scroll down for the Subscribe information.
DTSTART;TZID=Europe/London:<?= $firstMonday ?>T090000
DTEND;TZID=Europe/London:<?= $firstMonday ?>T100000
DTSTAMP:<?= $dtstamp ?>

RRULE:FREQ=WEEKLY;INTERVAL=1;UNTIL=<?= $year ?>1231T235900Z
END:VEVENT
END:VCALENDAR