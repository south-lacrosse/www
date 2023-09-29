!function(){"use strict";var e={n:function(t){var r=t&&t.__esModule?function(){return t.default}:function(){return t};return e.d(r,{a:r}),r},d:function(t,r){for(var n in r)e.o(r,n)&&!e.o(t,n)&&Object.defineProperty(t,n,{enumerable:!0,get:r[n]})},o:function(e,t){return Object.prototype.hasOwnProperty.call(e,t)}},t=window.wp.element,r=window.wp.apiFetch,n=e.n(r),l=window.wp.blockEditor,a=window.wp.blocks,o=window.wp.components,c=window.wp.primitives,s=(0,t.createElement)(c.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"},(0,t.createElement)(c.Path,{d:"m19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z"})),u=window.wp.serverSideRender,i=e.n(u);(0,a.registerBlockType)("semla/data",{edit:function({attributes:e,setAttributes:r}){const[a,c]=(0,t.useState)(null);(0,t.useEffect)((()=>{n()({path:"/semla-admin/v1/leagues-cups"}).then((e=>{const r=[];for(let n=0,l=e.length;n<l;n++){const{id:l,type:a,name:o}=e[n];"league"===a?(r.push((0,t.createElement)("option",{key:`curr_tables,${l}`,value:`curr_tables,${l}`},o," League Tables")),r.push((0,t.createElement)("option",{key:`curr_grid,${l}`,value:`curr_grid,${l}`},o," Fixtures Grid"))):(r.push((0,t.createElement)("option",{key:`curr_flags,${l}`,value:`curr_flags,${l}`},o)),r.push((0,t.createElement)("option",{key:`curr_flags_rounds,${l}`,value:`curr_flags_rounds,${l}`},o," Table")))}c(r)})).catch((e=>console.log(e)))}),[]);const{src:u}=e,m=(0,l.useBlockProps)();return"none"===u?(0,t.createElement)("div",{...m},(0,t.createElement)(o.Placeholder,{icon:(0,t.createElement)(o.Icon,{icon:"editor-table"}),label:"SEMLA Data",instructions:"Select the data source"},(0,t.createElement)("select",{className:"semla-select",onChange:e=>{r({src:e.target.value}),e.preventDefault()}},(0,t.createElement)("option",{value:"none"},"Select..."),(0,t.createElement)("option",{value:"clubs_list"},"Clubs List"),(0,t.createElement)("option",{value:"clubs_grid"},"Clubs Grid"),(0,t.createElement)("option",{value:"clubs_map"},"Clubs Map"),(0,t.createElement)("option",{value:"curr_fixtures"},"Fixtures"),a,(0,t.createElement)("option",{value:"curr_results"},"Recent Results/Upcoming Fixtures")))):(0,t.createElement)("div",{...m},(0,t.createElement)(l.BlockControls,null,(0,t.createElement)(o.ToolbarGroup,null,(0,t.createElement)(o.ToolbarButton,{label:"Change data source",icon:s,onClick:()=>{r({src:"none"})}}))),"clubs_map"===u?(0,t.createElement)("p",{className:"semla-border semla-border-dashed"},"Clubs map will be inserted here - check the preview to see actual rendering."):(0,t.createElement)(o.Disabled,null,(0,t.createElement)(i(),{block:"semla/data",attributes:e})))}})}();