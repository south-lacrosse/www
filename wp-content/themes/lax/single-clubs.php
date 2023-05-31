<?php
/**
 * Template for a single club
 */

global $post;
require __DIR__ . '/parts/header.php'; ?>
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
<div class="entry-content">
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
}
if (!empty($team)) {
	$subject = '?subject=' . urlencode($team) . '+Info';
} else {
	$subject = '';
}
?>
<hr>
<p class="entry-meta meta">Updated: <?= get_the_modified_date('j M Y') ?></p>
<p class="no-print">Is this information wrong? If so please email <a href="mailto:semla.secretary@southlacrosse.org.uk<?= $subject ?>">semla.secretary@southlacrosse.org.uk</a>.</p>
</main>
<?php
require __DIR__ . '/parts/footer.php';
