<noscript>
<p><b>JavaScript must be enabled in order for you to use these maps.</b></p>
<p>However, it seems JavaScript is either disabled or not supported by your browser.
To view the maps, enable JavaScript in your browser options, and then try again.</p>
</noscript>
<div style="display: none">
<div id="search-wrapper">
<input id="search-box" type="text" placeholder="Search for clubs near..." size="30">
<span id="search-reset" title="Reset search for nearest clubs">&times;</span>
</div>
</div>
<a id="toggle-key" href="#">Hide Key</a>
<div id="map-container">
<div class="map" id="map"></div>
<div id="map-key">
<ul id="clubs-list"></ul>
</div>
</div>
<script>
SemlaClubs=[<?php
while ($query->have_posts()) {
    $query->the_post();
    if (preg_match('/"latLong":"([^"]*)"/', get_the_content(), $matches)) {
        $lat_lng = explode('%2C' , esc_attr($matches[1]));
        echo "\n{name:'";
        the_title();
        echo "',lat:$lat_lng[0],lng:$lat_lng[1],html:'<a href=\"";
        the_permalink();
        echo '">Club page</a>\'},';
    }
}
?>
];
</script>
