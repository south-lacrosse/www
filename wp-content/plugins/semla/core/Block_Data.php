<?php
namespace Semla;

use Semla\Data_Access\Club_Gateway;
use Semla\Data_Access\Cup_Draw_Gateway;
use Semla\Data_Access\DB_Util;
use Semla\Data_Access\Fixtures_Gateway;
use Semla\Data_Access\Fixtures_Results_Gateway;
use Semla\Data_Access\Table_Gateway;
use Semla\Utils\Block_Util;

/**
 * Server side rendering of SEMLA data block, plus handling of results history
 * pages
 */
class Block_Data {
	private static $instance;
	private $gateway;
	private $year;
	private $options;
	private $fix_res; // "Fixtures" or "Results"
	private $type;
	private $arg;

	public static function get_instance() {
		null === self::$instance and self::$instance = new self;
		return self::$instance;
	}
	public function parse_query_args($src) {
		if (!$src) wp_die('Block_Data missing src attribute.');
		$split = explode(',',$src);
		$method = "$split[0]_args";
		if (method_exists($this, $method)) {
			if (isset($split[1])) {
				$this->$method($split[1]);
			} else {
				$this->$method();
			}
		}
	}

	public static function render_callback( $atts ) {
		// Note: cannot just use self::$instance as this method can be called
		// via REST from the editor to display data
		return self::get_instance()->_render_callback($atts);
	}
	private function _render_callback( $atts )  {
		if (isset($atts['src'])) {
			// Note: reevaluate src here as _parse_query_args won't be called
			// when in the block editor
			$src = $atts['src'];
			$split = explode(',',$src);
			$method = "$split[0]";
			if (method_exists($this, $method)) {
				if (isset($split[1])) {
					return $this->$method($split[1]);
				} else {
					return $this->$method();
				}
			}
			return("<p>Data source $src does not exist</p>");
		}
		return '';
	}

	private function clubs_list_args() {
		wp_enqueue_style( 'semla-clubs-list',
			plugins_url('css/clubs-list' . SEMLA_MIN . '.css', __DIR__),
			[], '1.0');
	}
	private function clubs_list() {
		return Club_Gateway::clubs_list('list');
	}

	private function clubs_grid_args() {
		wp_enqueue_style( 'semla-clubs-grid',
			plugins_url('css/clubs-grid' . SEMLA_MIN . '.css', __DIR__),
			[], '1.0');
	}
	private function clubs_grid() {
		return Club_Gateway::clubs_list('grid');
	}

	/**
	 * Just need to enqueue the map css/js here so the CSS appears in the head
	 */
	private function clubs_map_args() {
		wp_enqueue_style( 'semla-map', plugins_url('css/map' . SEMLA_MIN . '.css', __DIR__),
			[], '1.5');
		wp_enqueue_script( 'semla-map',
			plugins_url('js/map' . SEMLA_MIN . '.js', __DIR__),
			[], '1.4', ['in_footer' => true, 'strategy' => 'async'] );
		Block_Util::preconnect_hints(['maps.googleapis.com','maps.gstatic.com','fonts.gstatic.com']);
	}
	private function clubs_map() {
		return Club_Gateway::clubs_map();
	}

	private function curr_fixtures_args() {
		$this->fixtures_results_args(0);
	}
	public function fixtures_results_args($year) {
		$this->year = $year;
		$this->gateway = new Fixtures_Results_Gateway();
		if ($year) {
			$this->options = $this->gateway->get_result_options($year);
			$this->fix_res ='Results';
			$valid_args = ['team','comp','date','all'];
		} else {
			$this->options = $this->gateway->get_fixtures_options();
			$this->fix_res ='Fixtures';
			$valid_args = ['team','club','comp','date','all'];
		}
		if ($this->options === false) return; // db error

		$argsCount = count($_GET);
		if ($argsCount === 0) {
			if ($year === 0) {
				$this->type = 'default';
				// cache for 2 days
				do_action( 'litespeed_control_set_ttl', '172800' );
			} else {
				$this->type = null;
			}
			$this->arg = '';
			return;
		}
		$this->type = null;
		foreach ($valid_args as $arg) {
			$test= isset($_GET[$arg]) ? stripslashes(urldecode($_GET[$arg])) : '';
			if ($test && ($arg === 'all' || isset($this->options[$arg][$test]))) {
				$this->arg = $test;
				$this->type = $arg;
				break;
			}
		}
		// Note: these errors won't happen for links within the site, but malicious programs
		// may try to inject parameters
		if (!$this->type) {
			// all query args invalid, so redirect to page without any args
			wp_redirect( get_permalink() );
			exit;
		}
		if ($this->type === 'all' && $this->arg !== '1') {
			// 'all' must be 1
			wp_redirect( get_permalink() . '?all=1' );
			exit;
		}
		if ($argsCount > 1) {
			// too many args, so redirect to page with only 1
			wp_redirect( get_permalink() . "?$this->type=" . urlencode($this->arg) );
			exit;
		}

		switch ($this->type) {
			case 'all':
				$title = "Complete $this->fix_res";
				if ($year) $title .= " $year";
				break;
			case 'team':
			case 'club':
			case 'comp':
				$title = htmlspecialchars($this->arg, ENT_NOQUOTES) . " $this->fix_res";
				if ($year) $title .= " $year";
				break;
			case 'date':
				$date = date('jS F Y ', strtotime($this->arg)); // use full month here
				$title = "$date $this->fix_res";
				break;
		}
		Block_Util::change_page_title($title);
	}

	private function curr_fixtures() {
		return $this->fixtures_results();
	}

	public function fixtures_results() {
		if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
			// For REST/editor requests we won't have parsed any params, so fake it here
			$this->year = 0;
			$this->type = 'default';
			$this->arg = '';
			$this->gateway = new Fixtures_Results_Gateway();
			$this->options = $this->gateway->get_fixtures_options();
			$this->fix_res ='Fixtures';
		}
		if ($this->options === false) return DB_Util::db_error();
		if ($this->year) {
			$selects = ['team' => 'team', 'comp' => 'competition','date' => 'date'];
		} else {
			$selects = ['team' => 'team', 'club' => 'club', 'comp' => 'competition','date' => 'date'];
		}
		$html = '<div class="big no-print alignwide">Display ';
		if ($this->type !== 'all') {
			$html .= '<a href="?all=1" class="btn">All ' . $this->fix_res . '</a>'. "\n";
		}

		foreach ($selects as $key => $val) {
			$html .= $this->create_select($key,$val,$this->options[$key],
				$this->type === $key ? $this->arg : null);
		}
		$html .= '</div><div id="data-area">';
		if ($this->type) {
			$html .= $this->gateway->get_fixtures($this->year, $this->type, $this->arg);
		}
		$html .= '</div>';
		return $html;
	}

	private function curr_results() {
		return Fixtures_Results_Gateway::recent_results();
	}
	/**
	 * Just need to enqueue the css here so the CSS appears in the head, and set the body class
	 */
	private function curr_flags_args($group_id) {
		App_Public::enqueue_flags_css(get_option('semla_max_flags_rounds'));
	}
	private function curr_flags($group_id) {
		return Cup_Draw_Gateway::get_draws(0,$group_id);
	}
	private function curr_flags_rounds($group_id) {
		return Cup_Draw_Gateway::get_draws(0,$group_id,'rounds');
	}
	private function curr_tables($league_id) {
		return Table_Gateway::get_tables(0,$league_id);
	}
	private function curr_grid($league_id) {
		wp_enqueue_script( 'semla-colhover',
			plugins_url('js/colhover' . SEMLA_MIN . '.js', __DIR__),
			[], '2.0', true );
		return Fixtures_Gateway::get_grid(0,$league_id);
	}

	/**
	 * Create a select box
	 */
	private function create_select($name, $label, $arr, $selected, $action=false, $hidden = []) {
		$html = '<form class="selform" id="' . $name . 'form" name="' . $name
		. 'form" method="get" '. ($action ? 'action="' . $action . '" ' : '') . 'onchange="document.' . $name . 'form.submit();">'
		. '<label for="' . $name . '">' . $label . ': </label><select name="' . $name . '" id="' . $name . '">';
		if (!$selected) {
			$html .= '<option value="" selected="" disabled="">Select...</option>';
		}
		foreach (array_keys($arr) as $opt) {
			$html .= '<option' . ($opt === $selected ? ' selected': '') . '>' . htmlspecialchars($opt, ENT_NOQUOTES) . "</option>\n";
		}
		$html .= '</select>';
		foreach ($hidden as $key=> $value) {
			$html .= '<input type="hidden" name="' . $key . '" value="' . htmlentities($value, ENT_NOQUOTES) . '">';
		}
		$html .= '<input type="submit" value="Select"></form>' . "\n";
		return $html;
	}
}
