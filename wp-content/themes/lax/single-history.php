<?php
/**
 * Template for a single history page - basically the same as page, except it will
 * add breadcrumbs, and also flags css if needed
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package Lax
 */

global $post;
if (strpos($post->post_content, 'class="flags"') !== false) {
	// need to signal plugin to add flags styles
	do_action('semla_flags_header');
}

get_header();
do_action('semla_history_breadcrumbs');
?>
<main id="content">
<?php
while (have_posts()) {
	the_post();
	get_template_part('template-parts/content', 'page');
// If comments are open or we have at least one comment, load up the comment template.
// 	if (comments_open() || get_comments_number()) {
// 		comments_template();
// 	}
} ?>
</main>
<?php
get_footer();
