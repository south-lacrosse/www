<?php
/**
 * The template for displaying search results pages
 */

get_header(); ?>
<main id="content">
<?php
if (have_posts()) :
	require __DIR__ . '/searchform.php';
	while (have_posts()) {
		the_post();
		get_template_part('template-parts/content', 'search');
	}
	lax_posts_navigation();
else :
	get_template_part('template-parts/content', 'none');
endif; ?>
</main>
<?php
get_footer();
