<?php
/**
 * The template for displaying search results pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/#search-result
 * @package Lax
 */

get_header(); ?>
<main id="content">
<?php
if (have_posts()) :
	get_search_form();
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
