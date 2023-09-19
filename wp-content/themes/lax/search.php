<?php
/**
 * The template for displaying search results pages
 */

require __DIR__ . '/template-parts/header.php'; ?>
<main id="content">
<?php
if (have_posts()) :
	require __DIR__ . '/template-parts/searchform.php';
	while (have_posts()) {
		the_post();
		require __DIR__ . '/template-parts/post-summary.php';
	}
	lax_posts_navigation();
else :
	require __DIR__ . '/template-parts/nothing-found.php';
endif; ?>
</main>
<?php
require __DIR__ . '/template-parts/footer.php';
