(()=>{"use strict";const e=window.wp.element,t=window.wp.blocks,a=window.wp.blockEditor,l=window.wp.components,n=window.wp.data,o="semla/map",r=window.wp.primitives,c=(0,e.createElement)(r.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 92.3 132.3"},(0,e.createElement)(r.Path,{fill:"#1a73e8",d:"M60.2 2.2C55.8.8 51 0 46.1 0 32 0 19.3 6.4 10.8 16.5l21.8 18.3L60.2 2.2z"}),(0,e.createElement)(r.Path,{fill:"#ea4335",d:"M10.8 16.5C4.1 24.5 0 34.9 0 46.1c0 8.7 1.7 15.7 4.6 22l28-33.3-21.8-18.3z"}),(0,e.createElement)(r.Path,{fill:"#4285f4",d:"M46.2 28.5c9.8 0 17.7 7.9 17.7 17.7 0 4.3-1.6 8.3-4.2 11.4 0 0 13.9-16.6 27.5-32.7-5.6-10.8-15.3-19-27-22.7L32.6 34.8c3.3-3.8 8.1-6.3 13.6-6.3"}),(0,e.createElement)(r.Path,{fill:"#fbbc04",d:"M46.2 63.8c-9.8 0-17.7-7.9-17.7-17.7 0-4.3 1.5-8.3 4.1-11.3l-28 33.3c4.8 10.6 12.8 19.2 21 29.9l34.1-40.5c-3.3 3.9-8.1 6.3-13.5 6.3"}),(0,e.createElement)(r.Path,{fill:"#34a853",d:"M59.1 109.2c15.4-24.1 33.3-35 33.3-63 0-7.7-1.9-14.9-5.2-21.3L25.6 98c2.6 3.4 5.3 7.3 7.9 11.3 9.4 14.5 6.8 23.1 12.8 23.1s3.4-8.7 12.8-23.2"})),s={to:[{type:"block",blocks:["semla/location"],transform:(e,a)=>(0,t.createBlock)("semla/location",{},[(0,t.createBlock)(o,e,a)])}]},i=["core/image","core/paragraph"];(0,t.registerBlockType)(o,{icon:c,edit:function(t){let{clientId:o,attributes:r,setAttributes:c,isSelected:s}=t;const{address:m,lat:u,long:h,latLong:w}=r,[{lat:g,long:E},b]=(0,e.useState)({lat:u,long:h}),[f,k]=(0,e.useState)(!1),v=(0,n.useSelect)((e=>0!==e(a.store).getBlockParentsByBlockName(o,"semla/location").length),[]);return(0,e.createElement)("div",(0,a.useBlockProps)(),(0,e.createElement)(a.BlockControls,null,(0,e.createElement)(l.ToolbarGroup,null,(0,e.createElement)(l.ToolbarButton,{icon:(0,e.createElement)(l.Icon,{icon:"location-alt"}),label:"Set coordinates on map",onClick:()=>{window.semla=window.semla||{},window.semla.loc={lat:u,long:h,address:m},k(!0)}}))),(0,e.createElement)(a.InspectorControls,null,(0,e.createElement)(l.PanelBody,{title:"Help",initialOpen:!1},(0,e.createElement)("p",null,"To display the map either enter the coordinates below, or find the exact location on a Google map using the button in the toolbar (the map will start at the current location, or if none is set then it will start at the address if there is one)."),(0,e.createElement)("p",null,"Enter directions below the map so the are hidden when the page initially loads. Since 99.9% of people have have a SatNav on their phones only add anything if the route is complicated, or things like the postcode taking people to the wrong place. And if possible add information about public transport.")),(0,e.createElement)(l.PanelBody,{title:"Map"},(0,e.createElement)("p",null,'If you know the exact coordinates enter them below, and click the "Update Map" button to update the map.'),(0,e.createElement)(l.TextControl,{label:"Latitude",type:"number",value:g,min:50,max:54,onChange:e=>b({lat:parseFloat(e),long:E})}),(0,e.createElement)(l.TextControl,{label:"Longitude",type:"number",value:E,min:-6,max:2,onChange:e=>b({lat:g,long:parseFloat(e)})}),(0,e.createElement)(l.Button,{variant:"secondary",onClick:()=>{c({lat:g,long:E,latLong:p(g,E)})}},"Update Map"))),f&&(0,e.createElement)(l.Modal,{onRequestClose:()=>k(!1),title:"Set Location on Map",className:"semla-map-modal",shouldCloseOnClickOutside:!1},(0,e.createElement)("p",null,"Drag the marker or double-click to set the exact position. You can also use the search box to search for a location."),(0,e.createElement)("iframe",{title:"Map",id:"semla-map-iframe",src:d()+"../../modal/map-dialog.html"}),(0,e.createElement)(l.ButtonGroup,{className:"semla-map-buttons"},(0,e.createElement)(l.Button,{variant:"primary",onClick:()=>{const{lat:e,long:t}=window.semla.loc;c({lat:e,long:t,latLong:p(e,t)}),b({lat:e,long:t}),k(!1)}},"OK"),(0,e.createElement)(l.Button,{variant:"secondary",onClick:()=>k(!1)},"Cancel"))),(0,e.createElement)("div",{className:"semla__border semla__border_dashed"},!v&&s&&(0,e.createElement)("p",{className:"no-top-margin"},(0,e.createElement)("strong",null,"WARNING:")," This is a standalone map. Transform it to a Location block for a club or venue address with a map."),(0,e.createElement)("button",{className:"acrd-btn"},"Map and Directions (will start hidden on live page)"),w&&(0,e.createElement)("iframe",{className:"gmap",src:"https://www.google.com/maps/embed/v1/place?q="+w+"&zoom=15&key="+window.semla.gapi,title:"Google Map",allowFullScreen:!0}),(0,e.createElement)(a.InnerBlocks,{allowedBlocks:i})))},save:function(t){let{attributes:{latLong:l}}=t;return(0,e.createElement)("div",a.useBlockProps.save(),(0,e.createElement)("button",{className:"acrd-btn","data-toggle":"collapse","aria-expanded":"false"},"Map and Directions"),(0,e.createElement)("div",{className:"acrd-content"},l&&(0,e.createElement)(e.RawHTML,null,"!MAP!"),!l&&(0,e.createElement)("p",null,"Map coordinates not set!"),(0,e.createElement)(a.InnerBlocks.Content,null)))},transforms:s});let m=null;function d(){if(null===m){const e=document.currentScript||document.querySelector('script[src*="semla/blocks/"]');if(e){const t=e.src;m=t.substring(0,t.lastIndexOf("/")+1)}}return m}function p(e,t){return isNaN(e)||isNaN(t)||e<50||e>54||t<-6||t>2?null:e.toFixed(6)+"%2C"+t.toFixed(6)}})();