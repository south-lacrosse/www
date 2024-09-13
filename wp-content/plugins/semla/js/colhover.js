/**
 * IMPORTANT: Bump the version number in the plugin (search for "js/colhover")
 * if you make anything other than cosmetic changes to this file,
 *
 * This script adds 'hover' class to all td cells in the same column within a table which has
 * class 'col-hover'.
 * If the first column is a heading it should be a TH cell as that will be ignored.
 * Only cells within tbody are affected, and will work with multiple tables on the same page.
 *
 * The .hover class can then be styled to highlight the column
 * Format of table must be table>tbody>tr>th+td
 *
 * Used on fixtures grid so uses can highlight row and column at the same time.
 */
(function () {
	'use strict';
	// this is a progressive enhancement, so only run if a browser has the
	// required capabilities
	if (document.querySelector && document.body.classList) {
		// add event listener to tbody - otherwise we'd have to put
		// a listener on all cells with the table
		var tbodys = document.querySelectorAll('.col-hover>tbody');
		for (var i = tbodys.length; i--; ) {
			tbodys[i].addEventListener('mouseover', mouseover, false);
			tbodys[i].addEventListener('mouseout', mouseout, false);
		}
	}

	function mouseover(event) {
		var target = event.target;
		// only process TD elements, if first column is a header it should be TH
		if (target.tagName !== 'TD') {
			return;
		}
		// add .hover to rest of column
		var col = position(target);
		// td->parent(tr)->parent(tbody)
		var rows = target.parentNode.parentNode.getElementsByTagName('tr');
		for (var i = rows.length; i--; ) {
			var cellsInRow = rows[i].getElementsByTagName('td');
			if (col < cellsInRow.length) {
				cellsInRow[col].classList.add('hover');
			}
		}
	}

	function mouseout(event) {
		var target = event.target;
		// only process TD elements
		if (target.tagName !== 'TD') {
			return;
		}
		// remove .hover from all tbody td cells
		// td->parent(tr)->parent(tbody)
		var hoverCells = target.parentNode.parentNode.querySelectorAll('td.hover');
		for (var i = hoverCells.length; i--; ) {
			hoverCells[i].classList.remove('hover');
		}
	}

	// Returns the relative position of a td node within it's parent.
	// Note getElementsByTagName is an HTMLCollection not an array, so indexOf
	// doesn't work
	function position(node) {
		var children = node.parentNode.getElementsByTagName('td');
		for (var i = 0; i < children.length; i++) {
			if (children[i] === node) {
				return i;
			}
		}
		return -1;
	}
})();
