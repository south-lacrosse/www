/**
 * Inline edit for admin list tables, enqueued by Inline_Edit_List_Table
 *
 * This is done by hiding the item's row in the list table, and adding an edit
 * row with all the editable fields and update/cancel buttons. The updates are
 * then sent to a REST endpoint passed in the semlaEdit global.
 */
/* global semlaEdit, wp */
/* eslint-env es6 */
(function () {
	const theList = document.getElementById('the-list');

	/**
	 * The editing row, and its original parent. The editRow is moved to the
	 * list table, or back to its parent. We need to keep it in the DOM so the
	 * colspan is updated if the user changes the hidden columns.
	 */
	let editRow, editRowParent;
	/**
	 * The data in the table that is currently being edited. dataFields is keyed
	 * on name
	 */
	let dataRow, dataFields;
	/**
	 * Elements in the editRow. fields is keyed on colname
	 */
	let primaryTitle, fields, firstField, spinner, errorNotice, errorMessage;
	/**
	 * If we are sending to a standard WordPress endpoint then the query is set
	 * to restrict the fields returned.
	 */
	let query;

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

		fields = new Map();
		editRow.querySelectorAll('input, textarea').forEach((inputField) => {
			// Save the inline edits when pressing Enter inside the inline
			// editor input fields only
			if (inputField.nodeName === 'INPUT') {
				inputField.addEventListener('keydown', saveOnEnter);
			}
			// save fields
			if (!firstField) firstField = inputField;
			fields.set(inputField.dataset.colname, inputField);
		});

		primaryTitle = editRow.querySelector('.inline-edit-title');
		spinner = editRow.querySelector('.spinner');
	}

	function inlineEdit(editButton) {
		if (editRow.parentNode !== editRowParent) {
			// in case we are moving from editing one item to another
			resetDataRow();
		}
		// remove here before we update the fields
		editRow.remove();

		dataRow = editButton.closest('tr');
		dataFields = new Map();
		const cells = dataRow.cells;

		for (let c = 0; c < cells.length; c++) {
			const cell = cells[c];
			if (cell.classList.contains('column-primary')) {
				primaryTitle.textContent = cell.innerText;
				continue;
			}
			const colname = cell.dataset.colname;
			if (colname && fields.has(colname)) {
				const editInput = fields.get(colname);
				editInput.value = cell.innerText;
				// key is the name of the field as that's used in the response
				// from the server
				dataFields.set(editInput.name, cell);
			}
		}

		editButton.setAttribute('aria-expanded', 'true');
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
		let url =
			semlaEdit.url +
			// if we have an id then assume it's in the format comp-123 where 123 is the id

			// spaces to _, and Apache will just reject encoded "/" (%2F) for security reasons,
			// so convert to ~
			(dataRow.id
				? dataRow.id.substring(dataRow.id.indexOf('-') + 1)
				: encodeURI(primaryTitle.textContent.replaceAll(' ', '_').replaceAll('/', '~')));

		if (semlaEdit.wpMeta) {
			// if we're using the standard WP REST endpoints to update metadata
			// then make sure we only return the fields necessary
			if (!query) {
				const metaKeys = [];
				for (const input of fields.values()) {
					metaKeys.push(input.name);
				}
				query = '?_fields=meta.' + metaKeys.join(',meta.');
			}
			url += query;
		}
		let data = {};
		for (const input of fields.values()) {
			data[input.name] = input.value.trim();
		}
		if (semlaEdit.wpMeta) {
			data = { meta: data };
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
			// We allow the server to alter the sent data if needed, so update
			// the data using the server response
			const json = await response.json();
			for (const [key, value] of Object.entries(semlaEdit.wpMeta ? json.meta : json)) {
				if (dataFields.has(key)) dataFields.get(key).innerHTML = value;
			}
			// fade in the edited row, assumes CSS transitions set
			dataRow.style.opacity = 0;
			revertEditRow();
			dataRow.style.opacity = 1;
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
