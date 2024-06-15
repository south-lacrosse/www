(()=>{"use strict";const e=window.React,t=window.wp.blocks,n=window.wp.blockEditor,a=window.wp.components,l=window.wp.data,s=JSON.parse('{"u2":"semla/location"}'),r=[["core/paragraph",{content:"Notes to always display e.g. non-grass pitch"}],["semla/map",{}]],o=["core/image","core/paragraph"],i=["semla/map",...o];(0,t.registerBlockType)(s.u2,{edit:function({clientId:t,attributes:{address:s,mapperLinks:d},setAttributes:c}){const p=(0,l.useSelect)((e=>!!e(n.store).getBlock(t).innerBlocks.find((e=>"semla/map"===e.name))),[t]);return(0,e.createElement)("div",{...(0,n.useBlockProps)()},(0,e.createElement)(n.InspectorControls,null,(0,e.createElement)(a.PanelBody,{title:"Help",initialOpen:!0},(0,e.createElement)("p",null,"Enter address, any notes, and add a Map block."),(0,e.createElement)("p",null,"Don't put important instructions that should always be displayed directly below the map (e.g. non-grass types and required footwear, non-standard start times), as that will be hidden when the page is initially displayed.")),(0,e.createElement)(a.PanelBody,{title:"Settings"},(0,e.createElement)(a.ToggleControl,{label:"Mapper Links",help:"Add mapper links to address on front end (currently CityMapper)",checked:d,onChange:e=>{c({mapperLinks:e})}}))),(0,e.createElement)(n.PlainText,{value:s,placeholder:"Address",onChange:e=>{c({address:e})}}),(0,e.createElement)(n.InnerBlocks,{allowedBlocks:p?o:i,template:r}))},save:function({attributes:{address:t}}){return(0,e.createElement)("div",{...n.useBlockProps.save()},(0,e.createElement)("p",null,t),(0,e.createElement)(n.InnerBlocks.Content,null))}})})();