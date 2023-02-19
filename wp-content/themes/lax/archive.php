<?php
/**
 * The template for displaying archive pages
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package Lax
 */

get_header(); ?>
<main id="content">
<?php
if (have_posts()) : ?>
<header>
<?php
	the_archive_title('<h1>', '</h1>');
	the_archive_description();
?>
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
