<?php
/**
 * Template for a single club
 */

$team = '';
require __DIR__ . '/parts/header.php'; ?>
<main id="content">
<?php
while (have_posts()) {
	the_post();
	require __DIR__ . '/parts/content-page.php';
	$team = get_the_title();
}
if ($team) {
	$subject = '?subject=' . urlencode($team) . '+Info';
} else {
	$subject = '';
}
?>
<hr>
<p class="entry-meta meta">Updated: <?= esc_html(get_the_modified_date('j M Y')) ?></p>
<p class="noprint">Is this information wrong? If so please email <a href="mailto:webmaster@southlacrosse.org.uk<?= $subject ?>">webmaster@southlacrosse.org.uk</a>.</p>
</main>
<?php
require __DIR__ . '/parts/footer.php';
