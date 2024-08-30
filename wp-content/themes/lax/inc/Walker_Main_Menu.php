<?php
namespace Lax;
/**
 * A walker to traverse the main menu, and put appropriate classes etc.
 * The default WordPress version adds too many classes, but this class only adds minimal
 *  classes
 * Will respect classes added on the menu admin page, but they must not contain
 *  item|menu|parent. That's because removing all those classes is an easy way to remove
 *  WP classes such as menu-item, menu-item-has-children, current-menu-parent.
 * Makes sure there's no whitespace between <li> elements as that messes up the spacing.
 * Add aria information.
 */
class Walker_Main_Menu extends \Walker {
	public $db_fields = array('parent' => 'menu_item_parent', 'id' => 'db_id');

	// Starts the list before the elements are added.
	public function start_lvl(&$output, $depth = 0, $args = []) {
		$mu = $depth + 1;
		$output .= "<ul class=\"mu mu$mu\">\n";
	}

	// Ends the list of after the elements are added.
	public function end_lvl(&$output, $depth = 0, $args = []) {
		$output .= "</ul>\n";
	}

 	// Starts the element output.
	public function start_el(&$output, $item, $depth = 0, $args = [], $id = 0) {
		$attrs = '';
		$url = empty($item->url) ? '#' : esc_url($item->url);
	 	$class = '';
		if ($item->title === 'Home') {
			$class .= ' house';
			$attrs .= ' title="Home"';
		}
		// don't make filter_builtin_classes into an anonymous function as that
		// will create a new function every time this method is called
		$classes = empty($item->classes) ? [] :
			array_filter($item->classes, [$this, 'filter_builtin_classes']);
		if ($classes) {
			$class .= ' '. esc_attr(join(' ', $classes));
		}

		$output .= "<li class=\"ml$depth\">";
		if ($this->has_children) {
			// We use <a> tags instead of buttons so that if a user is using the keyboard
			// then <a> tags will be outlined when tabbed onto, but not when clicked, but
			// buttons are outlined when clicked too, which we don't want (and we can't just
			// remove the outline as then keyboard users won't be able to tell where the
			// focus is)
			$output .= "<a href=\"#\" class=\"ma mp$class mi$depth\"$attrs"
			. ' role="button" aria-haspopup="true" aria-expanded="false" data-toggle="dropdown">'
			. htmlspecialchars($item->title, ENT_NOQUOTES) . "</a>\n";
		} else {
			$output .= "<a href=\"$url\" class=\"ma mi$depth$class\"$attrs>"
			. htmlspecialchars($item->title, ENT_NOQUOTES) . "</a>\n";
		}
	}

	private function filter_builtin_classes($var) {
		return preg_match('/item|menu|parent/', $var) ? '' : $var;
	}

	// Ends the element output
	public function end_el(&$output, $item, $depth = 0, $args = []) {
		$output .= '</li>'; // must not have line feed after end tag
	}
}
