<?php
/**
 * This is the template that displays all pages by default (that's pages,
 * as opposed to posts or clubs, not a generic "page")
 */

get_header();
lax_breadcrumbs(); ?>
<main id="content">
<?php
do_action('semla_notices');
while (have_posts()) {
	the_post();
	get_template_part('template-parts/content', 'page');
} ?>
</main>
<?php
get_footer();
