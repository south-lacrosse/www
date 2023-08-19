/**
 * IMPORTANT: If you change this remember to bump the version number in
 * the plugin. Just search for "js/map" to find the reference.
 */
/* eslint-env es2017 */
(function () {
	'use strict';
	let mapDiv,
		mapKeyDiv,
		footer,
		searchBox,
		map,
		mapContainerDiv = null,
		mapBounds,
		infoWindow = null,
		currentMapCenter = null,
		geocoder = null,
		closeZoom = 9,
		clubsInAlphaOrder = true;

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
		// https://developers.google.com/maps/documentation/javascript/reference
		const { Map, MapTypeControlStyle } = await google.maps.importLibrary('maps');
		const { ControlPosition, LatLng, LatLngBounds } = await google.maps.importLibrary('core');
		const { Autocomplete } = await google.maps.importLibrary('places');

		mapDiv = document.getElementById('map');
		mapKeyDiv = document.getElementById('map-key');
		footer = document.getElementById('page-footer');
		// now resize the map to fill the available size
		resizeMapDiv();
		// make sure when the browser window is resized that the map resizes too
		window.addEventListener('resize', resizeMapDiv, false);

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
				if (map) {
					google.maps.event.trigger(map, 'resize');
				}
			},
			false
		);

		const clubsLength = SemlaClubs.length;
		let i;
		// see what the maximum bounds the map needs to display all the clubs
		mapBounds = new LatLngBounds();
		for (i = clubsLength; i--; ) {
			const club = SemlaClubs[i];
			club.latLng = new LatLng(club.lat, club.lng);
			mapBounds.extend(club.latLng);
		}

		map = new Map(mapDiv, {
			center: mapBounds.getCenter(),
			fullscreenControl: true,
			scaleControl: true,
			mapTypeControlOptions: { style: MapTypeControlStyle.DROPDOWN_MENU },
			zoom: 6,
		});
		map.fitBounds(mapBounds);

		// when a map is resized by default it won't recentre, so do that here
		map.addListener('resize', function () {
			currentMapCenter = this.getCenter();
		});
		map.addListener('bounds_changed', function () {
			if (currentMapCenter) {
				this.setCenter(currentMapCenter);
			}
			currentMapCenter = null;
		});

		let html = '';
		// create markers for each club, and the html for the key
		for (i = 0; i < clubsLength; i++) {
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

			html += '<li>' + club.name + '</li>';
		}

		const clubsList = document.getElementById('clubs-list');
		// add list of clubs to the key
		clubsList.innerHTML = html;
		// whenever a club name is clicked the zoom and pan to that club's location,
		// and open an info window
		clubsList.addEventListener(
			'click',
			function (event) {
				const i = positionWithinParent(event.target);
				const club = SemlaClubs[i];
				zoomPan(club.latLng);
				showClubInfoWindow(club);
			},
			false
		);

		// create and add a control to centre the map
		const centreControlDiv = document.createElement('div');
		centreControlDiv.className = 'gmnoprint';

		const centreControlUI = document.createElement('div');
		centreControlUI.className = 'map-ctl';
		centreControlUI.title = 'Recentre the map';
		centreControlUI.innerHTML = '<div class="map-ctl-text">Centre</div>';
		centreControlUI.addEventListener('click', centreMap);
		centreControlDiv.appendChild(centreControlUI);

		map.controls[ControlPosition.LEFT_TOP].push(centreControlDiv);

		// the search box on our page will be added to the map
		map.controls[ControlPosition.TOP_RIGHT].push(document.getElementById('search-wrapper'));

		searchBox = document.getElementById('search-box');
		const autocomplete = new Autocomplete(searchBox, {
			bounds: new LatLngBounds(
				new LatLng(50, -6), //sw
				new LatLng(54, 2) //ne
			),
			componentRestrictions: { country: 'gb' },
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
				geocoder.geocode({ address: place.name.trim() }, geocoderResponse);
				return;
			}
			showNearestClubs(place.geometry.location);
		});
		document.getElementById('search-reset').addEventListener('click', function () {
			searchBox.value = '';
			reorderClubsAlpha();
		});
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

	// pan to new position, or setcenter if zooming
	function zoomPan(position) {
		if (map.getZoom() < closeZoom) {
			map.setZoom(closeZoom);
			map.setCenter(position);
		} else {
			map.panTo(position);
		}
	}

	// centre the map so all clubs are displayed, and zoom in as much as possible
	// while making sure that all clubs are displayed
	function centreMap() {
		const mapType = map.mapTypes.get(map.getMapTypeId());
		const MAX_ZOOM = mapType.maxZoom || 21;
		const MIN_ZOOM = mapType.minZoom || 0;

		const projection = map.getProjection();
		const ne = projection.fromLatLngToPoint(mapBounds.getNorthEast());
		const sw = projection.fromLatLngToPoint(mapBounds.getSouthWest());

		const worldCoordWidth = Math.abs(ne.x - sw.x);
		const worldCoordHeight = Math.abs(ne.y - sw.y);

		// Fit padding in pixels
		const FIT_PAD = 40;
		let zoom;
		for (zoom = MAX_ZOOM; zoom >= MIN_ZOOM; --zoom) {
			// use mapDiv.firstChild.offsetWidth etc as when the map is maximized the
			// mapDiv stays the same size, but it's first child will be 100%x100%, so
			// we need to use that div's height and width
			if (
				worldCoordWidth * (1 << zoom) + 2 * FIT_PAD < mapDiv.firstChild.offsetWidth &&
				worldCoordHeight * (1 << zoom) + 2 * FIT_PAD < mapDiv.firstChild.offsetHeight
			) {
				break;
			}
		}
		map.setCenter(mapBounds.getCenter());
		map.setZoom(zoom);
	}

	function showClubInfoWindow(club) {
		// Reuse the one infoWindow, just change the text and marker.
		if (!infoWindow) {
			infoWindow = new google.maps.InfoWindow();
		}
		let html = '<h2 style="margin:0;border:0;font-size:1rem">' + club.name + '</h2>';
		if (!clubsInAlphaOrder) {
			html +=
				'<p style="margin:0;font-size:075.rem">(' + club.dist + ' miles from search)</p>';
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
		let i;
		const clubsLength = SemlaClubs.length;
		const positionLatRad = deg2rad(position.lat());
		const positionLatRadSin = Math.sin(positionLatRad);
		const positionLatRadCos = Math.cos(positionLatRad);
		for (i = clubsLength; i--; ) {
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
		const keyLiNodes = mapKeyDiv.getElementsByTagName('li');
		for (i = clubsLength; i--; ) {
			const club = SemlaClubs[i];
			club.order = i;
			club.marker.setLabel('' + (i + 1));
			club.dist = club.dist.toFixed(2);
			keyLiNodes[i].firstChild.nodeValue = i + 1 + '. ' + club.name + ' ' + club.dist + ' mi';
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
		const keyLiNodes = mapKeyDiv.getElementsByTagName('li');
		for (let i = SemlaClubs.length; i--; ) {
			const club = SemlaClubs[i];
			club.order = i;
			club.marker.setLabel('');
			keyLiNodes[i].firstChild.nodeValue = club.name;
		}
	}

	// convert degrees to radians, which are needed for distance calculations
	function deg2rad(angle) {
		return (angle * Math.PI) / 180;
	}

	initMap();
})();
