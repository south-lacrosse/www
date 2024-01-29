/**
 * Inline edit for admin list tables, enqueued by Inline_Edit_List_Table
 *
 * This is done by hiding the item's row in the list table, and adding an edit
 * row with all the editable fields and update/cancel buttons. The updates are
 * then sent to a REST endpoint passed in the semlaEdit global.
 */
/* global semlaEdit, wp */
(function () {
	const theList = document.getElementById('the-list');

	/**
	 * The editing row, and its original parent. The editRow is moved to the
	 * list table, or back to its parent. We need to keep it in the DOM so the
	 * colspan is updated if the user changes the hidden columns.
	 */
	let editRow, editRowParent;
	/**
	 * The data in the table that is currently being edited
	 */
	let dataRow, dataFields;
	/**
	 * Elements in the editRow
	 */
	let primaryTitle, fields, firstField, spinner, errorNotice, errorMessage;

	theList.addEventListener(
		'click',
		function (e) {
			const target = e.target;
			if (target.classList.contains('editinline')) {
				if (!editRow) initEditRow();
				inlineEdit(target);
			}
		},
		false
	);

	/**
	 * Initialise the edit row. Adds listeners, save references to elements that
	 * will be used repeatedly
	 */
	function initEditRow() {
		editRow = document.getElementById('inline-edit');
		editRowParent = editRow.parentNode;

		/**
		 * Save and cancel buttons
		 */
		editRow.querySelector('.save').addEventListener('click', save);
		editRow.querySelector('.cancel').addEventListener('click', revertEditRow);

		/**
		 * Cancel inline editing when pressing Escape inside the inline editor.
		 */
		editRow.addEventListener('keyup', function (e) {
			if (e.key === 'Escape') revertEditRow();
		});

		const saveOnEnter = function (e) {
			if (e.key === 'Enter') {
				e.preventDefault();
				save();
			}
		};

		fields = {};
		editRow.querySelectorAll('input, textarea').forEach((elem) => {
			// Save the inline edits when pressing Enter inside the inline editor.
			elem.addEventListener('keydown', saveOnEnter);
			// save fields
			if (!firstField) firstField = elem;
			fields[elem.name] = elem;
		});

		primaryTitle = editRow.querySelector('.inline-edit-title');
		spinner = editRow.querySelector('.spinner');
	}

	function inlineEdit(elem) {
		if (editRow.parentNode !== editRowParent) {
			// in case we are moving from editing one item to another
			resetDataRow();
		}
		// remove here before we update the fields
		editRow.remove();

		dataRow = elem.closest('tr');
		dataFields = {};
		const cells = dataRow.cells;

		for (let c = 0; c < cells.length; c++) {
			const cell = cells[c];
			if (cell.classList.contains('column-primary')) {
				primaryTitle.textContent = cell.innerText;
				continue;
			}
			const colname = cell.dataset.colname;
			if (colname && fields[colname]) {
				fields[colname].value = cell.innerText;
				dataFields[colname] = cell;
			}
		}

		elem.setAttribute('aria-expanded', 'true');
		dataRow.hidden = true;
		dataRow.insertAdjacentElement('afterend', editRow);
		// extra row to keep striping
		dataRow.insertAdjacentHTML('afterend', '<tr class="hidden"></tr>');
		firstField.focus();
	}

	/**
	 * Re-show the data row, and hide the inline edit row
	 */
	function revertEditRow() {
		resetDataRow(true);
		// move edit row back to original parent
		editRowParent.appendChild(editRow);
	}

	async function save() {
		spinner.classList.add('is-active');
		resetErrorMessage();
		const url =
			semlaEdit.url +
			// if we have an id then assume it's in the format comp-123 where 123 is the id
			(dataRow.id
				? dataRow.id.substring(dataRow.id.indexOf('-') + 1)
				: primaryTitle.textContent.replaceAll(' ', '+'));
		const data = {};
		for (const [colname, input] of Object.entries(fields)) {
			data[colname] = input.value;
		}
		try {
			const response = await fetch(url, {
				method: 'PUT',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': semlaEdit.nonce,
				},
				body: JSON.stringify(data),
			});
			if (!response.ok) {
				let text;
				if (response.headers.get('Content-Type').startsWith('application/json')) {
					const errorJson = await response.json();
					text = errorJson.message;
					console.log('Fetch error', errorJson);
				} else {
					text = await response.text();
				}
				throw new Error(`Response status code ${response.status}: ${text}`);
			}
			// response may contain a refreshed nonce
			const nonce = response.headers.get('X-Wp-Nonce');
			if (nonce) semlaEdit.nonce = nonce;
			// fade in the edited row
			dataRow.style.opacity = 0;
			revertEditRow();
			dataRow.style.opacity = 1;
			// We allow the server to alter the sent data if needed, so update
			// the data using the server response
			const json = await response.json();
			for (const [colname, value] of Object.entries(json)) {
				if (dataFields[colname]) dataFields[colname].textContent = value;
			}
			wp.a11y.speak('Changes saved.');
		} catch (error) {
			setErrorMessage('Error while saving the changes. ' + error.message);
			console.error(error);
		} finally {
			spinner.classList.remove('is-active');
		}
	}

	/**
	 * Revert changes by inline edit, so remove extra hidden row, and unhide the
	 * data row. Note that it doesn't remove the inline edit row as that will be
	 * moved by the cancel/inlineEdit functions.
	 */
	function resetDataRow(focus = false) {
		theList.querySelectorAll('tr.hidden').forEach((elem) => elem.remove());
		const button = dataRow.querySelector('button.editinline');
		button.setAttribute('aria-expanded', 'false');
		dataRow.hidden = false;
		resetErrorMessage();
		if (focus) button.focus();
	}

	function setErrorMessage(message) {
		if (!errorNotice) {
			errorNotice = editRow.querySelector('.notice-error');
			errorMessage = errorNotice.querySelector('.error');
		}
		errorNotice.classList.remove('hidden');
		errorMessage.textContent = message;
		wp.a11y.speak(message);
	}

	function resetErrorMessage() {
		if (errorNotice) errorNotice.classList.add('hidden');
	}
})();
