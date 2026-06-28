/**
 * FetchPriority Featured Image — real-user LCP beacon.
 * Observes the Largest Contentful Paint element and reports its resource URL
 * back to the plugin so future renders prioritise the real LCP.
 */
(function () {
	'use strict';

	var cfg = window.FPFI_BEACON || {};
	if (!cfg.endpoint || typeof PerformanceObserver === 'undefined') {
		return;
	}

	var last = null;
	var reported = false;

	try {
		var po = new PerformanceObserver(function (list) {
			var entries = list.getEntries();
			if (entries.length) {
				last = entries[entries.length - 1];
			}
		});
		po.observe({ type: 'largest-contentful-paint', buffered: true });
	} catch (e) {
		return;
	}

	function selectorOf(el) {
		if (!el || el.nodeType !== 1) {
			return '';
		}
		if (el.id) {
			return '#' + el.id;
		}
		var parts = [];
		var node = el;
		var depth = 0;
		while (node && node.nodeType === 1 && depth < 4) {
			var part = node.nodeName.toLowerCase();
			if (node.className && typeof node.className === 'string') {
				var cls = node.className.trim().split(/\s+/).slice(0, 2).join('.');
				if (cls) {
					part += '.' + cls;
				}
			}
			parts.unshift(part);
			node = node.parentElement;
			depth++;
		}
		return parts.join('>');
	}

	function backgroundUrl(el) {
		try {
			var bg = window.getComputedStyle(el).backgroundImage;
			var m = bg && bg.match(/url\((['"]?)(.*?)\1\)/);
			return m ? m[2] : '';
		} catch (e) {
			return '';
		}
	}

	function send() {
		if (reported || !last) {
			return;
		}
		reported = true;

		var el = last.element || null;
		var url = last.url || '';
		var isBg = false;

		if (!url && el) {
			var bg = backgroundUrl(el);
			if (bg) {
				url = bg;
				isBg = true;
			}
		}
		if (!url && el && el.currentSrc) {
			url = el.currentSrc;
		}
		if (!url) {
			return;
		}

		var lcpMs = Math.round(last.renderTime || last.loadTime || last.startTime || 0);

		var dispW = 0, dispH = 0, natW = 0, natH = 0;
		if (el) {
			try {
				var r = el.getBoundingClientRect();
				dispW = Math.round(r.width);
				dispH = Math.round(r.height);
			} catch (e) {}
			if (el.tagName === 'IMG') {
				natW = el.naturalWidth || 0;
				natH = el.naturalHeight || 0;
			} else if (el.tagName === 'VIDEO') {
				natW = el.videoWidth || 0;
				natH = el.videoHeight || 0;
			}
		}

		var payload = JSON.stringify({
			template: cfg.template,
			url: url,
			is_bg: isBg,
			selector: selectorOf(el),
			tag: el ? el.nodeName.toLowerCase() : '',
			lcp_ms: lcpMs,
			disp_w: dispW,
			disp_h: dispH,
			nat_w: natW,
			nat_h: natH,
			dpr: window.devicePixelRatio || 1
		});

		try {
			if (navigator.sendBeacon) {
				navigator.sendBeacon(
					cfg.endpoint,
					new Blob([payload], { type: 'application/json' })
				);
			} else {
				fetch(cfg.endpoint, {
					method: 'POST',
					keepalive: true,
					headers: { 'Content-Type': 'application/json' },
					body: payload
				});
			}
		} catch (e) {}
	}

	document.addEventListener('visibilitychange', function () {
		if (document.visibilityState === 'hidden') {
			send();
		}
	});
	window.addEventListener('pagehide', send);
})();
