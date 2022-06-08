<?php
namespace Lax;
/**
 * A walker to traverse a menu and return simple a tags, and put appropriate classes etc.
 * The default WordPress version adds too many classes, but this class only a single class.
 * Will respect classes added on the menu admin page, but they must not contain the word
 * 'menu'. That's becuase removing all 'menu' classes is an easy way to remove WP classes
 * such as menu-item, menu-item-has-children, current-menu-parent.
 * Add aria information.
 */
class Walker_Basic_Menu extends \Walker {
	public $db_fields = array('parent' => 'menu_item_parent', 'id' => 'db_id');
	private $class;

	public function __construct($class) {
		$this->class = $class;
	}

	/**
	 * Starts the list before the elements are added.
	 * @see Walker::start_lvl()
	 */
	public function start_lvl(&$output, $depth = 0, $args = []) {
	}

	/**
	 * Ends the list of after the elements are added.
	 * @see Walker::end_lvl()
	 */
	public function end_lvl(&$output, $depth = 0, $args = []) {
	}

 	/**
	 * Starts the element output.
	 * @see Walker::start_el()
	 */
	public function start_el(&$output, $item, $depth = 0, $args = [], $id = 0) {
		// don't make filter_builtin_classes into an anonymous function as that
		// will create a new function every time this method is called

		$url = $item->url;

		$classes = empty($item->classes) ? [] :
			array_filter($item->classes, [$this, 'filter_builtin_classes']);
		if ($classes) {
			$class = ' ' . esc_attr(join(' ', $classes));
		} else {
			$class = '';
		}
		$output .= "\n<a href=\"$url\" class=\"$this->class$class\">$item->title</a>";
	}
	private function filter_builtin_classes($var) {
		// return (FALSE === strpos($var, 'item')) ? $var : '';
		return preg_match('/item|menu/', $var) ? '' : $var;
	}
	
	/**
	 * Ends the element output, if needed.
	 * @see Walker::end_el()
	 */
	public function end_el(&$output, $item, $depth = 0, $args = []) {
	}
}
