!function(){"use strict";if(window.addEventListener){const e=document,t=e.getElementById("semla_toc-list");if(t){const n=e.createElement("a");n.href="#",n.innerHTML="hide",n.setAttribute("aria-expanded","true"),n.setAttribute("role","button"),n.addEventListener("click",(function(e){e.preventDefault();const d=this.firstChild;"hide"===d.nodeValue?(t.style.display="none",d.nodeValue="show",n.setAttribute("aria-expanded","false")):(t.style.display="",d.nodeValue="hide",n.setAttribute("aria-expanded","true"))}),!1);const d=e.createDocumentFragment();d.appendChild(e.createTextNode(" [")),d.appendChild(n),d.appendChild(e.createTextNode("] ")),t.parentNode.insertBefore(d,t)}}}();