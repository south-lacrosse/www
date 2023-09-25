<noscript>
<p><b>JavaScript must be enabled in order for you to use these maps.</b></p>
<p>However, it seems JavaScript is either disabled or not supported by your browser.
To view the maps, enable JavaScript in your browser options, and then try again.</p>
</noscript>
<a id="toggle-key" href="#">Hide Key</a>
<div id="map-container">
<div id="map"></div>
<div id="map-key">
<ul id="clubs-list"></ul>
</div>
</div>
<script>
(g=>{var h,a,k,p="The Google Maps JavaScript API",c="google",l="importLibrary",q="__ib__",m=document,b=window;b=b[c]||(b[c]={});var d=b.maps||(b.maps={}),r=new Set,e=new URLSearchParams,u=()=>h||(h=new Promise(async(f,n)=>{await (a=m.createElement("script"));e.set("libraries",[...r]+"");for(k in g)e.set(k.replace(/[A-Z]/g,t=>"_"+t[0].toLowerCase()),g[k]);e.set("callback",c+".maps."+q);a.src=`https://maps.${c}apis.com/maps/api/js?`+e;d[q]=f;a.onerror=()=>h=n(Error(p+" could not load."));a.nonce=m.querySelector("script[nonce]")?.nonce||"";m.head.append(a)}));d[l]?console.warn(p+" only loads once. Ignoring:",g):d[l]=(f,...n)=>r.add(f)&&u().then(()=>d[l](f,...n))})({
key:"<?= get_option('semla_gapi_key')  ?>",v:"weekly",region:"GB"});
SemlaClubs=[<?php
foreach($clubs as $club) {
	if (preg_match('/"latLong":"([^"]*)"/', $club->post_content, $matches)) {
		$lat_lng = explode('%2C' , esc_attr($matches[1]));
		echo "\n{name:'";
		echo str_replace("'", "\\'", $club->post_title);
		echo "',lat:$lat_lng[0],lng:$lat_lng[1],html:'<a href=\"";
		echo get_permalink($club);
		echo '">Club page</a>\'},';
	}
}
?>
];
</script>
