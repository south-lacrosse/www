/**
 * Configure GLightbox for galleries with the lightbox style
 */
/* global GLightbox */
(function () {
	'use strict';
	var galleries = document.querySelectorAll('.wp-block-gallery.is-style-lightbox');
	var lightboxes = [];

	for (var i = galleries.length; i--; ) {
		var gallery = galleries[i];

		// copy all img.title attributes to a.data-title so GLightbox can set
		// the title
		var aTags = gallery.querySelectorAll('a[href]');
		for (var j = 0; j < aTags.length; j++) {
			var aTag = aTags[j];
			var img = aTag.querySelector('img[title]');
			if (img) {
				aTag.setAttribute('data-title', img.getAttribute('title'));
			}
		}

		var galleryId = gallery.className.match(/wp-block-gallery-\d+/);
		if (galleryId) {
			lightboxes.push(
				GLightbox({
					selector: '.' + galleryId + ' a',
				})
			);
		} else {
			console.error('Cannot extract gallery id from ' + gallery.className);
		}
	}
})();
