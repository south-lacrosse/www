<table class="clubs-list is-style-boxed-striped">
<thead><tr><th>Club</th><th>Link</th></tr></thead>
<tbody>
<?php
while ($query->have_posts()) {
	$query->the_post();
	echo '<tr><td><a href="';
	the_permalink();
	echo '">';
	the_title();
	echo '</a></td><td>';
	if (preg_match('/<a [^>]*>(Club |)(Website|Facebook)[^>]*>/i', get_the_content(), $matches)) {
		echo strtr($matches[0], [
			'Club ' => '',
			'website' => 'Website',
		]);
	}
	echo '</td></tr>';
}
?>
</tbody>
</table>
