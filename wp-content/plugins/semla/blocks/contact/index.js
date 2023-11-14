!function(){"use strict";var e=window.wp.element,t=window.wp.blockEditor,l=window.wp.blocks,a=window.wp.components,n=window.wp.primitives,r=(0,e.createElement)(n.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"},(0,e.createElement)(n.Path,{d:"m19 7-3-3-8.5 8.5-1 4 4-1L19 7Zm-7 11.5H5V20h7v-1.5Z"})),o=(0,e.createElement)(n.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"},(0,e.createElement)(n.Path,{d:"M4 7.2v1.5h16V7.2H4zm8 8.6h8v-1.5h-8v1.5zm-8-3.5l3 3-3 3 1 1 4-4-4-4-1 1z"})),c=JSON.parse('{"u2":"semla/contact"}');function i(e){return(e=e.trim()).includes(" ")?e.replace(/  +/g," "):e.replace(/^\+44(\d{4})(\d{6})$/,"+44 $1 $2").replace(/^07(\d{3})(\d{6})$/,"07$1 $2")}var m={from:[{type:"raw",isMatch:e=>{var t,l;if("P"!==e.nodeName)return!1;const a=e.innerHTML.replaceAll("<br>"," ").replace(/<a[^>]*href="(mailto|tel)[^>]*>/g,"").replaceAll("</a>","");if(a.includes("<"))return!1;const n=a.split(":");if(2!==n.length)return!1;const r=n[1],o=null!==(t=r.match(/\b[A-Za-z0-9._%+-]+\@[A-Za-z0-9._%+-]+\.[A-Za-z]+\b/g)?.length)&&void 0!==t?t:0;if(o>1)return!1;const c=null!==(l=r.match(/\b\+?\d(?: ?\d){9,12}\b/g)?.length)&&void 0!==l?l:0;return c<2&&(o>0||c>0)},transform:e=>u(e.innerHTML)},{type:"block",isMultiBlock:!0,blocks:["core/paragraph"],transform:e=>e.map((({content:e})=>u(e)))},{type:"block",isMultiBlock:!0,blocks:["semla/attr-value"],transform:e=>e.map((({attr:e,value:t,sameLine:a})=>{const{text:n,email:r,tel:o}=s(t);return(0,l.createBlock)("semla/contact",{role:e,name:n,email:r,tel:o,sameLine:a})}))}],to:[{type:"block",isMultiBlock:!0,blocks:["core/paragraph"],transform:e=>e.map((e=>(0,l.createBlock)("core/paragraph",{content:`${e.role}: `+p(e)})))},{type:"block",isMultiBlock:!0,blocks:["semla/attr-value"],transform:e=>e.map((e=>(0,l.createBlock)("semla/attr-value",{attr:e.role,value:p(e),sameLine:e.sameLine})))}]};function s(e){let t="",l="",a=(e=e.replace(/<br[^>]*>/g," ").replace(/<(?!a |\/a>)[^>]*>/g,"").replaceAll("&nbsp;"," ")).match(/<a href="mailto:([^"]+)"/);return a?t=a[1].trim():(a=e.match(/\b[A-Za-z0-9._%+-]+\@[A-Za-z0-9._%+-]+\.[A-Za-z]+\b/),a&&(t=a[0],e=e.substring(0,a.index)+e.substring(a.index+a[0].length))),a=e.match(/<a href="tel:([^"]+)"/),a?l=i(a[1]):(a=e.match(/(?:^| )\+?\d(?: ?\d){9,12}\b/),a&&(l=i(a[0]),e=e.substring(0,a.index)+e.substring(a.index+a[0].length))),{text:e.replace(/<a[^>]*>[^>]*<\/a>/g,"").replace(/  +/g," ").trim(),email:t,tel:l}}function u(e){const{text:t,email:a,tel:n}=s(e);let r=null,o=null;const c=t.indexOf(":");return-1===c?r=t:(r=t.substring(0,c).trim(),o=t.substring(c+1).trim()),(0,l.createBlock)("semla/contact",{role:r,name:o,email:a,tel:n})}function p({name:e,email:t,tel:l}){let a=e?.trim()||"";return t&&(a&&(a+="<br>"),a+=`<a href="mailto:${t=t.trim()}">${t}</a>`),l&&(a&&(a+="<br>"),a+=`<a href="tel:${l.replaceAll(" ","")}">${l}</a>`),a}function b({attributes:t}){const{role:l,name:a,email:n,tel:r}=t,o={"pointer-events":"none"};return(0,e.createElement)(e.Fragment,null,(0,e.createElement)("div",{className:"avf-name"},l),(0,e.createElement)("div",{className:"avf-value"},a,n&&a&&(0,e.createElement)("br",null),n&&(0,e.createElement)("a",{style:o,href:`mailto:${n}`},n),r&&(a||n)&&(0,e.createElement)("br",null),r&&(0,e.createElement)("a",{style:o,href:`tel:${r.replaceAll(" ","")}`},r)))}function g({attributes:t,onChange:l}){const{role:n,name:r,email:o,tel:c}=t;return(0,e.createElement)(e.Fragment,null,(0,e.createElement)(a.TextControl,{label:"Role",value:n,onChange:e=>l({role:e})}),(0,e.createElement)(a.TextControl,{label:"Name",value:r,onChange:e=>l({name:e})}),(0,e.createElement)(a.TextControl,{label:"Email",type:"email",value:o,onChange:e=>l({email:e})}),(0,e.createElement)(a.TextControl,{label:"Telephone",type:"tel",value:c,onChange:e=>l({tel:e})}))}(0,l.registerBlockType)(c.u2,{edit:function({attributes:l,setAttributes:n,isSelected:c}){const{sameLine:m}=l,s=(0,t.useBlockProps)({className:m?"avf-same-line":""}),u=!l.role||!l.email&&!l.tel,[p,h]=(0,e.useState)(u);u&&!p&&h(!0);const[d,v]=(0,e.useState)({...l}),f=function(e){v({...d,...e});const t={};for(const l in e){const a=""+e[l];t[l]="tel"===l?i(a):"email"===l?a.replaceAll(" ",""):a.trim().replace(/  +/g," ")}n(t)};return(0,e.createElement)(e.Fragment,null,(0,e.createElement)(t.BlockControls,null,(0,e.createElement)(a.ToolbarGroup,null,c&&!u&&(0,e.createElement)(a.ToolbarButton,{label:"Edit mode",icon:r,isActive:p,onClick:()=>h((e=>!e))}),(0,e.createElement)(a.ToolbarButton,{icon:o,label:"Put contact details on line below role",isActive:!m,onClick:()=>n({sameLine:!m})}))),c&&(0,e.createElement)(t.InspectorControls,null,(0,e.createElement)(a.PanelBody,{title:"Settings",initialOpen:!0},(0,e.createElement)(g,{attributes:d,onChange:f}))),(0,e.createElement)("div",{...s},u||p&&c?(0,e.createElement)(a.Placeholder,{icon:"admin-users",label:"Contact",isColumnLayout:!0},(0,e.createElement)(g,{attributes:d,onChange:f})):(0,e.createElement)(b,{attributes:l})))},transforms:m})}();