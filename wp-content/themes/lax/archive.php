<?php
/**
 * The template for displaying archive pages, also used for posts index (home)
 */

require __DIR__ . '/template-parts/header.php';
$sidebar_active = is_active_sidebar( 'sidebar-posts' );
if ($sidebar_active) echo "<div class=\"content-with-sidebar\">\n";
?>
<main id="content" class="with-sidebar">
<?php
if (have_posts()) : ?>
<header>
<?php
	if (is_home()) {
		echo '<h1>';
		single_post_title();
		echo "</h1>\n";
	} else {
		the_archive_title('<h1>', '</h1>');
		the_archive_description();
	}
?>
</header>
<?php
	while (have_posts()) {
		the_post();
		require __DIR__ . '/template-parts/post-summary.php';
	}
	lax_posts_navigation();
else :
	require __DIR__ . '/template-parts/nothing-found.php';
endif; ?>
</main>
<?php if ($sidebar_active) : ?>
<aside id="sidebar">
<div class="sidebar-content">
<?php dynamic_sidebar( 'sidebar-posts' ); ?>
</div>
</aside>
</div>
<?php endif;
require __DIR__ . '/template-parts/footer.php';
