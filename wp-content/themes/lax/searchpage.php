<?php
/**
 * Template which can be used for a search page
 */

require __DIR__ . '/template-parts/header.php'; ?>
<main id="content">
<h1>Search</h1>
<?php
require __DIR__ . '/template-parts/searchform.php';
while (have_posts()) {
	the_post();
	the_content();
} ?>
</main>
<?php
require __DIR__ . '/template-parts/footer.php';
