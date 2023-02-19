<?php
/**
 * Template for a single history page - basically the same as page, except it
 * will add breadcrumbs, and also flags css if needed
 */

global $post;
if (strpos($post->post_content, 'class="flags"') !== false) {
	// need to signal plugin to add flags styles
	do_action('semla_flags_header');
}

require __DIR__ . '/parts/header.php';
do_action('semla_history_breadcrumbs');
?>
<main id="content">
<?php
while (have_posts()) {
	the_post();
	require __DIR__ . '/parts/content-page.php';
} ?>
</main>
<?php
require __DIR__ . '/parts/footer.php';
