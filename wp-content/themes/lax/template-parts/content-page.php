<?php
/**
 * Template part for displaying page content in page.php
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @link http://microformats.org/wiki/hentry
 * @package Lax
 */
?>
<article id="post-<?php the_ID(); ?>" class="hentry">
<?php the_title('<h1 class="entry-title">', '</h1>'); ?>
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
