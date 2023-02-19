<?php
/**
 * The template for displaying archive pages
 */

require __DIR__ . '/parts/header.php'; ?>
<main id="content">
<?php
if (have_posts()) : ?>
<header>
<?php
	the_archive_title('<h1>', '</h1>');
	the_archive_description();
?>
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
