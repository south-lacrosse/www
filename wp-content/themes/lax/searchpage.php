<?php
/**
 * Template which can be used for a search page
 */

get_header(); ?>
<main id="content">
<h1>Search</h1>
<?php
require __DIR__ . '/searchform.php';
while (have_posts()) {
	the_post();
	the_content();
} ?>
</main>
<?php
get_footer();
