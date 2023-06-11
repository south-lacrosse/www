/**
 * Configure GLightbox for galleries with the lightbox style
 */
/* global GLightbox */
(function () {
	'use strict';
	var galleries = document.querySelectorAll('.wp-block-gallery.is-style-lightbox');
	var lightboxes = [];

	/**
	 * Create separate Glightbox for each gallery, otherwise you can navigate
	 * from an image in one gallery to another
	 */
	for (var i = galleries.length; i--; ) {
		var gallery = galleries[i];

		var galleryId = gallery.className.match(/wp-block-gallery-\d+/);
		if (galleryId) {
			var lightbox = GLightbox({
				selector: '.' + galleryId + ' a',
			});
			lightbox.on('slide_before_load', onSlideBeforeLoad);
			lightboxes.push(lightbox);
		} else {
			console.error('Cannot extract gallery id from ' + gallery.className);
		}
	}

	/**
	 *  Srcset and title are stored on the img, and not the a tag as GLightbox
	 *  requires, so add in the slide_before_load event
	 */
	function onSlideBeforeLoad(data) {
		var slideConfig = data.slideConfig;
		var img = document.querySelector(
			'.wp-block-gallery.is-style-lightbox a[href="' + slideConfig.href + '"] > img'
		);
		if (!img) return;
		if (!slideConfig.srcset) {
			slideConfig.srcset = img.getAttribute('srcset') || '';
		}
		if (!slideConfig.title) {
			slideConfig.title = img.getAttribute('title') || '';
		}
	}
})();
