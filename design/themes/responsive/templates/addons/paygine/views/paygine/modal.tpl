<html>
<head>
	<title></title>
</head>
{literal}
	<style>section#payform-modal{backdrop-filter: blur(5px);z-index: 9999;}button#payform-close-button {border: 4px solid #fff;background-color: #888;box-shadow: 0px 5px 10px 0px rgba(0, 0, 0, 0.5);}</style>
{/literal}
<body class="clear-body">
<script type="text/javascript" charset="utf-8" data-no-defer>
	{literal}
	!function(){"use strict";function t(t,o){return function(t){if(Array.isArray(t))return t}(t)||function(t,r){var o=null==t?null:"undefined"!=typeof Symbol&&t[Symbol.iterator]||t["@@iterator"];if(null==o)return;var e,a,n=[],i=!0,s=!1;try{for(o=o.call(t);!(i=(e=o.next()).done)&&(n.push(e.value),!r||n.length!==r);i=!0);}catch(t){s=!0,a=t}finally{try{i||null==o.return||o.return()}finally{if(s)throw a}}return n}(t,o)||function(t,o){if(!t)return;if("string"==typeof t)return r(t,o);var e=Object.prototype.toString.call(t).slice(8,-1);"Object"===e&&t.constructor&&(e=t.constructor.name);if("Map"===e||"Set"===e)return Array.from(t);if("Arguments"===e||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(e))return r(t,o)}(t,o)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}function r(t,r){(null==r||r>t.length)&&(r=t.length);for(var o=0,e=new Array(r);o<r;o++)e[o]=t[o];return e}window.modalPayform=function(r){if(!r)throw new Error("Payment src is not defined");var o=n("section",{id:"payform-modal",class:"payform-modal "}),e=n("iframe",{src:r,id:"payform-frame",class:"payform-frame",frameborder:"0",allow:"payment"}),a=n("button",{id:"payform-close-button",class:"payform-close-button",type:"button"});function n(r,o){var e=document.createElement(r);if(o)for(var a=0,n=Object.entries(o);a<n.length;a++){var i=t(n[a],2),s=i[0],l=i[1];e.setAttribute(s,l)}return e}function i(){o.classList.remove("payform-modal--open","payform-modal--loaded"),e.classList.remove("payform-frame--loaded"),setTimeout((function(){a.removeEventListener("click",i),o.remove()}))}return{openModal:function(){var t;document.querySelector("#payform-modal")?e.classList.add("payform-frame--loaded"):((t=document.createElement("style")).append(".payform-frame,.payform-modal{top:0;left:0;right:0;bottom:0;width:100%;height:100%}.payform-modal > *{box-sizing:border-box}.payform-modal{display:none;position:fixed;overflow:hidden;background-color:rgba(0,0,0,.7);backdrop-filter:blur(5px);-webkit-backdrop-filter:blur(5px);z-index:10}.payform-modal::after{content:'';position:absolute;top:50%;left:50%;margin-top:-30px;margin-left:-30px;width:50px;height:50px;border-radius:50px;border:5px solid grey;border-top-color:#000;-webkit-animation:1s linear infinite loading;animation:1s linear infinite loading}@-webkit-keyframes loading{0%{-webkit-transform:rotate(0);transform:rotate(0)}100%{-webkit-transform:rotate(360deg);transform:rotate(360deg)}}@keyframes loading{0%{-webkit-transform:rotate(0);transform:rotate(0)}100%{-webkit-transform:rotate(360deg);transform:rotate(360deg)}}.payform-modal--open{display:block}.payform-modal--loaded::after{content:none}.payform-frame{position:absolute;opacity:0;-webkit-transition:opacity .2s ease-in;-o-transition:opacity .2s ease-in;transition:opacity .2s ease-in;z-index:2}.payform-frame--loaded{opacity:1}.payform-close-button{position:absolute;right:2%;top:2%;width:40px;height:40px;border-radius:50%;border:none;background:rgba(255,255,255,0.32);z-index:3;cursor:pointer;transition:all .2s ease}.payform-close-button:hover{background:rgba(255,255,255,0.2)}.payform-close-button::after,.payform-close-button::before{content:'';position:absolute;top:48%;left:50%;-webkit-transform:translateX(-50%);-ms-transform:translateX(-50%);transform:translateX(-50%);width:28px;height:4px;border-radius:4px;background:#fff;transition:all .2s ease}.payform-close-button::after{-webkit-transform:translateX(-50%) rotate(45deg);-ms-transform:translateX(-50%) rotate(45deg);transform:translateX(-50%) rotate(45deg)}.payform-close-button::before{-webkit-transform:translateX(-50%) rotate(-45deg);-ms-transform:translateX(-50%) rotate(-45deg);transform:translateX(-50%) rotate(-45deg)}@media (min-width: 1024px){.payform-close-button{width:56px;height:56px;top:16px;right:16px}.payform-close-button::after,.payform-close-button::before{top:50%}}"),e.onload=function(){e.classList.add("payform-frame--loaded"),o.classList.add("payform-modal--loaded")},o.append(e),o.append(a),a.addEventListener("click",i),document.querySelector("head").append(t),document.querySelector("body").append(o)),o.classList.add("payform-modal--open")},closeModal:i}}}();
	{/literal}
	window.onload = function(){
		let modal_url = '{$modal_url|escape:"javascript"}'.replace(/\&amp;/g, '&');
		modalPayform(modal_url).openModal();
		let close_button = document.querySelector("#payform-close-button");
		close_button.onclick = function() {
			close_modal();
		};
		setTimeout(() => {
			close_modal();
		}, 5 * 60 * 1000);
	}
	function close_modal() {
		window.top.location.href = '{$cancel_url}';
	}
</script>
</body>
</html>