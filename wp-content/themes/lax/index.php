<?php
/**
 * The main template file
 */

get_header(); ?>
<main id="content">
<?php
if (have_posts()) :
	if (is_home() && !is_front_page()) : ?>
<h1 class="screen-reader-text"><?php single_post_title(); ?></h1>
	<?php
	endif;
	while (have_posts()) {
		the_post();
		// Include the Post-Format-specific template for the content.
		get_template_part('template-parts/content', get_post_format());
	}
	lax_posts_navigation();
else :
	get_template_part('template-parts/content', 'none');
endif; ?>
</main>
<?php
get_footer();
