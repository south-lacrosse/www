<?php
/**
 * The template for displaying 404 pages (not found)
 * @link https://codex.wordpress.org/Creating_an_Error_404_Page
 * @package Lax
 */

get_header(); ?>
<main id="content">
<section>
<h1>Oops! That page can&rsquo;t be found.</h1>
<p>It looks like nothing was found at this location. Try the menu or a search?</p>
<?php get_search_form(); ?>
</section>
</main>
<?php
get_footer();
