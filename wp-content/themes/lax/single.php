<?php
/**
 * The template for displaying all single posts
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#single-post
 * @package Lax
 */
// only place get_the_post_navigation() used - if this changes
// move to functions.php
add_filter('navigation_markup_template', function() {
	return '<nav class="page-nav">
		<h2 class="screen-reader-text">%2$s</h2>
		%3$s
	</nav>';
});

get_header();
?>
<main id="content"<?php if (LAX_ACTIVE_SIDEBAR) echo ' class="with-sidebar"';?>>
<?php
while (have_posts()) {
	the_post();
	get_template_part('template-parts/content', get_post_format());
	$nav = get_the_post_navigation([
		'prev_text' => '« %title',
		'next_text' => '%title »',
	]);
	if ($nav) {
		echo '<hr>', $nav;
	}
	// If comments are open or we have at least one comment, load up the comment template.
// 	if (comments_open() || get_comments_number()) {
// 		comments_template();
// 	}
} ?>
</main>
<?php
get_sidebar();
get_footer();
