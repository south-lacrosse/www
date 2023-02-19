<?php
/**
 * The template for displaying 404 pages (not found)
 */

require __DIR__ . '/parts/header.php'; ?>
<main id="content">
<section>
<h1>Oops! That page can&rsquo;t be found.</h1>
<p>It looks like nothing was found at this location. Try the menu or a search?</p>
<?php require __DIR__ . '/parts/searchform.php'; ?>
</section>
</main>
<?php
require __DIR__ . '/parts/footer.php';
