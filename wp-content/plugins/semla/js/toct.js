/**
 * IMPORTANT: Bump the version number in the plugin (search for "js/toc")
 * if you make anything other than cosmetic changes to this file,
 * 
 * Add toggle link for table of contents. It is placed just before the ul
 */
(function () {
  'use strict';
  if (window.addEventListener) {
    var doc = document;
    var tocUL = doc.getElementById('semla_toc-list');

    if (tocUL) {
      var toggletoc = doc.createElement('a');
      toggletoc.href = '#';
      toggletoc.innerHTML = 'hide';
      toggletoc.setAttribute('aria-expanded', 'true');
      toggletoc.setAttribute('role', 'button');
      toggletoc.addEventListener('click', function (event) {
        event.preventDefault(); // don't link to #
        var textNode = this.firstChild;
        if (textNode.nodeValue === 'hide') {
          tocUL.style.display = 'none';
          textNode.nodeValue = 'show';
          toggletoc.setAttribute('aria-expanded', 'false');
        } else {
          tocUL.style.display = '';
          textNode.nodeValue = 'hide';
          toggletoc.setAttribute('aria-expanded', 'true');
        }
      }, false);

      var fragment = doc.createDocumentFragment();
      fragment.appendChild(doc.createTextNode('\u00A0['));
      fragment.appendChild(toggletoc);
      fragment.appendChild(doc.createTextNode(']\u00A0'));

      tocUL.parentNode.insertBefore(fragment, tocUL);
    }
  }
})();
