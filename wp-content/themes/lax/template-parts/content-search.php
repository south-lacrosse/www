<?php
/**
 * Template part for displaying results in search pages
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @link http://microformats.org/wiki/hentry
 * @package Lax
 */

?>
<article id="post-<?php the_ID(); ?>">
<div class="rd">
<h3 class="rh"><a href="<?= esc_url(get_permalink()) ?>" class="ra"><?php the_title(); ?></a></h3>
<?php
if ('post' === get_post_type()) {
	echo '<p class="meta">', esc_html(get_the_modified_date('j M Y')), '</p>';
}
the_excerpt();
?>
</div>
</article>
