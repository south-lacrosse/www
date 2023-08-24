/**
 * Google Map of the clubs
 *
 * Docs at https://developers.google.com/maps/documentation/javascript/reference
 *
 * IMPORTANT: If you change this remember to bump the version number in the
 * plugin. Just search for "js/map" to find the reference.
 */
/* eslint-env es2017 */
(function () {
	'use strict';
	let mapDiv,
		mapKeyDiv,
		clubsList,
		footer,
		searchBox,
		map,
		mapContainerDiv = null,
		clubsBounds,
		infoWindow = null,
		geocoder = null,
		zoomInToClub = 9, // min zoom when we pan to a selected club
		clubsInAlphaOrder = true;

	const componentRestrictions = { country: 'gb' }; // autocomplete/geocode restriction

	const GEOCODER_STATUS_DESCRIPTION = {
		UNKNOWN_ERROR:
			'The request could not be successfully processed, yet the exact reason for the failure is not known',
		OVER_QUERY_LIMIT: 'The webpage has gone over the requests limit in too short a time',
		REQUEST_DENIED: 'The webpage is not allowed to use the geocoder',
		INVALID_REQUEST: 'This request was invalid',
		ZERO_RESULTS: 'The address is unknown, please try another',
		ERROR: 'There was a problem contacting the Google servers',
	};

	async function initMap() {
		const { Map } = await google.maps.importLibrary('maps');
		const { ControlPosition, LatLng, LatLngBounds } = await google.maps.importLibrary('core');
		const { Autocomplete } = await google.maps.importLibrary('places');

		mapDiv = document.getElementById('map');
		mapKeyDiv = document.getElementById('map-key');
		clubsList = document.getElementById('clubs-list');
		footer = document.getElementById('page-footer');
		// now resize the map to fill the available size
		resizeMapDiv();
		// make sure when the browser window is resized that the map resizes too
		window.addEventListener('resize', resizeMapDiv, false);

		const clubsLength = SemlaClubs.length;
		// see what the maximum bounds the map needs to display all the clubs
		clubsBounds = new LatLngBounds();
		for (let i = clubsLength; i--; ) {
			const club = SemlaClubs[i];
			club.latLng = new LatLng(club.lat, club.lng);
			clubsBounds.extend(club.latLng);
		}

		map = new Map(mapDiv, {
			center: clubsBounds.getCenter(),
			fullscreenControl: true,
			scaleControl: true,
			restriction: {
				// restrict map to a reasonable area around SEMLA area - sw/ne corners
				latLngBounds: new LatLngBounds(new LatLng(49, -6), new LatLng(55.5, 2.5)),
				strictBounds: false,
			},
		});
		map.fitBounds(clubsBounds);

		let html = '';
		// create markers for each club, and the html for the key
		for (let i = 0; i < clubsLength; i++) {
			const club = SemlaClubs[i];
			const marker = new google.maps.Marker({
				map: map,
				title: club.name,
				optimized: false, // need this otherwise markers are optimized to single element, and title won't show
				position: club.latLng,
				semlaClub: club, // add custom property for club data
			});
			club.marker = marker;
			club.order = i;
			marker.addListener('click', markerClick);

			html += '<li title="' + club.name + '">' + club.name + '</li>';
		}

		// add list of clubs to the key
		clubsList.innerHTML = html;
		// whenever a club name is clicked then zoom and pan to that club's location,
		// and open an info window
		clubsList.addEventListener(
			'click',
			function (event) {
				const club = SemlaClubs[positionWithinParent(event.target)];
				zoomPan(club.latLng);
				showClubInfoWindow(club);
			},
			false
		);

		document.getElementById('toggle-key').addEventListener(
			'click',
			function (event) {
				event.preventDefault();
				if (!mapContainerDiv) {
					mapContainerDiv = document.getElementById('map-container');
				}
				if (mapContainerDiv.className.indexOf('hide-key') > -1) {
					mapContainerDiv.className = mapContainerDiv.className.replace(' hide-key', '');
					this.innerHTML = 'Hide Key';
				} else {
					mapContainerDiv.className += ' hide-key';
					this.innerHTML = 'Show Key';
				}
				google.maps.event.trigger(map, 'resize');
			},
			false
		);

		// add a control to centre the map
		const centreControl = document.createElement('div');
		centreControl.className = 'map-ctl';
		centreControl.title = 'Centre all clubs on the map';
		centreControl.innerHTML = '<button type="button" class="map-ctl-text">Centre</button>';
		centreControl.firstElementChild.addEventListener('click', function () {
			map.fitBounds(clubsBounds);
			map.setCenter(clubsBounds.getCenter());
		});
		map.controls[ControlPosition.LEFT_TOP].push(centreControl);

		// add an autocomplete search box
		const searchControl = document.createElement('div');
		searchControl.className = 'search-wrapper';
		searchControl.innerHTML =
			'<input class="search-box" type="text" placeholder="Search for clubs near..." size="30">' +
			'<span class="search-reset" title="Reset search for nearest clubs">&times;</span>';
		searchBox = searchControl.firstElementChild;
		const autocomplete = new Autocomplete(searchBox, {
			fields: ['name', 'geometry.location'],
			componentRestrictions,
		});
		autocomplete.addListener('place_changed', function () {
			const place = this.getPlace();
			if (!place.geometry) {
				if (place.name.trim() === '') {
					reorderClubsAlpha();
					return;
				}
				if (!geocoder) {
					geocoder = new google.maps.Geocoder();
				}
				geocoder.geocode(
					{ address: place.name.trim(), componentRestrictions },
					geocoderResponse
				);
				return;
			}
			showNearestClubs(place.geometry.location);
		});
		searchControl.lastElementChild.addEventListener('click', function () {
			searchBox.value = '';
			reorderClubsAlpha();
		});
		map.controls[ControlPosition.TOP_RIGHT].push(searchControl);
	}

	// event listeners for markers
	function markerClick() {
		showClubInfoWindow(this.semlaClub);
	}

	// callback from geocoder
	function geocoderResponse(results, status) {
		if (status === google.maps.GeocoderStatus.OK) {
			showNearestClubs(results[0].geometry.location);
		} else {
			alert(GEOCODER_STATUS_DESCRIPTION[status]);
		}
	}

	// pan to new position, or setCenter if zooming
	function zoomPan(position) {
		if (map.getZoom() < zoomInToClub) {
			map.setZoom(zoomInToClub);
			map.setCenter(position);
		} else {
			map.panTo(position);
		}
	}

	function showClubInfoWindow(club) {
		// Reuse the one infoWindow, just change the text and marker.
		if (!infoWindow) {
			infoWindow = new google.maps.InfoWindow();
		}
		let html = '<h2 style="margin:0;border:0;font-size:1rem">' + club.name + '</h2>';
		if (!clubsInAlphaOrder) {
			html +=
				'<p style="margin:0;font-size:0.75rem">(' + club.dist + ' miles from search)</p>';
		}
		html +=
			'<p style="margin:1em 0 0;font-size:1rem">' +
			club.html +
			' <a style="margin-left:2em" href="https://www.google.co.uk/maps/dir//' +
			club.latLng.toUrlValue() +
			'/@' +
			club.latLng.toUrlValue() +
			',16z">Directions</a></p>';
		infoWindow.setContent(html);
		infoWindow.open({ anchor: club.marker, map });
	}

	function closeInfoWindow() {
		if (infoWindow) {
			infoWindow.close();
		}
	}

	// Returns the relative position of a node within its parent
	function positionWithinParent(node) {
		const children = node.parentNode.childNodes;
		let num = 0;
		for (let i = 0, len = children.length; i < len; i++) {
			if (children[i] === node) {
				return num;
			}
			if (children[i].nodeType === 1) {
				num++;
			}
		}
		return -1;
	}

	// resize the map div container to fill available screen area, or 300px minimum
	function resizeMapDiv() {
		const mapY = getPosY(mapDiv);
		// evaluate the height needed by other elements on the page
		const heightBetweenMapAndFooter = getPosY(footer) - mapY - mapDiv.offsetHeight;

		let newHeight = window.innerHeight - mapY - heightBetweenMapAndFooter - footer.offsetHeight;
		newHeight = Math.max(300, newHeight);
		const ht = Math.round(newHeight) + 'px';
		mapDiv.style.height = ht;
		mapKeyDiv.style.height = ht;
		if (map) {
			google.maps.event.trigger(map, 'resize');
		}
	}
	function getPosY(node) {
		let y = 0;
		while (node) {
			y += node.offsetTop;
			node = node.offsetParent;
		}
		return y;
	}

	function showNearestClubs(position) {
		zoomPan(position);

		// compute distance to all clubs from position
		const clubsLength = SemlaClubs.length;
		const positionLatRad = deg2rad(position.lat());
		const positionLatRadSin = Math.sin(positionLatRad);
		const positionLatRadCos = Math.cos(positionLatRad);
		for (let i = clubsLength; i--; ) {
			const club = SemlaClubs[i];
			const clubLatRad = deg2rad(club.lat);
			club.dist =
				3959.0 *
				Math.acos(
					positionLatRadSin * Math.sin(clubLatRad) +
						positionLatRadCos *
							Math.cos(clubLatRad) *
							Math.cos(deg2rad(position.lng() - club.lng))
				);
		}
		// and now sort into distance order
		SemlaClubs.sort(sortByDistance);
		clubsInAlphaOrder = false;
		closeInfoWindow();
		const keyLiNodes = clubsList.getElementsByTagName('li');
		for (let i = clubsLength; i--; ) {
			const club = SemlaClubs[i];
			club.order = i;
			club.dist = club.dist.toFixed(2);
			const clubTitle = i + 1 + '. ' + club.name + ' ' + club.dist + ' mi';
			club.marker.setLabel('' + (i + 1));
			club.marker.setTitle(clubTitle);
			keyLiNodes[i].textContent = clubTitle;
			keyLiNodes[i].setAttribute('title', clubTitle);
		}
	}

	function sortByDistance(a, b) {
		return a.dist - b.dist;
	}
	function sortByName(a, b) {
		if (a.name < b.name) {
			return -1;
		}
		return a.name > b.name ? 1 : 0;
	}

	function reorderClubsAlpha() {
		if (clubsInAlphaOrder) {
			return;
		}
		SemlaClubs.sort(sortByName);
		clubsInAlphaOrder = true;
		closeInfoWindow();
		const keyLiNodes = clubsList.getElementsByTagName('li');
		for (let i = SemlaClubs.length; i--; ) {
			const club = SemlaClubs[i];
			club.order = i;
			club.marker.setLabel('');
			club.marker.setTitle(club.name);
			keyLiNodes[i].textContent = club.name;
			keyLiNodes[i].setAttribute('title', club.name);
		}
	}

	// convert degrees to radians, which are needed for distance calculations
	function deg2rad(angle) {
		return (angle * Math.PI) / 180;
	}

	initMap();
})();
