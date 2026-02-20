<?php
/**
 * The template for displaying the footer
 * Contains the closing of the #content div and all content after.
 */
$url = get_theme_file_uri();
$js = $url . '/main' . SEMLA_MIN . '.js?ver=1.1';
?>
</div>
<footer id="page-footer">
<div class="inner">
<?php
// wp_nav_menu executes 4 queries, which return a load of data, so rather than
// calling directly the menus are cached here.
lax_menu("social");
?>
<p class="copy">&copy; 2003-<?= date('Y') ?>, SEMLA</p>
</div>
</footer>
<script src="<?= $js ?>" async></script>
<?php wp_footer(); ?>
</body>
</html>
