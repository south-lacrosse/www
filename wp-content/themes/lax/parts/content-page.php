<?php
/**
 * Template part for displaying page content
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
