<?php
global $wp_admin_bar;
$url = 'https://south-lacrosse.github.io/wp-help/';
$children = [
	'Main Page' => '',
	'Attribute Value' => 'attribute-value',
	'Clubs' => 'clubs',
	'Google Calendar' => 'google-calendar',
	'Location' => 'location',
	'Menus' => 'menus',
	'SEMLA Data' => 'semla-data',
];
// parent item
$wp_admin_bar->add_node([
	'id'    => 'semla-help',
	'title' => 'SEMLA Help',
	'href'  => $url,
	'meta'	=> ['target' => '_blank'],
]);
// add all the children to our parent
foreach ($children as $name => $uri) {
	$wp_admin_bar->add_node([
		'parent' => 'semla-help',
		'id'     => 'semla-' . str_replace(' ','-',strtolower($name)),
		'title'  => $name,
		'href'   => $url . $uri . ($uri ? '.html' : ''),
		'meta'	 => ['target' => '_blank'],
	]);
}
