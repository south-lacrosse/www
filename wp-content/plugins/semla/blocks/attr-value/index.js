!function(){"use strict";var e=window.wp.element,t=window.wp.blockEditor,a=window.wp.blocks,l=window.wp.components,n=window.wp.primitives,r=(0,e.createElement)(n.SVG,{xmlns:"http://www.w3.org/2000/svg",viewBox:"0 0 24 24"},(0,e.createElement)(n.Path,{d:"M4 7.2v1.5h16V7.2H4zm8 8.6h8v-1.5h-8v1.5zm-8-3.5l3 3-3 3 1 1 4-4-4-4-1 1z"})),o=JSON.parse('{"u2":"semla/attr-value"}'),s={from:[{type:"block",isMultiBlock:!0,blocks:["core/paragraph"],transform:e=>e.map((({content:e})=>{const t=e.indexOf(":");let l="",n="";return-1===t?l=e.trim():(l=e.substring(0,t).trim(),n=e.substring(t+1).trim()),(0,a.createBlock)(o.u2,{attr:l,value:n})}))}],to:[{type:"block",isMultiBlock:!0,blocks:["core/paragraph"],transform:e=>e.map((({attr:e,value:t})=>(0,a.createBlock)("core/paragraph",{content:`${e}: ${t}`})))}]};(0,a.registerBlockType)(o.u2,{edit:function({attributes:a,setAttributes:n}){const o=(0,t.useBlockProps)({className:a.sameLine?"avf-same-line":""});return(0,e.createElement)("div",{...o},(0,e.createElement)(t.BlockControls,null,(0,e.createElement)(l.ToolbarGroup,null,(0,e.createElement)(l.ToolbarButton,{icon:r,label:"Put value on line below attribute",isActive:!a.sameLine,onClick:()=>{n({sameLine:!a.sameLine})}}))),(0,e.createElement)("div",{className:"avf-name"},(0,e.createElement)(t.PlainText,{value:a.attr,placeholder:"Attribute",onChange:e=>n({attr:e})})),a.sameLine&&(0,e.createElement)("div",{style:{display:"table-cell",width:"1em"}},":"),(0,e.createElement)(t.RichText,{tagName:"div",className:"avf-value",value:a.value,placeholder:"Value",onChange:e=>n({value:e})}))},save:function({attributes:a}){const l=t.useBlockProps.save({className:a.sameLine?"avf-same-line":""});return(0,e.createElement)("div",{...l},(0,e.createElement)("div",{className:"avf-name"},a.attr),(0,e.createElement)(t.RichText.Content,{tagName:"div",className:"avf-value",value:a.value}))},transforms:s})}();