<?php
/**
 * Template part for displaying a message that posts cannot be found
 *
 * @link https://codex.wordpress.org/Template_Hierarchy
 * @package Lax
 */
?>
<section>
<?php
get_search_form();
if (is_search()) : ?>
<h3>Sorry, no matches found.</h3>
<h3>Search Suggestions:</h3>
<ul>
<li>Check your spelling</li>
<li>Try more general words</li>
<li>Try different words that mean the same thing</li>
</ul>
<?php else : ?>
<p>It seems we can&rsquo;t find what you&rsquo;re looking for. Perhaps searching can help.</p>
<?php endif; ?>
</section>
