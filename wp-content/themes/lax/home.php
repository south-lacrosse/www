<?php
/**
 * Blog posts index template
 */

get_header(); ?>
<main id="content">
<?php
if (have_posts()) : ?>
<header>
<h1><?php single_post_title(); ?></h1>
</header>
<?php
	while (have_posts()) {
		the_post();
		// Include the Post-Format-specific template for the content.
		// get_template_part('template-parts/content', get_post_format());
		get_template_part('template-parts/content', 'archive');
	}
	lax_posts_navigation();
else :
	get_template_part('template-parts/content', 'none');
endif; ?>
</main>
<?php
get_footer();
