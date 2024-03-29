<?php
/**
 * This is the template that displays all pages by default (that's pages,
 * as opposed to posts or clubs, not a generic "page")
 */

require __DIR__ . '/template-parts/header.php';
lax_breadcrumbs(); ?>
<main id="content">
<?php
while (have_posts()) {
	the_post();
	require __DIR__ . '/template-parts/content-page.php';
} ?>
</main>
<?php
require __DIR__ . '/template-parts/footer.php';
