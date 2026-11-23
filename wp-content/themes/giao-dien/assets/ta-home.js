(function () {
	'use strict';
	if (!('IntersectionObserver' in window) || window.matchMedia('(prefers-reduced-motion: reduce)').matches) { return; }
	var els = document.querySelectorAll('.ta-home .ta-line, .ta-home .ta-app, .ta-home .ta-proj, .ta-home .ta-doc, .ta-home .ta-q, .ta-home .ta-fact');
	document.documentElement.classList.add('js');
	var io = new IntersectionObserver(function (es) {
		es.forEach(function (e) { if (e.isIntersecting) { e.target.classList.add('is-in'); io.unobserve(e.target); } });
	}, { rootMargin: '0px 0px -8% 0px' });
	els.forEach(function (el) { el.classList.add('ta-reveal'); io.observe(el); });
})();
