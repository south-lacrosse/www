/**
 * IMPORTANT: If you change this remember to bump the version number in
 * the plugin. Just search for "js/map" to find the reference.
 */
/* eslint no-alert: "off", no-mixed-operators: "off" */
(function () {
  'use strict';
  var mapDiv, mapKeyDiv, footer, searchBox, mapPaddingAndBorders, map, mapContainerDiv = null,
    gmapBounds, infoWindow = null, currentMapCenter = null, geocoder = null, selectedClub = -1,
    closeZoom = 9, clubsInAlphaOrder = true;
  var doc = document;
  var win = window;

  var GEOCODER_STATUS_DESCRIPTION = {
    UNKNOWN_ERROR: 'The request could not be successfully processed, yet the ' +
            'exact reason for the failure is not known',
    OVER_QUERY_LIMIT: 'The webpage has gone over the requests limit in too ' +
              'short a time',
    REQUEST_DENIED: 'The webpage is not allowed to use the geocoder for some ' +
            'reason',
    INVALID_REQUEST: 'This request was invalid',
    ZERO_RESULTS: 'The address is unknown, please try another',
    ERROR: 'There was a problem contacting the Google servers'
  };

  /**
   * initialize the Google map
   */
  function initMap() {
    mapDiv = doc.getElementById('map');
    mapKeyDiv = doc.getElementById('map-key');
    footer = doc.getElementById('page-footer');

    // evaluate the height needed by other elements on the page
    mapPaddingAndBorders = getPosY(footer) - getPosY(mapDiv) - mapDiv.clientHeight;
    // now resize the map to fill the available size
    resizeMapDiv();
    // make sure when the browser window is resized that the map resizes too
    win.addEventListener('resize', resizeMapDiv, false);

    doc.getElementById('toggle-key').addEventListener('click', function (event) {
      event.preventDefault();
      if (!mapContainerDiv) {
        mapContainerDiv = doc.getElementById('map-container');
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
    }, false);

    var i, len = SemlaClubs.length, club;
    // see what the maximum bounds the map needs to display all the clubs
    gmapBounds = new google.maps.LatLngBounds();
    for (i = len; i--;) {
      club = SemlaClubs[i];
      club.latLng = new google.maps.LatLng(club.lat, club.lng);
      gmapBounds.extend(club.latLng);
    }

    map = new google.maps.Map(mapDiv, {
      center: gmapBounds.getCenter(),
      fullscreenControl: true,
      scaleControl: true,
      mapTypeControlOptions: { style: google.maps.MapTypeControlStyle.DROPDOWN_MENU },
      mapTypeId: google.maps.MapTypeId.ROADMAP,
      zoom: 6
    });
    map.fitBounds(gmapBounds);

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

    var html = '';
    // create markers for each club, and the html for the key
    for (i = 0; i < len; i++) {
      club = SemlaClubs[i];
      var marker = new google.maps.Marker({
        map: map,
        title: club.name,
        optimized: false, // need this otherwise markers are optimized to single element, and title won't show
        position: club.latLng,
        semlaClub: club // add custom property for club data
      });
      club.marker = marker;
      club.order = i;
      marker.addListener('click', markerClick);
      marker.addListener('mouseover', markerMouseover);
      marker.addListener('mouseout', markerMouseout);

      html += '<li>' + club.name + '</li>';
    }

    var clubsList = doc.getElementById('clubs-list');
    // add list of clubs to the key
    clubsList.innerHTML = html;
    // whenever a club name is clicked the zoom and pan to that club's location,
    // and open an info window
    clubsList.addEventListener('click', function (event) {
      var i = positionWithinParent(event.target);
      var club = SemlaClubs[i];
      zoomPan(club.latLng);
      showClubInfoWindow(club);
    }, false);

    // create and add a control to centre the map. It will be added to the
    // top centre position within the google map
    var centreControlDiv = doc.createElement('div');
    centreControlDiv.className = 'gmnoprint';

    var centreControlUI = doc.createElement('div');
    centreControlUI.className = 'map-ctl';
    centreControlUI.title = 'Click to recentre the map';
    centreControlUI.innerHTML = '<div class="map-ctl-text">Centre Map</div>';
    centreControlUI.addEventListener('click', centreMap);
    centreControlDiv.appendChild(centreControlUI);

    map.controls[google.maps.ControlPosition.LEFT_TOP].push(centreControlDiv);

    // the search box on our page will be added to the map
    map.controls[google.maps.ControlPosition.TOP_RIGHT].push(doc.getElementById('search-wrapper'));

    searchBox = doc.getElementById('search-box');
    var autocomplete = new google.maps.places.Autocomplete(searchBox, {
      bounds: new google.maps.LatLngBounds(
        new google.maps.LatLng(50, -6), //sw
        new google.maps.LatLng(54, 2) //ne
      ),
      componentRestrictions: { country: 'gb' }
    });
    autocomplete.addListener('place_changed', function () {
      var place = this.getPlace();
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
    doc.getElementById('search-reset').addEventListener('click', function () {
      searchBox.value = '';
      reorderClubsAlpha();
    });
  }

  // event listerners for markers
  function markerClick() {
    showClubInfoWindow(this.semlaClub);
  }
  function markerMouseover() {
    mapKeyDiv.getElementsByTagName('li')[this.semlaClub.order].className += ' hover';
  }
  function markerMouseout() {
    var elem = mapKeyDiv.getElementsByTagName('li')[this.semlaClub.order];
    elem.className = elem.className.replace(' hover', '');
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
    var MAX_ZOOM = map.mapTypes.get(map.getMapTypeId()).maxZoom || 21;
    var MIN_ZOOM = map.mapTypes.get(map.getMapTypeId()).minZoom || 0;

    var ne = map.getProjection().fromLatLngToPoint(gmapBounds.getNorthEast());
    var sw = map.getProjection().fromLatLngToPoint(gmapBounds.getSouthWest());

    var worldCoordWidth = Math.abs(ne.x - sw.x);
    var worldCoordHeight = Math.abs(ne.y - sw.y);

    // Fit padding in pixels
    var FIT_PAD = 40;
    var zoom;
    for (zoom = MAX_ZOOM; zoom >= MIN_ZOOM; --zoom) {
      // use mapDiv.firstChild.offsetWidth etc as when the map is maximized the
      // mapDiv stays the same size, but it's first child will be 100%x100%, so
      // we need to use that div's height and width
      if (worldCoordWidth * (1 << zoom) + 2 * FIT_PAD < mapDiv.firstChild.offsetWidth &&
          worldCoordHeight * (1 << zoom) + 2 * FIT_PAD < mapDiv.firstChild.offsetHeight) {
        break;
      }
    }
    map.setCenter(gmapBounds.getCenter());
    map.setZoom(zoom);
  }

  // show an info windows about the marker with information about the club,
  // and highlight the club within the key
  function showClubInfoWindow(club) {
    // Reuse the one infoWindow, just change the text and marker.
    if (!infoWindow) {
      infoWindow = new google.maps.InfoWindow();
      infoWindow.addListener('closeclick', unselectClub);
    }
    var html = '<div style="margin: 0.1em"><b>' + club.name + '</b>';
    if (!clubsInAlphaOrder) {
      html += '<br>(' + club.dist + ' miles from search)';
    }
    html += '<br><br>' + club.html + ' <a style="padding-left:20px" href="https://www.google.co.uk/maps/dir//' +
			club.latLng.toUrlValue() + '/@' + club.latLng.toUrlValue() +
			',16z">directions</a></div>';
    infoWindow.setContent(html);
    if (selectedClub !== -1) {
      mapKeyDiv.getElementsByTagName('li')[selectedClub].className = '';
    }
    selectedClub = club.order;
    mapKeyDiv.getElementsByTagName('li')[selectedClub].className = 'selected';
    infoWindow.open(map, club.marker);
  }

  // close the info window, and remove styling from the club in th key
  function closeInfoWindow() {
    if (infoWindow) {
      infoWindow.close();
      unselectClub();
    }
  }

  function unselectClub() {
    if (selectedClub !== -1) {
      mapKeyDiv.getElementsByTagName('li')[selectedClub].className = '';
      selectedClub = -1;
    }
  }

  // Returns the relative position of a node within its parent
  function positionWithinParent(node) {
    var children = node.parentNode.childNodes;
    var num = 0;
    for (var i = 0, len = children.length; i < len; i++) {
      if (children[i] === node) {
        return num;
      }
      if (children[i].nodeType === 1) {
        num++;
      }
    }
    return -1;
  }

  // resize the map div container to fill avaiable screen area, or 300px minimum
  function resizeMapDiv() {
    var wh = win.innerHeight || doc.documentElement.clientHeight ||
        doc.body.clientHeight;
    var newHeight = wh - getPosY(mapDiv) - footer.offsetHeight - mapPaddingAndBorders;
    newHeight = Math.max(300, newHeight);
    var ht = Math.round(newHeight) + 'px';
    mapDiv.style.height = ht;
    mapKeyDiv.style.height = ht;
    if (map) {
      google.maps.event.trigger(map, 'resize');
    }
  }
  function getPosY(node) {
    var y = 0;
    while (node) {
      y += node.offsetTop;
      node = node.offsetParent;
    }
    return y;
  }

  // show clubs nearest to a position
  function showNearestClubs(position) {
    zoomPan(position);

    // compute distance to all clubs from position
    var i, len = SemlaClubs.length, club;
    var positionLatRad = deg2rad(position.lat());
    var positionLatRadSin = Math.sin(positionLatRad);
    var positionLatRadCos = Math.cos(positionLatRad);
    for (i = len; i--;) {
      club = SemlaClubs[i];
      var clubLatRad = deg2rad(club.lat);
      club.dist = 3959.0 * Math.acos(
        positionLatRadSin * Math.sin(clubLatRad) +
        positionLatRadCos * Math.cos(clubLatRad) *
        Math.cos(deg2rad(position.lng() - club.lng))
      );
    }
    // and now sort into distance order
    SemlaClubs.sort(sortByDistance);
    clubsInAlphaOrder = false;
    closeInfoWindow();
    var keyLiNodes = mapKeyDiv.getElementsByTagName('li');
    for (i = len; i--;) {
      club = SemlaClubs[i];
      club.order = i;
      club.marker.setLabel('' + (i + 1));
      club.dist = club.dist.toFixed(2);
      keyLiNodes[i].firstChild.nodeValue = (i + 1) + '. ' + club.name + ' ' + club.dist + ' mi';
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
    var keyLiNodes = mapKeyDiv.getElementsByTagName('li');
    for (var i = SemlaClubs.length; i--;) {
      var club = SemlaClubs[i];
      club.order = i;
      club.marker.setLabel('');
      keyLiNodes[i].firstChild.nodeValue = club.name;
    }
  }

  // convert degrees to radians, which are needed for distance calculations
  function deg2rad(angle) {
    return (angle * Math.PI) / 180;
  }

  window.addEventListener('load', initMap);
})();
