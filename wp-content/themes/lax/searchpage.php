<?php
/**
 * Template Name: Search Page
 *
 * Template which can be used for a search page
 * 
 * @package Lax
 */

get_header(); ?>
<main id="content">
<h1>Search</h1>
<?php get_search_form();
while (have_posts()) {
	the_post();
	the_content();
} ?>
</main>
<?php
get_footer();
