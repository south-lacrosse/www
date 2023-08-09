(()=>{"use strict";const e=window.wp.element,t=window.wp.blockEditor,a=window.wp.blocks,l=window.wp.components,n=JSON.parse('{"u2":"semla/calendar"}');(0,a.registerBlockType)(n.u2,{edit:function(a){let{attributes:n,setAttributes:o}=a;const{enhanced:r,tagsList:c}=n,s=(e,t)=>{const a=c.map((e=>({...e})));Object.assign(a[e],t),o({tagsList:a})};return(0,e.createElement)("div",(0,t.useBlockProps)(),(0,e.createElement)(t.InspectorControls,null,(0,e.createElement)(l.PanelBody,{title:"Calendar Type"},(0,e.createElement)(l.ToggleControl,{checked:r,label:"Enhanced",onChange:()=>{o(r?{enhanced:!1,tagsList:[]}:{enhanced:!r})}}),(0,e.createElement)("p",null,(0,e.createElement)("b",null,"Default")," simply lists the events, split by month, with day name, day, start time, end date (if different) and time, and summary."),(0,e.createElement)("p",null,(0,e.createElement)("b",null,"Enhanced")," does the same, but additionally adds location, and extracts a URL from the description to use as a link (anything like \"http://x.com\"). It also adds adds coloured tags if you have ' : ' in the summary, so 'SBL Session 5 : Box' will have Box as the tag, and you can specify colours for the tags (default blue) under Tags below.")),r&&(0,e.createElement)(e.Fragment,null,(0,e.createElement)(l.PanelBody,{title:"Tags"},(0,e.createElement)("p",null,"You can specify the colour of the tags (after the ':' in the summary). Note that if no colour is specified then blue (#0277bd) is used."),(0,e.createElement)(l.RangeControl,{label:"Number of Tags",value:c.length,onChange:e=>{let t;if(e<c.length)t=c.slice(0,e).map((e=>({...e})));else for(t=c.map((e=>({...e})));t.length<e;)t.push({tag:"",color:"#000000"});o({tagsList:t})},min:0,max:10})),c.map(((t,a)=>{const{tag:n,color:o}=t;let r="Tag "+(a+1);return n&&(r+=" "+n),(0,e.createElement)(l.PanelBody,{key:a,title:r,initialOpen:!1},(0,e.createElement)(l.TextControl,{label:"Tag",placeholder:"Tag",onChange:e=>s(a,{tag:e}),value:n}),(0,e.createElement)(l.ColorPicker,{color:o,onChangeComplete:e=>s(a,{color:e.hex}),disableAlpha:!0}))})))),(0,e.createElement)(l.Placeholder,{icon:(0,e.createElement)(l.Icon,{icon:"calendar-alt"}),label:"Embed a Google Calendar",instructions:"Enter the Google Calendar Id"},(0,e.createElement)("div",{className:"components-placeholder__fieldset"},(0,e.createElement)("input",{className:"semla-cal-placeholder__text-input-field",type:"text",value:n.cid,"aria-label":"Google Calendar id",placeholder:"calendar@group.calendar.google.com",onChange:e=>o({cid:e.target.value})})),(0,e.createElement)("p",{className:"components-placeholder__learn-more"},"Open the Settings to set calendar options. Open the page or preview to see the actual calendar.")))}})})();