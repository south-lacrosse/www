(()=>{"use strict";var e={n:t=>{var l=t&&t.__esModule?()=>t.default:()=>t;return e.d(l,{a:l}),l},d:(t,l)=>{for(var r in l)e.o(l,r)&&!e.o(t,r)&&Object.defineProperty(t,r,{enumerable:!0,get:l[r]})},o:(e,t)=>Object.prototype.hasOwnProperty.call(e,t)};const t=window.wp.element,l=window.wp.apiFetch;var r=e.n(l);const a=window.wp.blockEditor,n=window.wp.blocks,o=window.wp.components,c=window.wp.primitives,s=(0,t.createElement)(c.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"},(0,t.createElement)(c.Path,{d:"m19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z"})),u=window.wp.serverSideRender;var i=e.n(u);(0,n.registerBlockType)("semla/data",{edit:function(e){let{attributes:l,setAttributes:n}=e;const[c,u]=(0,t.useState)(null);(0,t.useEffect)((()=>{r()({path:"/semla-admin/v1/leagues-cups"}).then((e=>{const l=[];for(let r=0,a=e.length;r<a;r++){const{id:a,type:n,name:o}=e[r];"league"===n?(l.push((0,t.createElement)("option",{key:`curr_tables,${a}`,value:`curr_tables,${a}`},o," League Tables")),l.push((0,t.createElement)("option",{key:`curr_grid,${a}`,value:`curr_grid,${a}`},o," Fixtures Grid"))):(l.push((0,t.createElement)("option",{key:`curr_flags,${a}`,value:`curr_flags,${a}`},o)),l.push((0,t.createElement)("option",{key:`curr_flags_rounds,${a}`,value:`curr_flags_rounds,${a}`},o," Table")))}u(l)})).catch((e=>console.log(e)))}),[]);const{src:m}=l,p=(0,a.useBlockProps)();return"none"===m?(0,t.createElement)("div",p,(0,t.createElement)(o.Placeholder,{icon:(0,t.createElement)(o.Icon,{icon:"editor-table"}),label:"SEMLA Data",instructions:"Select the data source"},(0,t.createElement)("select",{className:"semla__select",onChange:e=>{n({src:e.target.value}),e.preventDefault()}},(0,t.createElement)("option",{value:"none"},"Select..."),(0,t.createElement)("option",{value:"clubs_list"},"Clubs List"),(0,t.createElement)("option",{value:"clubs_grid"},"Clubs Grid"),(0,t.createElement)("option",{value:"clubs_map"},"Clubs Map"),(0,t.createElement)("option",{value:"curr_fixtures"},"Fixtures"),c,(0,t.createElement)("option",{value:"curr_results"},"Recent Results/Upcoming Fixtures")))):(0,t.createElement)("div",p,(0,t.createElement)(a.BlockControls,null,(0,t.createElement)(o.ToolbarGroup,null,(0,t.createElement)(o.ToolbarButton,{label:"Change data source",icon:s,onClick:()=>{n({src:"none"})}}))),"clubs_map"===m?(0,t.createElement)("p",{className:"semla__border semla__border_dashed"},"Clubs map will be inserted here - check the preview to see actual rendering."):(0,t.createElement)(o.Disabled,null,(0,t.createElement)(i(),{block:"semla/data",attributes:l})))}})})();