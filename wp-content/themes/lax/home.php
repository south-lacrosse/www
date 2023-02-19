<?php
/**
 * Blog posts index template
 */

require __DIR__ . '/parts/header.php'; ?>
<main id="content">
<?php
if (have_posts()) : ?>
<header>
<h1><?php single_post_title(); ?></h1>
</header>
<?php
	while (have_posts()) {
		the_post();
		require __DIR__ . '/parts/post-summary.php';
	}
	lax_posts_navigation();
else :
	require __DIR__ . '/parts/nothing-found.php';
endif; ?>
</main>
<?php
require __DIR__ . '/parts/footer.php';
