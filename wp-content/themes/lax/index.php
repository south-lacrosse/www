<?php
/**
 * The main template file
 *
 * This is the most generic template file in a WordPress theme
 * and one of the two required files for a theme (the other being style.css).
 * It is used to display a page when nothing more specific matches a query.
 * E.g., it puts together the home page when no home.php file exists.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package Lax
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
