<?php
/**
 * The template for displaying all single posts
 */

// only place get_the_post_navigation() used - if this changes
// move to functions.php
add_filter('navigation_markup_template', function() {
	return '<nav class="page-nav">
		<h2 class="screen-reader-text">%2$s</h2>
		%3$s
	</nav>';
});

require __DIR__ . '/parts/header.php';
?>
<main id="content">
<?php
while (have_posts()) {
	the_post();
	require __DIR__ . '/parts/content.php';
	// get_template_part('parts/content', get_post_format());
	$nav = get_the_post_navigation([
		'prev_text' => '« %title',
		'next_text' => '%title »',
	]);
	if ($nav) {
		echo '<hr>', $nav;
	}
} ?>
</main>
<?php
require __DIR__ . '/parts/footer.php';
