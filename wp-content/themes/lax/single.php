<?php
/**
 * The template for displaying all single posts
 */
require __DIR__ . '/template-parts/header.php';
?>
<main id="content">
<?php
while (have_posts()) {
	the_post();
	require __DIR__ . '/template-parts/content.php';
	// get_template_part('template-parts/content', get_post_format());

	$prev = get_previous_post_link('%link', '« %title');
	$next = get_next_post_link('%link','%title »');
	if ($prev || $next) {
		if ($next) {
			if ($prev) {
				$nav_class = ' prev-next';
			} else {
				// single next link, so make sure the text is right justified
				$nav_class = ' right';
			}
		} else {
			$nav_class = '';
		}
		?>
<hr class="no-print">
<nav class="page-nav<?= $nav_class ?>" aria-label="Posts">
<h2 class="screen-reader-text">Post navigation</h2>
<?= $prev, $next ?>
</nav>
<?php
	}
} ?>
</main>
<?php
require __DIR__ . '/template-parts/footer.php';
