<?php
/**
 * Display post summary with link to post. Used for search and archive
 */
?>
<article id="post-<?php the_ID(); ?>">
<div class="rd">
<h3 class="rh"><a href="<?= esc_url(get_permalink()) ?>" class="ra"><?php the_title(); ?></a></h3>
<p><?php
if ('post' === get_post_type()) {
	echo '<span class="meta">', esc_html(get_the_date('j M Y')), '</span> - ';
}
// don't use the_excerpt() as that wraps the excerpt with p tags
echo get_the_excerpt();
?></p>
</div>
</article>
