/**
 * IMPORTANT: Bump the version number in the plugin (search for "js/colhover")
 * if you make anything other than cosmetic changes to this file,
 *
 * This script adds 'hover' class to all td cells in the same column within a table which has
 * class 'col-hover'.
 * The first column is excluded as that's assumed to be a row header.
 * Only cells within tbody are affected, and will work with multiple tables on the same page.
 *
 * The .hover class can then be styled to highlight the column
 * Format of table must be table>tbody>tr>td
 *
 * Used on fixtures grid so uses can highlight row and column at the same time.
 */
(function () {
	'use strict';
	if (document.querySelector) {
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
		// only process TD elements, and ignore first column
		if (target.tagName !== 'TD' || target === target.parentNode.firstElementChild) {
			return;
		}
		// add .hover to rest of column
		var index = position(target);
		// td->parent(tr)->parent(tbody)
		var rows = target.parentNode.parentNode.getElementsByTagName('tr');
		for (var j = rows.length; j--; ) {
			var cellsInRow = rows[j].getElementsByTagName('td');
			if (index < cellsInRow.length) {
				// don't use classlist as that doesn't exist in IE9
				var cell = cellsInRow[index];
				if (cell.className.indexOf('hover') === -1) {
					cell.className += ' hover';
				}
			}
		}
	}

	function mouseout(event) {
		var target = event.target;
		// only process TD elements, and ignore first column
		if (target.tagName !== 'TD' || target === target.parentNode.firstElementChild) {
			return;
		}
		// remove .hover from all tbody td cells
		var cellsInTable = target.parentNode.parentNode.getElementsByTagName('td');
		for (var j = cellsInTable.length; j--; ) {
			var cell = cellsInTable[j];
			cell.className = cell.className.replace(' hover', '');
		}
	}

	// Returns the relative position of a node within it's parent
	function position(node) {
		var children = node.parentNode.childNodes;
		var num = 0;
		for (var i = 0; i < children.length; i++) {
			if (children[i] === node) {
				return num;
			}
			if (children[i].nodeType === Node.ELEMENT_NODE) {
				num++;
			}
		}
		return -1;
	}
})();
