!function(){"use strict";var e={n:function(t){var n=t&&t.__esModule?function(){return t.default}:function(){return t};return e.d(n,{a:n}),n},d:function(t,n){for(var o in n)e.o(n,o)&&!e.o(t,o)&&Object.defineProperty(t,o,{enumerable:!0,get:n[o]})},o:function(e,t){return Object.prototype.hasOwnProperty.call(e,t)}},t=window.wp.element,n=window.wp.blockEditor,o=window.wp.blocks,r=window.wp.components,l=window.wp.compose,a=window.wp.domReady,c=e.n(a),s=window.wp.hooks,i=window.wp.primitives,u=(0,t.createElement)(i.SVG,{width:"20",height:"20",xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"},(0,t.createElement)(i.Path,{d:"M9.984 12.984v-1.969h12v1.969h-12zM9.984 18.984v-1.969h12v1.969h-12zM9.984 5.016h12v1.969h-12v-1.969zM6 6.984v10.031h2.484l-3.469 3.469-3.516-3.469h2.484v-10.031h-2.484l3.516-3.469 3.469 3.469h-2.484z"}));function m(e,t){return!!e&&new RegExp("(\\s|^)"+t+"(\\s|$)").test(e)}function p(e,t){if(e=e?.trim(),!e)return t;const n=new RegExp("(^|\\s+)"+t+"(\\s+|$)");return""===(e=n.test(e)?e.replace(n,""):e+" "+t)?void 0:e}function d(e,t,n){if(e=e?.trim(),e){for(let n=t.length-1;n>=0;n--)e=e.replace(new RegExp("(^|\\s+)"+t[n]+"(\\s+|$)"),"");n&&!m(e,n)&&(e&&(e+=" "),e+=n)}else e=n;return""===e?void 0:e}c()((function(){(0,o.unregisterBlockType)("core/audio"),(0,o.unregisterBlockType)("core/video");const e=/^core\/.*comment/;(0,o.getBlockTypes)().forEach((t=>{t.name.match(e)&&(0,o.unregisterBlockType)(t.name)}))}));const h=(0,l.createHigherOrderComponent)((e=>n=>{let o;switch(n.name){case"core/paragraph":o=w;break;case"core/list":o=b;break;case"core/table":o=E;break;default:return(0,t.createElement)(e,n)}return(0,t.createElement)(t.Fragment,null,(0,t.createElement)(e,n),o(n))}),"coreBlocksControls");function w(e){const o=e.attributes.className,l=m(o,"no-print");return(0,t.createElement)(n.InspectorControls,null,(0,t.createElement)(r.PanelBody,{title:"Print Options"},(0,t.createElement)(r.ToggleControl,{label:"Don't print",checked:l,onChange:()=>{e.setAttributes({className:p(o,"no-print")})},help:l?"Remove from printed page.":"Show on printed page."})))}(0,s.addFilter)("editor.BlockEdit","semla/custom-core-controls",h);const f=[{label:"Regular spaced",value:""},{label:"Medium spaced",value:"medium-spaced"},{label:"Large Spaced",value:"spaced"}],g=[{label:"Default",value:""},{label:"Alphabetic",value:"is-style-alpha"},{label:"Roman numerals",value:"is-style-roman"}],v="is-style-unstyled";function b(e){let{attributes:o,setAttributes:l}=e;const{className:a,ordered:c}=o,s=(0,t.useRef)(!0);(0,t.useEffect)((()=>{if(s.current)return void(s.current=!1);const e=c?d(a,[v],""):y(a,"",g);e!==a&&l({className:e})}),[c]);const i=k(a,f),h=f.map((e=>{const{label:t,value:n}=e;return{role:"menuitemradio",title:t,icon:u,isActive:n===i,onClick:()=>{l({className:y(a,n,f)})}}}));return(0,t.createElement)(t.Fragment,null,(0,t.createElement)(n.InspectorControls,null,(0,t.createElement)(r.PanelBody,{title:(c?"Ordered":"Unordered")+" list style"},c?function(e,n){const o=k(e,g);return(0,t.createElement)(r.SelectControl,{value:o,options:g,onChange:t=>n({className:y(e,t,g)})})}(a,l):function(e,n){const o=m(e,v);return(0,t.createElement)(r.ToggleControl,{label:"Unstyled",checked:o,onChange:()=>{n({className:p(e,v)})}})}(a,l))),(0,t.createElement)(n.BlockControls,null,(0,t.createElement)(r.ToolbarGroup,null,(0,t.createElement)(r.ToolbarDropdownMenu,{isCollapsed:!0,icon:u,label:"Set spacing",controls:h}))))}function E(e){const o=e.attributes.className,l=m(o,"compact");return(0,t.createElement)(n.InspectorControls,null,(0,t.createElement)(r.PanelBody,{title:"Formatting"},(0,t.createElement)(r.ToggleControl,{label:"Compact",checked:l,onChange:()=>{e.setAttributes({className:p(o,"compact")})},help:l?"Don't use full width of page.":"Use full page width."})))}function k(e,t){for(let n=1;n<t.length;n++)if(m(e,t[n].value))return t[n].value;return""}function y(e,t,n){const o=[];for(let e=0;e<n.length;e++){const r=n[e].value;r&&r!==t&&o.push(r)}return d(e,o,t)}}();