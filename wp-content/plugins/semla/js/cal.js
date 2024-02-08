/**
 * IMPORTANT: If you change this remember to bump the version number
 * in the Block_Calendar.php
 *
 * Display a Google Calendar
 */
/* global gapi */
(function () {
	'use strict';
	var days = 'Sun_Mon_Tue_Wed_Thu_Fri_Sat'.split('_');
	var months =
		'January_February_March_April_May_June_July_August_September_October_November_December'.split(
			'_'
		);

	// Load the JavaScript client library and invoke the loadCalendar function afterwards.
	gapi.load('client', loadCalendar);

	function loadCalendar() {
		gapi.client
			.init({
				apiKey: window.semla.gapi,
				discoveryDocs: ['https://www.googleapis.com/discovery/v1/apis/calendar/v3/rest'],
			})
			.then(fetchEvents)
			.then(onSuccess, onError);
	}

	function fetchEvents() {
		// Events: list API docs: https://developers.google.com/calendar/v3/reference/events/list
		return gapi.client.calendar.events.list({
			calendarId: window.semla.cid,
			eventTypes: ['default'],
			timeZone: 'Europe/London',
			singleEvents: true,
			timeMin: new Date().toISOString(), //gathers only events not happened yet
			// maxResults: 20,
			orderBy: 'startTime',
			fields:
				'items(summary,start,end' +
				(window.semla.calEnhanced ? ',description,location' : '') +
				')',
			// ,htmlLink
		});
	}

	function onSuccess(response) {
		var events = response.result.items;
		if (!events || events.length === 0) {
			setContent('<p>No pending events.</p>');
			return;
		}

		var calendarRows = ['<dl class="calendar">'];
		var lastYearMonth = '';
		events.forEach(function (entry) {
			var startDate = entry.start.date
				? entry.start.date
				: entry.start.dateTime.substring(0, 10);
			var yearMonth = startDate.substring(0, 8);
			if (yearMonth !== lastYearMonth) {
				calendarRows.push(
					'<dt>' +
						months[parseInt(yearMonth.substring(5, 7), 10) - 1] +
						' ' +
						yearMonth.substring(0, 4) +
						'</dt>'
				);
				lastYearMonth = yearMonth;
			}
			var day = parseInt(startDate.substring(8, 10), 10);
			var startDateDate = new Date(startDate);
			var line = days[startDateDate.getDay()] + ' ' + day + ordinal(day);
			var endDate;
			if (entry.start.dateTime) {
				line += ', ';
				var startTime = parseTime(entry.start.dateTime);
				var endTime = parseTime(entry.end.dateTime);
				endDate = entry.end.dateTime.substring(0, 10);
				if (startDate === endDate) {
					if (startTime.ampm === endTime.ampm) {
						startTime.ampm = '';
					}
					line += startTime.time + startTime.ampm + '-' + endTime.time + endTime.ampm;
				} else {
					line +=
						startTime.time +
						startTime.ampm +
						' - ' +
						formatEndDate(startDate, endDate) +
						', ' +
						endTime.time +
						endTime.ampm;
				}
			} else {
				var date = new Date(entry.end.date);
				// subtract 1 day, or 24 * 60 * 60 * 1000 seconds
				date.setTime(date.getTime() - 86400000);
				endDate = date.toISOString().split('T')[0];
				if (endDate !== startDate) {
					line += ' - ' + formatEndDate(startDate, endDate);
				}
			}
			var summary = entry.summary;
			var mark = '';
			if (window.semla.calEnhanced) {
				var split = summary.split(':');
				if (split.length > 1) {
					summary = split[0].trim();
					mark = ' <mark class="tag';
					var tag = split[1].trim();
					if (window.semla.calTags && window.semla.calTags[tag]) {
						mark += ' semla__tag' + window.semla.calTags[tag];
					}
					mark += '">' + tag + '</mark>';
				}
			}
			// entry.description and location can only be set if we are in enhanced mode
			if (entry.description) {
				var url = extractLink(entry.description);
				if (url) {
					summary = '<a href="' + url + '">' + summary + '</a>';
				}
			} else {
				summary = '<b>' + summary + '</b>';
			}
			line += ' - ' + summary;
			if (entry.location) {
				line += ' ' + entry.location;
			}
			line += mark;
			calendarRows.push('<dd>' + line + '</dd>');
		});
		calendarRows.push('</dl>');
		setContent(calendarRows.join(''));
	}

	function onError(reason) {
		console.log(reason);
		setContent('<p>Error: ' + reason.result.error.message + '</p>');
	}

	// Utility functions =======================================================

	function ordinal(number) {
		if (parseInt((number % 100) / 10, 10) === 1) {
			// 10th,11th,12th etc
			return 'th';
		}
		var b = number % 10;
		if (b === 1) {
			return 'st';
		}
		if (b === 2) {
			return 'nd';
		}
		if (b === 3) {
			return 'rd';
		}
		return 'th';
	}

	function parseTime(dateTime) {
		var hh = parseInt(dateTime.substring(11, 13), 10);
		var mm = dateTime.substring(14, 16);
		var ampm;
		if (hh > 11) {
			ampm = 'pm';
			if (hh > 12) {
				hh -= 12;
			}
		} else {
			ampm = 'am';
		}
		var time = '' + hh;
		if (mm !== '00') {
			time += ':' + mm;
		}
		return { time: time, ampm: ampm };
	}

	function formatEndDate(startDate, endDate) {
		var endDateDate = new Date(endDate);
		var day = parseInt(endDate.substring(8, 10), 10);
		var str = days[endDateDate.getDay()] + ' ' + day + ordinal(day);
		var endMonth = endDate.substring(5, 7);
		if (endMonth !== startDate.substring(5, 7)) {
			str += ' ' + months[parseInt(endMonth, 10) - 1];
		}
		var endYear = endDate.substring(0, 4);
		if (endYear !== startDate.substring(0, 4)) {
			str += ' ' + endYear;
		}
		return str;
	}

	/* Gets the first link from text. To extract multiple add g option */
	function extractLink(str) {
		var found = str.match(/(\b(https?):\/\/[-A-Z0-9+&@#/%?=~_|!:,.;]*[-A-Z0-9+&@#/%=~_|])/i);
		return found ? found[0] : false;
	}

	function setContent(str) {
		var cal = document.getElementById('semla_cal');
		if (!cal) {
			console.log('Error: no div with id semla_cal');
		} else {
			cal.innerHTML = str;
		}
	}
})();
