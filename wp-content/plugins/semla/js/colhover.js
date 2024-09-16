/**
 * IMPORTANT: Bump the version number in the plugin (search for "js/colhover")
 * if you make anything other than cosmetic changes to this file,
 *
 * This script adds 'hover' class to all td cells in the same column as the mouse is
 * over within a table which has class 'col-hover'.
 *
 * If the first column is a heading it should be a TH cell, as hovering over that cell
 * will add the hover class to the appropriate column.
 *
 * Only cells within tbody are affected, and will work with multiple tables on the same page.
 *
 * The .hover class can then be styled to highlight the column
 * Format of table must be table>tbody>tr>th+td
 *
 * Used on fixtures grid so uses can highlight row and column at the same time.
 */
(function () {
	'use strict';
	var lastCell;
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
		if (target.tagName === 'A') {
			target = target.parentNode;
		}
		// ignore moving from A to TH/TD, or TH/TD to A
		if (target === lastCell) return;
		var cells;
		if (target.tagName === 'TH') {
			// for ladders we don't highlight a column when the mouse is over the
			// row heading as it's not the same team like on a normal grid
			var tbody = target.parentNode.parentNode;
			if (tbody.parentNode.classList.contains('ladder')) {
				lastCell = target;
				return;
			}
			// for TH rows the column will be the row number, so find all row
			// headings
			cells = tbody.getElementsByTagName('th');
		} else if (target.tagName == 'TD') {
			// for TD the offset will be the the offset of the TD in the row
			cells = target.parentNode.getElementsByTagName('td');
		} else {
			return;
		}
		for (var offset = cells.length; offset--; ) {
			if (cells[offset] === target) {
				break;
			}
		}
		if (offset === -1) {
			console.log('could not find', target, 'in cells', rows);
			return;
		}
		lastCell = target;
		// add .hover to rest of column
		// td->parent(tr)->parent(tbody)
		var rows = target.parentNode.parentNode.getElementsByTagName('tr');
		for (var i = rows.length; i--; ) {
			var cellsInRow = rows[i].getElementsByTagName('td');
			if (offset < cellsInRow.length) {
				cellsInRow[offset].classList.add('hover');
			}
		}
	}

	function mouseout(event) {
		var target = event.target;
		if (
			!(target.tagName === 'TD' || target.tagName === 'TH') ||
			// ignore mouseout into link inside the cell
			(event.relatedTarget && event.relatedTarget.tagName === 'A')
		) {
			return;
		}
		lastCell = null;
		// remove .hover from all tbody td cells
		// td->parent(tr)->parent(tbody)
		var hoverCells = target.parentNode.parentNode.querySelectorAll('td.hover');
		for (var i = hoverCells.length; i--; ) {
			hoverCells[i].classList.remove('hover');
		}
	}
})();
