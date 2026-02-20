/**
 * IMPORTANT: Bump the version number in footer.php if you change this file.
 *
 * Main javascript for our theme. Handles dropdown menu, pullout menu for small
 * screens, tabs, and toggling collapses.
 *
 * This code tries to use older javascript syntax so it will run on anything
 * which has addEventListener (so IE9+ and everything else).
 *
 * Note: We close the dropdown menu if a user clicks outside the menu area. This
 * works fine on desktop but some older mobile browsers only dispatch click
 * events on elements like anchors.
 *
 * We could implement the close on touch, but then we have to be careful not to
 * close it if the user is dragging to view the bottom of the menu, when we'd
 * want to leave it open. That becomes quite fiddly, so we've just left it as is
 * as the effort doesn't seem worthwhile as it's a minor feature, and the user
 * can click the dropdown element again to close.
 */
(function () {
	'use strict';
	var menu,
		mainMenu,
		closeMenu,
		showMenuBtn,
		shownDropdown,
		dropdownUl,
		lastMenuLink,
		passive = false,
		pulloutTimeout,
		isPulloutMenuOpen = false,
		addedListeners = false,
		mpTabIndex = 0,
		doc = document,
		win = window;

	if (!win.addEventListener) return;

	// Main click event handler - just one on the document so we don't have
	// to add individual listeners on each element we are interested in.
	doc.addEventListener(
		'click',
		function (event) {
			var target = event.target;
			switch (target.id) {
				case 'show-menu':
					showPulloutMenu(event);
					return;
				case 'close-menu':
				case 'overlay':
					hidePulloutMenu();
					return;
			}
			var dataToggle = target.getAttribute('data-toggle');
			if (dataToggle) {
				switch (dataToggle) {
					case 'dropdown':
						event.preventDefault();
						// If we are displaying the menu as a pull out with all dropdown menus
						// open already, then just ignore click. Should never happen as CSS
						// pointer-events will be none, but not quite 100% of browsers support that
						if (isPulloutMenuOpen) return;
						if (target === shownDropdown) {
							hideDropdown();
						} else {
							showDropdown(target);
						}
						return;
					case 'tab':
						event.preventDefault(); // tabs will have links as fallback for no javascript
						tab(target);
						break;
					case 'collapse':
						toggleCollapse(target);
						break;
				}
			}
			if (shownDropdown && target.className.substring(0, 3) !== 'ma ') {
				hideDropdown();
			}
		},
		false
	);

	// We really just want to close any open menus when we pass the CSS media
	// query breakpoint that changes from pullout to dropdown menu, however if
	// the matchMedia function isn't available (very old browsers) then just
	// close whenever the browser is resized.
	if (typeof matchMedia === 'function') {
		// Important: this media query must match the break point in style.css
		matchMedia('(min-width: 680px)').addEventListener('change', closeMenus);
	} else {
		// Not all older browsers fire resize when the orientation changes, so
		// be safe and close menus on both. closeMenus will only close open
		// menus anyway, so no harm doing both, and we should be using
		// matchMedia above anyway
		win.addEventListener('resize', closeMenus, false);
		win.addEventListener('orientationchange', closeMenus, false);
	}

	function showDropdown(target) {
		if (shownDropdown) {
			hideDropdown();
		} else {
			if (!addedListeners) {
				// We want to set passive property on touch event listeners as that guarantees to the
				// browser that we won't preventDefault, and therefore improves scroll performance.
				// Since only modern browsers support this we need to test via a getter in the
				// options object to see if the passive property is accessed, and if so change the
				// passive var.
				try {
					var opts = Object.defineProperty({}, 'passive', {
						// eslint-disable-next-line getter-return
						get: function () {
							// the passive property has been accessed, so we can pass the passive option
							// in addEventListener
							passive = { passive: true };
						},
					});
					win.addEventListener('testPassive', null, opts);
					win.removeEventListener('testPassive', null, opts);
					// eslint-disable-next-line no-empty
				} catch (e) {}
				// Note: we just add the following listeners. It might seem better to
				// add/remove them when the dropdown is shown/hidden, but the way touch
				// works on mobile can mean switching dropdowns will remove then add
				// the listeners, so we just leave them listening.

				mainMenu = doc.getElementById('main-menu');
				// Once a dropdown is open, other dropdowns will open on mouseover
				// until the dropdown is closed by clicking it again, or clicking outside
				// the menu - basically the same as how menus on browsers work.
				mainMenu.addEventListener('mouseover', mouseOverMainMenu, false);
				mainMenu.addEventListener('touchstart', touchStartMainMenu, passive);
				doc.addEventListener('focus', docFocus, true);
				doc.getElementById('menu').addEventListener('keyup', closeNavOnEsc, false);
				addedListeners = true;
			}
		}
		dropdownUl = firstSiblingByTag(target.nextSibling, 'UL');
		target.className += ' open';
		dropdownUl.className += ' show';
		target.setAttribute('aria-expanded', 'true');
		shownDropdown = target;
	}

	function mouseOverMainMenu(event) {
		if (!shownDropdown) return;
		var target = event.target;
		if (target.getAttribute('data-toggle') === 'dropdown' && target !== shownDropdown) {
			showDropdown(target);
		}
	}

	function touchStartMainMenu(event) {
		// We close the dropdown here for touches on another dropdown. That's
		// because a touch event generates touch, mouseover, click, so if we
		// didn't close the sequence of events would be touch (ignored), then
		// mouseover which would switch the dropdown, then click which would
		// close it. By doing it this way the touch closes the current dropdown,
		// the mouseover is ignored as there's no shownDropdown, then the click
		// opens the new dropdown.

		// One alternative would be to use preventDefault (which would be
		// better in touchend as otherwise touchstart can't be passive which
		// causes scroll delays), but that can have funny side effects.

		// Another would be to change the way we do things, and have users click
		// a dropdown to open and close, and not use mouseover to change
		// dropdowns if one is already open
		if (
			shownDropdown &&
			event.target.getAttribute('data-toggle') === 'dropdown' &&
			event.target !== shownDropdown
		) {
			hideDropdown();
		}
	}

	/**
	 * For keyboard users we close the dropdown if focus is moved away from
	 * opened dropdown. That way when a uses tabs down a dropdown and onto
	 * the next parent then shift-tab won't force them to go back up the
	 * entire dropdown.
	 */
	function docFocus(event) {
		if (!shownDropdown) return;
		var target = event.target;
		// focused outside menu, so close
		if (
			target.className.substring(0, 3) != 'ma ' ||
			// focus inside menu, so close if we've moved to a different parent
			(target.className.indexOf(' mi0') !== -1 && shownDropdown !== target)
		) {
			hideDropdown();
		}
	}

	/* a11y close dropdown or pullout on esc */
	function closeNavOnEsc(event) {
		if (event.key !== 'Escape') return;
		if (shownDropdown) {
			var dd = shownDropdown;
			hideDropdown();
			dd.focus();
			return;
		}
		if (isPulloutMenuOpen) {
			hidePulloutMenu();
		}
	}

	function hideDropdown() {
		dropdownUl.className = dropdownUl.className.replace(/ show/g, '');
		shownDropdown.className = shownDropdown.className.replace(/ open/g, '');
		shownDropdown.setAttribute('aria-expanded', 'false');
		shownDropdown = false;
	}

	function tab(target) {
		if (target.getAttribute('aria-selected') === 'true') {
			return;
		}
		var tabs = target.parentNode.parentNode.getElementsByTagName('A');
		for (var i = tabs.length; i--; ) {
			if (tabs[i].getAttribute('aria-selected') === 'true') {
				var tab = tabs[i];
				var pane = doc.getElementById(tab.getAttribute('aria-controls'));
				pane.className = pane.className.replace(/ show/g, '');
				tab.className = tab.className.replace(/ active/g, '');
				tab.setAttribute('aria-selected', 'false');
			}
		}
		var showPane = doc.getElementById(target.getAttribute('aria-controls'));
		showPane.className += ' show';
		target.className += ' active';
		target.setAttribute('aria-selected', 'true');
	}

	function toggleCollapse(target) {
		// target is button, then content will be in next sibling div - which
		// may have an iframe child
		var div = firstSiblingByTag(target.nextSibling, 'DIV');
		if (target.getAttribute('aria-expanded') === 'true') {
			target.setAttribute('aria-expanded', 'false');
			target.className = target.className.replace(/ open/g, '');
			div.className = div.className.replace(/ show/g, '');
			return;
		}
		var iframe = firstSiblingByTag(div.firstChild, 'IFRAME');
		// having data-url attribute instead of src allows us to lazy load the
		// iframe only when the collapsed element is opened.
		if (iframe && !iframe.src) {
			var url = iframe.getAttribute('data-url');
			if (url) {
				iframe.src = url;
			}
		}
		div.className += ' show';
		target.className += ' open';
		target.setAttribute('aria-expanded', 'true');
	}

	function showPulloutMenu(event) {
		event.preventDefault(); // open menu is a link as a fallback if the user has javascript off
		if (pulloutTimeout) clearTimeout(pulloutTimeout);
		if (!menu) {
			showMenuBtn = event.target;
			menu = doc.getElementById('menu');
			mainMenu = doc.getElementById('main-menu');
			closeMenu = doc.getElementById('close-menu');
			// not all mobile browsers will dispatch click events on our overlay div, so we need
			// to detect touches
			doc.getElementById('overlay').addEventListener(
				'touchstart',
				function (event) {
					// We're closing the overlay so if we don't prevent default then the touch
					// could pass through to a link below
					event.preventDefault();
					hidePulloutMenu();
				},
				false
			);
			var aTags = mainMenu.getElementsByTagName('A');
			lastMenuLink = aTags[aTags.length - 1];
			menu.addEventListener('keyup', closeNavOnEsc, false);
			closeMenu.addEventListener('keydown', moveFocusToBottom, false);
		}
		setMenuParentTabIndex(-1); // a11y no tabbing onto menu parent as they are all open
		lastMenuLink.addEventListener('keydown', moveFocusToTop, false);
		// Separate class for visible as we want the menu to be visible immediately
		// in order to set focus on it, but we have a transition on the slide out.
		// On close the the visible class is removed on a timer otherwise the slide
		// in transition doesn't work
		menu.className += ' show visible';
		showMenuBtn.setAttribute('aria-expanded', 'true');
		// If the menu was previously shown and scrolled, then make sure
		// we reset the scroll position to the top again
		mainMenu.parentNode.scrollTop = 0;
		closeMenu.focus(); // a11y focus inside the pullout
		doc.body.style = 'overflow:hidden'; // stop scrolling of body behind the overlay
		isPulloutMenuOpen = true;
	}

	/**
	 * a11y stop/allow focus on menu parent items. This means when the pullout
	 * menu is open tab will miss the parent items (as they are already open)
	 */
	function setMenuParentTabIndex(tabIndex) {
		if (tabIndex === mpTabIndex || !doc.querySelectorAll) return;
		var mps = mainMenu.querySelectorAll('.mp');
		for (var i = mps.length; i--; ) {
			mps[i].tabIndex = tabIndex;
		}
		mpTabIndex = tabIndex;
	}

	/** a11y - keep focus inside pullout  */
	function moveFocusToTop(event) {
		if (event.key === 'Tab' && !event.shiftKey) {
			event.preventDefault();
			closeMenu.focus();
		}
	}

	function moveFocusToBottom(event) {
		if (event.key === 'Tab' && event.shiftKey) {
			event.preventDefault();
			lastMenuLink.focus();
		}
	}

	function hidePulloutMenu() {
		menu.className = menu.className.replace(/ show/g, '');
		showMenuBtn.setAttribute('aria-expanded', 'false');
		showMenuBtn.focus(); // a11y focus
		doc.body.style = '';
		pulloutTimeout = setTimeout(setMenuInvisible, 300);
		isPulloutMenuOpen = false;
	}

	function setMenuInvisible() {
		menu.className = menu.className.replace(/ visible/g, '');
	}

	function closeMenus() {
		if (shownDropdown) hideDropdown();
		if (isPulloutMenuOpen) hidePulloutMenu();
		if (lastMenuLink) {
			lastMenuLink.removeEventListener('keydown', moveFocusToTop, false);
			setMenuParentTabIndex(0);
		}
	}

	function firstSiblingByTag(el, tag) {
		while (el) {
			if (el.tagName === tag) {
				return el;
			}
			el = el.nextSibling;
		}
		return null;
	}
})();
