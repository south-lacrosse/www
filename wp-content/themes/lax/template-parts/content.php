<?php
/**
 * Template part for displaying posts
 */

$is_post = 'post' === get_post_type();
$class = $is_post ? ' with-byline' : '';
?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<?php if ($is_post) : ?>
<header>
<?php
endif;
if (is_single()) {
	the_title('<h1 class="entry-title' . $class . '">', '</h1>');
} else {
	the_title('<h2 class="entry-title' . $class . '"><a href="' . esc_url(get_permalink()) . '" rel="bookmark">', '</a></h2>');
}
echo "\n";
if ($is_post) :
	lax_posted_on(); ?>
</header>
<?php
endif; ?>
<div class="entry-content is-layout-flow">
<?php
	the_content('Continue reading <span class="screen-reader-text">"'
		. the_title('', '', false) . '"</span> &rarr;');

	wp_link_pages([
		'before' => '<div class="page-links">Pages:',
		'after'  => '</div>',
	]);
?>
</div>
<?php lax_entry_footer(); ?>
</article>
