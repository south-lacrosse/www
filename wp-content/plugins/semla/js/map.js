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
		map,
		mapContainerDiv = null,
		clubsBounds,
		infoWindow,
		clubsInAlphaOrder = true,
		searchNearMarker = null;

	// Larger zoom values correspond to a higher resolution.
	const minZoomForClub = 9; // min zoom when we select a club from the key
	const maxZoomForSearch = 8; // max zoom for search
	const includedRegionCodes = ['gb'];

	async function initMap() {
		// Request needed libraries.
		const [
			{ Map, InfoWindow },
			{ ControlPosition, LatLng, LatLngBounds },
			{ PlaceAutocompleteElement },
			{ AdvancedMarkerElement },
		] = await Promise.all([
			google.maps.importLibrary('maps'),
			google.maps.importLibrary('core'),
			google.maps.importLibrary('places'),
			google.maps.importLibrary('marker'),
		]);

		mapDiv = document.getElementById('map');
		mapKeyDiv = document.getElementById('map-key');
		clubsList = document.getElementById('clubs-list');
		footer = document.getElementById('page-footer');
		// now resize the map to fill the available size
		resizeMapDiv();
		// make sure when the browser window is resized that the map resizes too
		window.addEventListener('resize', resizeMapDiv, false);

		const clubsLength = SemlaClubs.length;
		// get the bounding area of all the clubs
		clubsBounds = new LatLngBounds();
		for (let i = clubsLength; i--; ) {
			const club = SemlaClubs[i];
			club.latLng = new LatLng(club.lat, club.lng);
			clubsBounds.extend(club.latLng);
		}

		// restrict the map to only display up to a reasonable padding around the SEMLA area
		const mapPaddingDegrees = 6;
		const clubsNE = clubsBounds.getNorthEast();
		const clubsSW = clubsBounds.getSouthWest();
		const mapBounds = new LatLngBounds(
			new LatLng(clubsSW.lat() - mapPaddingDegrees, clubsSW.lng() - mapPaddingDegrees),
			new LatLng(clubsNE.lat() + mapPaddingDegrees, clubsNE.lng() + mapPaddingDegrees)
		);

		map = new Map(mapDiv, {
			center: clubsBounds.getCenter(),
			fullscreenControl: true,
			scaleControl: true,
			mapId: window.location.host.startsWith('dev') ? 'DEMO_MAP_ID' : '96908cc41d8bdc4f',
			restriction: {
				latLngBounds: mapBounds,
				// latLngBounds: new LatLngBounds(new LatLng(49, -6), new LatLng(55.5, 2.5)),
				strictBounds: false,
			},
		});
		map.fitBounds(clubsBounds);
		infoWindow = new InfoWindow();

		let html = '';
		// create a marker for each club, and the html for the key
		for (let i = 0; i < clubsLength; i++) {
			const club = SemlaClubs[i];
			const marker = new AdvancedMarkerElement({
				map: map,
				title: club.name,
				position: club.latLng,
			});
			club.marker = marker;
			club.order = i;
			marker.semlaClub = club; // add custom property for club data
			marker.addListener('click', markerClick);

			html += '<li title="' + club.name + '" data-index="' + i + '">' + club.name + '</li>';
		}

		// add list of clubs to the key
		clubsList.innerHTML = html;
		// whenever a club name is clicked then zoom and pan to that club's location,
		// and open an info window
		clubsList.addEventListener(
			'click',
			function (event) {
				const i = event.target.getAttribute('data-index');
				if (i === null) return;
				const club = SemlaClubs[i];
				// pan to new position, or setCenter if zooming
				if (map.getZoom() < minZoomForClub) {
					map.setZoom(minZoomForClub);
					map.setCenter(club.latLng);
				} else {
					map.panTo(club.latLng);
				}
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
			'<p style="margin-bottom:0.5em">Search for clubs near:</p><button class="search-reset" title="Reset search for nearest clubs">Reset</button>';
		const placeAutocomplete = new PlaceAutocompleteElement({
			includedRegionCodes,
		});
		placeAutocomplete.id = 'search-box';
		searchControl.firstElementChild.after(placeAutocomplete);

		placeAutocomplete.addEventListener('gmp-select', async ({ placePrediction }) => {
			const place = placePrediction.toPlace();
			await place.fetchFields({ fields: ['formattedAddress', 'location'] });
			showNearestClubs(place);
		});
		// reset search button
		searchControl.lastElementChild.addEventListener('click', function () {
			reorderClubsAlpha();
		});
		map.controls[ControlPosition.TOP_RIGHT].push(searchControl);
	}

	// event listener for markers
	function markerClick() {
		showClubInfoWindow(this.semlaClub);
	}

	function showClubInfoWindow(club) {
		infoWindow.close();
		let html = '<h2 style="margin:0;border:0;font-size:1rem">' + club.name + '</h2>';
		if (!clubsInAlphaOrder) {
			html +=
				'<p style="margin:0;font-size:0.75rem">(' + club.dist + ' miles from search)</p>';
		}
		html +=
			'<p style="margin:1em 0 0;font-size:1rem">' +
			club.html +
			' <a style="margin-left:1.5em" href="https://www.google.co.uk/maps/dir//' +
			club.latLng.toUrlValue() +
			'/@' +
			club.latLng.toUrlValue() +
			',16z">Directions</a></p>';
		infoWindow.setContent(html);
		infoWindow.open({ anchor: club.marker, map });
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

	function showNearestClubs(place) {
		const position = place.location;
		// center on search, and zoom out so user can see clubs
		if (map.getZoom() > maxZoomForSearch) {
			map.setZoom(maxZoomForSearch);
			map.setCenter(position);
		} else {
			map.panTo(position);
		}

		if (!searchNearMarker) {
			const pin = new google.maps.marker.PinElement({
				background: '#5383EC',
				glyphColor: '#324F8E',
				borderColor: '#324F8E',
			});
			searchNearMarker = new google.maps.marker.AdvancedMarkerElement({
				map,
				content: pin.element,
			});
			searchNearMarker.addListener('click', function () {
				infoWindow.setContent(
					'<p style="margin:0;font-size:1rem;font-weight:400">Search near: ' +
						this.title +
						'</p>'
				);
				infoWindow.open({ anchor: this, map });
			});
		}
		searchNearMarker.position = position;
		searchNearMarker.title = place.formattedAddress;
		searchNearMarker.map = map;

		// compute distance to all clubs from position
		const positionLatRad = deg2rad(position.lat());
		const positionLatRadSin = Math.sin(positionLatRad);
		const positionLatRadCos = Math.cos(positionLatRad);
		const clubsLength = SemlaClubs.length;
		for (let i = clubsLength; i--; ) {
			const club = SemlaClubs[i];
			const clubLatRad = deg2rad(club.lat);
			const dist =
				3959.0 *
				Math.acos(
					positionLatRadSin * Math.sin(clubLatRad) +
						positionLatRadCos *
							Math.cos(clubLatRad) *
							Math.cos(deg2rad(position.lng() - club.lng))
				);
			club.dist = dist.toFixed(2);
		}
		// and now sort into distance order
		SemlaClubs.sort(sortByDistance);
		clubsInAlphaOrder = false;
		infoWindow.close();
		const keyLiNodes = clubsList.getElementsByTagName('li');
		for (let i = clubsLength; i--; ) {
			const club = SemlaClubs[i];
			club.order = i;
			const clubTitle = i + 1 + '. ' + club.name + ' ' + club.dist + ' mi';
			const pin = new google.maps.marker.PinElement({
				glyph: `${i + 1}`,
			});
			club.marker.content = pin.element;
			club.marker.title = clubTitle;
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
		infoWindow.close();
		searchNearMarker.map = null;
		SemlaClubs.sort(sortByName);
		clubsInAlphaOrder = true;
		const keyLiNodes = clubsList.getElementsByTagName('li');
		for (let i = SemlaClubs.length; i--; ) {
			const club = SemlaClubs[i];
			club.order = i;
			club.marker.content = null;
			club.marker.title = club.name;
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
