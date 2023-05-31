<?php
/**
 * Handle Text format for clubs
 */

use Semla\Utils\Util;

$before = '';
while ($query->have_posts()) {
	$query->the_post();
	echo $before . 'Club: ';
	the_title();
	echo "\nInfo Modified: " . get_the_modified_date('j M Y') . "\n";
	$post_content = get_the_content();
	if (preg_match('!<div class="wp-block-semla-location"><p>([^<]*)</p>!', $post_content, $matches)) {
		echo "Address: $matches[1]\n";
	}
	$website = Util::get_the_website($post_content);
	if ($website) {
		echo "Website: $website\n";
	}
	if (preg_match('!mailto:([^"]*)!', $post_content, $matches)) {
		echo "Email: $matches[1]\n";
	}
	if (preg_match('!<div>Founded</div><div>(\d*)</div>!', $post_content, $matches)) {
		echo "Founded: $matches[1]\n";
	}
	if (preg_match('/"latLong":"([^"]*)"/', $post_content, $matches)) {
		$lat_lng = str_replace('%2C' ,',', $matches[1]);
		echo "Lat,long $lat_lng\n";
	}
	$before = "===============================================================\n";
}
