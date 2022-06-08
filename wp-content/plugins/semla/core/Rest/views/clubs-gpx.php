<?php
/**
 * Handle GPS Exchange Format xml for club locations.
 */
?>
<gpx xmlns="http://www.topografix.com/GPX/1/1" creator="SEMLA" version="1.1">
<metadata><name>Locations of all clubs in the South of England Men's Lacrosse Association</name></metadata>
<?php
while ($query->have_posts()) {
	$query->the_post();
	if (preg_match_all('/"latLong":"([^"]*)"/', get_the_content(), $matches)) {
		$ground = 0;
		foreach ($matches[1] as $lat_lng) {
			$lat_lng = explode('%2C' , esc_attr($lat_lng));
			echo '<wpt lon="' , $lat_lng[0] , '" lat="' , $lat_lng[1] , '">' , "\n<name>";
			the_title();
			echo " Lacrosse Club</name>\n";
			if ($ground) {
				echo "<desc>Alternative ground $ground</desc>\n";
			}
			echo "</wpt>\n";
			$ground++;
		}
	}
}
?></gpx>
