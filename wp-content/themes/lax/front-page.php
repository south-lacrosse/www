<?php
/**
 * Front page template (i.e. the "Home" page). Should be a page, but also caters for
 * when WP is set to display posts on the front page.
 */
if ( 'posts' == get_option( 'show_on_front' ) ) {
	include( get_home_template() );
	exit;
}
$img = get_theme_file_uri() . '/img/logo.svg';
require __DIR__ . '/parts/header.php'; ?>
<img class="front-logo" src="<?= $img ?>" width="980" height="609" alt="SEMLA logo" onerror="this.style.display='none'">
<main id="content" class="with-mini-tables">
<div class="with-mini-tables-inner">
<?php
do_action('semla_notices');
while (have_posts()) :
	the_post();?>
<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
<div class="entry-content">
<?php
	the_content();
?>
</div>
</article><?php
endwhile; ?>
</div>
</main>
<aside id="sidebar-mini-tables">
<?php do_action('semla_mini_tables'); ?>
</aside>
<?php
require __DIR__ . '/parts/footer.php';
