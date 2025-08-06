<table class="clubs-list">
<thead><tr><th class="cl-club-head">Club</th><th class="cl-links">Links</th></thead>
<tbody>
<?php
use Semla\Utils\Util;
require_once ABSPATH . 'wp-admin/includes/image.php';

// note: this size is just used on this page, and images will be regenerated if needed
add_image_size( 'logo50', 50, 50 );
$website_svg = '<img class="cl-website-img" src="' . plugins_url('/', dirname(__DIR__,2))
	. 'img/website.svg" height="34" width="34" alt="Website link">';
global $post;
while ($query->have_posts()) {
	$query->the_post();
	echo '<tr><td class="cl-club"><a class="cl-club-link" href="';
	the_permalink();
	echo '"><span class="cl-logo">';
	$thumbnail_id = get_post_thumbnail_id($post->ID);
	if ($thumbnail_id) {
		if ('image/svg+xml' !== get_post_mime_type( $thumbnail_id )) {
			$meta = wp_get_attachment_metadata($thumbnail_id);
			if ( !isset( $meta['sizes']['logo50'] )) {
				wp_update_image_subsizes($thumbnail_id);
			}
		}
		// Note: thumbnails and smaller (like logo50) don't get scrset as Image::no_thumbnail_srcset prevents that
		the_post_thumbnail('logo50', ['class' => 'cl-img']);
	}
	echo '</span>';
	the_title();
	echo '</a></td><td class="cl-links">';
	$website = Util::get_the_website($post->post_content);
	if (preg_match('|<!-- wp:social-links.*?<!-- /wp:social-links -->|s',
		$post->post_content, $matches)) {
		$social_links_block = parse_blocks( $matches[0] )[0];
		$html = render_block( $social_links_block );
		if ($website) {
			$pos = strpos($html, '<li');
			if ($pos !== false) {
				$website_link = '<li class="wp-social-link wp-block-social-link cl-social-website"><a href="'
					. $website . '" class="wp-block-social-link-anchor">'
					. $website_svg . '<span class="wp-block-social-link-label screen-reader-text">Website</span></a></li>';
				$html = substr_replace($html, $website_link, $pos, 0);
			}
		}
		echo $html;
	} elseif ($website) {
		echo "<a class=\"cl-website\" href=\"$website\">$website_svg<span class=\"screen-reader-text\">Website</span></a>";
	}
	echo "</td></tr>\n";
}
wp_reset_postdata();
?>
</tbody>
</table>
