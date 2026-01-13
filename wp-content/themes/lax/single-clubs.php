<?php
/**
 * Template for a single club
 */

global $post;
require __DIR__ . '/template-parts/header.php'; ?>
<main id="content">
	<?php
while (have_posts()) {
	the_post();
	$team = get_the_title(); ?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<?php
	if (!str_contains($post->post_content,'<!-- wp:post-title'))
		echo "<h1 class=\"entry-title\">$team</h1>\n";
?>
<div class="entry-content is-layout-flow">
<?php
	the_content();

	wp_link_pages([
		'before' => '<div class="page-links">Pages:',
		'after'  => '</div>',
	]);
?>
</div>
</article>
<?php
} ?>
</main>
<?php
require __DIR__ . '/template-parts/footer.php';
