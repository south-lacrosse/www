<?php
/**
 * The main template file. In this theme should never get here as everything is
 * catered for in the other templates.
 */
require __DIR__ . '/parts/header.php'; ?>
<main id="content">
<?php
if (have_posts()) :
	if (is_home() && !is_front_page()) : ?>
<h1 class="screen-reader-text"><?php single_post_title(); ?></h1>
	<?php
	endif;
	while (have_posts()) {
		the_post();
		// Include the Post-Format-specific template for the content.
		get_template_part('parts/content', get_post_format());
	}
	lax_posts_navigation();
else :
	require __DIR__ . '/parts/nothing-found.php';
endif; ?>
</main>
<?php
require __DIR__ . '/parts/footer.php';
