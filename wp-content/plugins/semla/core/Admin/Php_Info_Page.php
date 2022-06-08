<?php
namespace Semla\Admin;
/**
 * Display PHP Info admin page
 */
class Php_Info_Page {
	public static function render_page() {
		if (!current_user_can('manage_options'))  {
			wp_die('You do not have sufficient permissions to access this page.');
		}
		ob_start(); 
		phpinfo();
		$phpInfo = ob_get_clean();
		
		$pos1 = strpos($phpInfo, '<style');
		$pos2 = strrpos($phpInfo, '</style>', $pos1);
		$styles = explode("\n", substr($phpInfo, $pos1, $pos2 - $pos1));
		// styles[0] will be <style line
		echo '<style>.semla_phpinfo {font-size:16px}' . str_replace('body', '.semla_phpinfo', $styles[1]);

		// rewrite the remaining styles to include parent class of .semla_phpinfo
		$len = count($styles);
		$i = 2;
		while ($i < $len) {
			if ($styles[$i] != '') {
				echo $styles[$i] = '.semla_phpinfo ' . $styles[$i];
			}
			$i++;
		}
		echo '</style>';

		$pos1 = strpos($phpInfo, '<body>');
		$pos2 = strrpos($phpInfo, '</body>', $pos1);
		echo '<div class="semla_phpinfo">';
		echo substr($phpInfo, $pos1 + 6, $pos2 - $pos1 - 6);
		echo '</div>';
	}
}
