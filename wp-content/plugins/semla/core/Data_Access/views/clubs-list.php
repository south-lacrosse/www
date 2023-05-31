<table class="clubs-list">
<thead><tr><th class="cl-club-head">Club</th><th class="cl-links">Links</th></thead>
<tbody>
<?php
use Semla\Utils\Util;
require_once ABSPATH . 'wp-admin/includes/image.php';

// note: this size is just used on this page, and images will be regenerated if needed
add_image_size( 'logo50', 50, 50 );
// don't want srcset on all logos
add_filter('max_srcset_image_width', function() { return 1; });
$website_svg = '';
global $post;
while ($query->have_posts()) {
	$query->the_post();
	$thumbnail_id = get_post_thumbnail_id($post->ID);
	if ($thumbnail_id) {
		$meta = wp_get_attachment_metadata($thumbnail_id);
		if ( !isset( $meta['sizes']['logo50'] )) {
			wp_update_image_subsizes($thumbnail_id);
		}
	}
	echo '<tr><td class="cl-club"><a class="cl-club-link" href="';
	the_permalink();
	echo '"><span class="cl-logo">';
	if ($thumbnail_id) {
		the_post_thumbnail([50,50], ['class' => 'cl-img']);
	}
	echo '</span>';
	the_title();
	echo '</a></td><td class="cl-links"><div class="cl-links-wrapper">';
	if ($website = Util::get_the_website($post->post_content)) {
		if (!$website_svg) {
			$website_svg = '<img class="cl-website-img" src="' . plugins_url('/', dirname(__DIR__,2))
			. 'img/website.svg" height="34" width="34" alt="Website link">';
		}
		echo "<a class=\"cl-website\" href=\"$website\">$website_svg<span class=\"screen-reader-text\">Website</span></a>";
	}
	if (preg_match('|<!-- wp:social-links.*?<!-- /wp:social-links -->|s',
		$post->post_content, $matches)) {
		$social_links_block = parse_blocks( $matches[0] )[0];
		$social_links = render_block( $social_links_block );
		// force default style
		$social_links = preg_replace('/ is-style-(?!default)[a-z-]*/'
				,'is-style-default', $social_links);
		// don't display labels
		$social_links = str_replace('<span class="wp-block-social-link-label">',
			'<span class="wp-block-social-link-label screen-reader-text">', $social_links);
		echo $social_links;
    }
	echo "</div></td></tr>\n";
}
?>
</tbody>
</table>
