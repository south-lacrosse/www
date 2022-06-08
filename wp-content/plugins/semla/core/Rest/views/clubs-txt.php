<?php
/**
 * Handle Text format for clubs
 */
$before = '';
while ($query->have_posts()) {
	$query->the_post();
	echo $before . 'Club: ';
	the_title();
	echo "\nInfo Modified: " . get_the_modified_date('j M Y') . "\n";
	$page = get_the_content();
	if (preg_match('!<div class="wp-block-semla-location"><p>([^<]*)</p>!', $page, $matches)) {
		echo "Address: $matches[1]\n";
	}
	if (preg_match('!<a href="([^"]*)">Club website!i', $page, $matches)) {
		echo "Website: $matches[1]\n";
	}
	if (preg_match('!mailto:([^"]*)!', $page, $matches)) {
		echo "Email: $matches[1]\n";
	}
	if (preg_match('!<div>Founded</div><div>(\d*)</div>!', $page, $matches)) {
		echo "Founded: $matches[1]\n";
	}
	if (preg_match('/"latLong":"([^"]*)"/', $page, $matches)) {
		$lat_lng = str_replace('%2C' ,',', $matches[1]);
		echo "Lat,long $lat_lng\n";
	}
	$before = "===============================================================\n";
}
