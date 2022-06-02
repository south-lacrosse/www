<?php
/**
 * Template part for displaying posts
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @link http://microformats.org/wiki/hentry
 * @package Lax
 */

$is_post = 'post' === get_post_type();
$class = $is_post ? ' with-byline' : '';
?>
<article id="post-<?php the_ID(); ?>" class="hentry">
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
<div class="entry-content">
<?php
	the_content('Continue reading <span class="screen-reader-text">"'
		. the_title('', '', false) . '"</span> &rarr;');

	wp_link_pages([
		'before' => '<div class="page-links">Pages:',
		'after'  => '</div>',
	]);
?>
</div>
<?php
lax_entry_footer();
if ($is_post && is_single() && get_post_status() === 'publish') : 
	$url = urlencode(esc_url(get_permalink())); ?>
<div class="share">
<h3>Share</h3>
<a class="share-link tw" href="https://twitter.com/intent/tweet?url=<?= $url ?>&amp;text=<?= urlencode(get_the_title()) ?>" rel="nofollow">Twitter</a>
<a class="share-link fb" href="https://www.facebook.com/sharer/sharer.php?u=<?= $url ?>" rel="nofollow">Facebook</a>
</div>
<?php 
endif; ?>
</article>
