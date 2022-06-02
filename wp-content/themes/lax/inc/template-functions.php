<?php
/**
 * Helper functions called from various templates
 */

/**
 * Prints HTML with meta information for the current post-date/time and author.
 */
function lax_posted_on() {
?>
<div class="entry-meta meta"><?php 
	$time_string = '<time class="published updated" datetime="%1$s">%2$s</time>';
	if (get_the_time('U') !== get_the_modified_time('U')) {
		$time_string = '<time class="published" datetime="%1$s">%2$s</time><time class="updated" datetime="%3$s">%4$s</time>';
	}

	$time_string = sprintf($time_string,
		esc_attr(get_the_date('c')),
		esc_html(get_the_date('j M Y')),
		esc_attr(get_the_modified_date('c')),
		esc_html(get_the_modified_date('j M Y'))
	);

	echo $time_string, ' by <span class="author">',
		esc_html(get_the_author()), '</span>';
?>
</div>
<?php
}

/**
 * Prints HTML with meta information for the categories, tags and comments.
 */
function lax_entry_footer() {
?>
<footer class="entry-footer">
<?php
	// Show category and tag text for pages.
	if ('post' === get_post_type()) {
		$categories_list = get_the_category_list(', ');
		if ($categories_list) {
			echo '<i class="icon icon-folder-open"></i>', $categories_list;
		}

		$tags_list = get_the_tag_list('', ', ');
		if ($tags_list) {
			if ($categories_list) echo ' &nbsp; ';
			echo '<i class="icon icon-tags"></i>', $tags_list;
		}
	}

	// if (!is_single() && !post_password_required() && (comments_open() || get_comments_number())) {
	// 	echo '<span class="comments-link">';
	// 	comments_popup_link('Leave a Comment<span class="screen-reader-text"> on '
	// 		. get_the_title() . '</span>');
	// 	echo '</span>';
	// }
?>
</footer>
<?php
}

/**
 * Load cached menu, or regenerate it
 * 
 * wp_nav_menu executes 4 queries, which returns a load of data, so menus are cached here.
 */
function lax_menu($menu_name) {
	$menu_file = dirname(__DIR__ ) . '/template-parts/menu-' . $menu_name . '.html';
	if (!@readfile($menu_file)) {
		add_filter('semla_change_the_title', '__return_false');
		ob_start(); 
		if ($menu_name === 'main') {
			require_once __DIR__.'/Walker_Main_Menu.php';
			wp_nav_menu([
				'theme_location' => 'main',
				'menu_id' => 'main-menu', // id for generated <ul>
				'menu_class' => 'mu', // class for generated <ul>
				'container' => false,
				'walker' => new \Lax\Walker_Main_Menu(),
				'fallback_cb' => false
			]);
			$menu = ob_get_clean();
		} else {
			require_once(__DIR__ . '/Walker_Basic_Menu.php');
			wp_nav_menu([
				'theme_location' => $menu_name,
				'items_wrap' => '%3$s', // remove <ul>: wrapper
				'container' => false,
				'walker' => new \Lax\Walker_Basic_Menu($menu_name === 'social' ? 'soc-a' : 'pop-a'),
				'fallback_cb' => false
			]);
			$menu = ob_get_clean();
			if ($menu) {
				if ($menu_name === 'social') {
					$menu = "<nav>\n<h2 class=\"soc-h\">Find us here:</h2>$menu\n</nav>";
				} else {
					$menu = "<nav class=\"nav-$menu_name\">$menu\n</nav>";
				}
			}
		}
		$site_url = defined( 'WP_SITEURL' ) ? WP_SITEURL : get_option('siteurl');
		$menu = str_replace($site_url, '', $menu);
		$menu = str_replace('href="http', 'rel="nofollow" href="http', $menu);
		// write to a temp file so another process doesn't try to read
		// a half written file
		$tmpf = tempnam('/tmp','lax_menu');
		$fp = fopen($tmpf,'w');
		fwrite($fp,$menu);
		fclose($fp);
		chmod($tmpf, 0604); // temp files default to 0600
		rename($tmpf, $menu_file);
        remove_filter('semla_change_the_title', '__return_false');
		echo $menu;
	}
}

/**
 * Prints HTML for breadcrumbs
 */
function lax_breadcrumbs() {
	global $post;
	// NB: currently only called from page template, so we know that this is a page/post!
	// could add in clubs?
	if ($post->post_parent) {
		// useful for programs which replace the title to not do it on breadcrumbs
		$anc = get_post_ancestors( $post->ID );
		// Get parents in the right order
		$anc = array_reverse($anc);

		echo '<nav><ul class="breadcrumbs nav-list">';
		foreach ( $anc as $ancestor ) {
			echo '<li><a href="' . get_permalink($ancestor) . '">' . get_the_title($ancestor) . '</a></li>';
		}
		echo '<li>';
		add_filter('semla_change_the_title', '__return_false');
		the_title();
        remove_filter('semla_change_the_title', '__return_false');
		echo '</li></ul></nav>';
	}
}

/**
 * Prints HTML for numbered pagination links
 */
function lax_posts_navigation() {
	global $wp_query;
	$total = $wp_query->max_num_pages;
	if ($total < 2) {
		return;
	}

	// format of pagination links/text to display
	$page_links = [
	    ['rel' => -1, 'label' => 'prev'],
		['rel' => -10],
		['rel' => -10, 'label' => '..'],
		['rel' => -2],
		['rel' => -1],
		['rel' => 0],
		['rel' => 1],
		['rel' => 2],
		['rel' => 10, 'label' => '..'],
		['rel' => 10],
		['rel' => 1, 'label' => 'next']
	];

	$current = get_query_var('paged') ? intval(get_query_var('paged')) : 1;
	$page_1 = untrailingslashit(get_pagenum_link(1));
	$big = 99999;
	$base = str_replace($big, '%#%', esc_url(get_pagenum_link($big)));
?>
<hr>
<nav class="page-nav paging">
<span class="box">Page <?= $current ?> of <?= $total ?></span>
<?php 	if ($current > 1 && $total > 3) {
			echo '<a class="no-ul box" href="', $page_1, '"';
			if ($current === 2) {
				echo ' rel="prev"';
			}
			echo ">« First</a>\n";
		}
		foreach ($page_links as $page_link) {
			$page = $current + $page_link['rel'];
			if ($page > $total) {
				continue;
			}
			if ($page > 0) {
				if ($page_link['rel'] === 0) {
					echo '<span class="box grey">', $page, "</span>\n";
				} else {
					$label = $page_link['label'] ?? '';
					if ($label === '..') {
						echo '<span class="box">', $label, "</span>\n";
					} else {
						echo '<a class="no-ul box" href="',
							$page === 1 ? $page_1 : str_replace('%#%', $page, $base),
							'"';
						if ($page_link['rel'] === -1) {
							echo ' rel="prev"';
						} elseif ($page_link['rel'] === 1) {
							echo ' rel="next"';
						}
						echo '>', ($label ? $label : $page), "</a>\n";
					}
				}
			}
		}
		if ($current < $total - 2) {
			echo '<a class="no-ul box" href="', str_replace('%#%', $total, $base), '"';
			if ($current === $total - 1) {
				echo ' rel="next"';
			}
			echo ">Last »</a>\n";
		} ?>
</nav>
<?php
}
