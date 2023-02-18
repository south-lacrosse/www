<?php
/**
 * Template for a single club
 */

$team = '';
get_header(); ?>
<main id="content">
<?php
while (have_posts()) {
	the_post();
	get_template_part('template-parts/content', 'page');
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
get_footer();
