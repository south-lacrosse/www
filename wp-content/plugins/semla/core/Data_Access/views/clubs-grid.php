<ul class="clubs-grid alignwide">
<?php
global $post;
$question_mark = '';
add_filter('max_srcset_image_width', function() { return -1; });
while ($query->have_posts()) {
	$query->the_post();
	echo '<li class="club-card"><a class="club-card-link" href="';
	the_permalink();
	echo '">';
	if (has_post_thumbnail()) {
		the_post_thumbnail('thumbnail', ['class' => 'club-card-icon']);
	} else {
		if (!$question_mark) {
			$question_mark = '<img src="' . $base_url = plugins_url('/', dirname(__DIR__,2))
				. 'img/question-mark.svg" style="height:150px" class="club-card-icon" height="150px" width="150px">';
		}
		echo $question_mark;
	}
	echo '<h4 class="club-card-title">';
	the_title();
	echo '</h4></a>';
	echo "</li>\n";
}
?>
</ul>
