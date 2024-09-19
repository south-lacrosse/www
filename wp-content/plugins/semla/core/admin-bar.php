<?php
// Customise the admin bar

// Remove comments from admin bar menu. This is the most efficient way
// to remove as it stops the wp_admin_bar_comments_menu function running,
// so stops any database access
add_action('add_admin_bar_menus', function() {
	remove_action( 'admin_bar_menu', 'wp_admin_bar_customize_menu', 40 );
	remove_action( 'admin_bar_menu', 'wp_admin_bar_comments_menu', 60 );
});

// add links to our help screens
add_action('wp_before_admin_bar_render', function() {
	global $wp_admin_bar;
	$url = 'https://south-lacrosse.github.io/wp-help/';
	// parent item
	$wp_admin_bar->add_node([
		'id'    => 'semla-help',
		'title' => 'SEMLA Help',
		'href'  => $url,
		'meta'	=> ['target' => '_blank'],
	]);

	if (!is_admin()) {
		$wp_admin_bar->remove_menu('appearance');
		$wp_admin_bar->remove_menu('plugins');
		$wp_admin_bar->add_node([
			'parent' => 'site-name',
			'id'    => 'semla-fixtures',
			'title' => 'Fixtures Import',
			'href'  => admin_url('admin.php?page=semla'),
		]);
		return;
	}
	if (!$screen = get_current_screen()) return;
	if (str_starts_with($screen->base, 'user') || $screen->base === 'profile') {
		$children = [ 'Users' => 'users'];
	} elseif ($screen->base === 'nav-menus') {
		$children = ['Menus' => 'menus' ];
	} else {
		return;
	}

	// add any children to our parent
	foreach ($children as $name => $uri) {
		$wp_admin_bar->add_node([
			'parent' => 'semla-help',
			'id'     => "semla-help-$uri",
			'title'  => $name,
			'href'   => "$url$uri.html",
			'meta'	 => ['target' => '_blank'],
		]);
	}
});

// add question mark icon to help link
add_action(is_admin() ? 'admin_head' : 'wp_head', function() {
	echo '<style>#wp-admin-bar-semla-help>.ab-item::before{'
		.'content:"\f223";top:2px;}</style>' . "\n";
});
