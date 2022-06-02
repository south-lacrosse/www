/**
 * Script to embed tables/fixtures into another site.
 * The script should be included <script data-target="div-to-populate" src="..." async></script>
 * anywhere in the page, but after a <div id="div-to-populate"></div>
 */
(function () {
  var c = document.currentScript || function () {
    var s = document.getElementsByTagName('script');
    for (var i = 0; i < s.length; i++) {
      if (s[i].src === '<?= $url ?>') {
        return s[i];
      }
    }
    return null;
  };
  if (c) {
    document.getElementById(c.getAttribute('data-target')).innerHTML = "<?= $data ?>";
  }
})();
