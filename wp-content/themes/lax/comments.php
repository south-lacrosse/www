<?php
/**
 * The template for displaying comments
 *
 * This is the template that displays the area of the page that contains both the current comments
 * and the comment form.
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package Lax
 */

/*
 * If the current post is protected by a password and
 * the visitor has not yet entered the password we will
 * return early without loading the comments.
 */
if (post_password_required()) {
	return;
}
?>
<div id="comments" class="comments-area">
<?php
if (have_comments()) : ?>
<h2 class="comments-title">
<?php
	printf( // WPCS: XSS OK.
		(get_comments_number() == 1 ? 'One thought on “;%2$s”'	: '%1$s thoughts on “%2$s”'),
		get_comments_number(),
		'<span>' . get_the_title() . '</span>'
	);
?>
</h2>
<?php if (get_comment_pages_count() > 1 && get_option('page_comments')) : // Are there comments to navigate through? ?>
<nav id="comment-nav-above" class="comment-navigation">
<h2 class="screen-reader-text">Comment navigation</h2>
<div class="nav-previous"><?php previous_comments_link('« Older Comments'); ?></div>
<div class="nav-next"><?php next_comments_link('Newer Comments »'); ?></div>
</nav>
<?php endif; // Check for comment navigation. ?>
<ol class="comment-list">
<?php
	wp_list_comments([
		'style'      => 'ol',
		'short_ping' => true,
	]);
?>
</ol>
<?php if (get_comment_pages_count() > 1 && get_option('page_comments')) : // Are there comments to navigate through? ?>
<nav id="comment-nav-below" class="page-nav">
<h2 class="screen-reader-text">Comment navigation</h2>
<div class="nav-previous"><?php previous_comments_link('« Older Comments'); ?></div>
<div class="nav-next"><?php next_comments_link('Newer Comments »'); ?></div>
</nav>
<?php
endif; // Check for comment navigation.
endif; // Check for have_comments().

// If comments are closed and there are comments, let's leave a little note, shall we?
if (!comments_open() && get_comments_number() && post_type_supports(get_post_type(), 'comments')) : ?>
<p class="no-comments">Comments are closed.</p>
<?php
endif;

comment_form();
?>
</div>
