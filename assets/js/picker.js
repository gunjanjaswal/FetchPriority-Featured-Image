/**
 * FetchPriority Featured Image — visual LCP picker.
 * Lets an admin click the hero element and save it as the manual priority
 * target for the current template.
 */
(function () {
	'use strict';

	var cfg = window.FPFI_PICKER || {};
	if (!cfg.ajax) {
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

	var bar = document.createElement('div');
	bar.style.cssText =
		'position:fixed;z-index:2147483647;top:0;left:0;right:0;background:#1d2327;color:#fff;' +
		'padding:10px 14px;font:14px/1.4 -apple-system,Segoe UI,Roboto,sans-serif;' +
		'box-shadow:0 2px 10px rgba(0,0,0,.35);display:flex;align-items:center;gap:12px;';
	bar.innerHTML =
		'<strong>FetchPriority LCP Picker</strong>' +
		'<span style="opacity:.85;">' +
		(cfg.label ? 'Template: ' + cfg.label + ' — ' : '') +
		'click the hero/LCP element.</span>' +
		'<button type="button" id="fpfi-pk-cancel" style="margin-left:auto;cursor:pointer;' +
		'background:#3582c4;border:0;color:#fff;padding:6px 12px;border-radius:4px;">Cancel</button>';
	document.body.appendChild(bar);

	var hi = document.createElement('div');
	hi.style.cssText =
		'position:fixed;z-index:2147483646;border:2px solid #2271b1;' +
		'background:rgba(34,113,177,.15);pointer-events:none;display:none;transition:all .03s;';
	document.body.appendChild(hi);

	function onMove(e) {
		var el = e.target;
		if (!el || el === bar || bar.contains(el) || el === hi) {
			hi.style.display = 'none';
			return;
		}
		var r = el.getBoundingClientRect();
		hi.style.display = 'block';
		hi.style.top = r.top + 'px';
		hi.style.left = r.left + 'px';
		hi.style.width = r.width + 'px';
		hi.style.height = r.height + 'px';
	}

	function cleanup() {
		document.removeEventListener('mousemove', onMove, true);
		document.removeEventListener('click', onClick, true);
		if (bar.parentNode) {
			bar.parentNode.removeChild(bar);
		}
		if (hi.parentNode) {
			hi.parentNode.removeChild(hi);
		}
		try {
			var u = new URL(window.location.href);
			u.searchParams.delete('fpfi_picker');
			window.history.replaceState({}, '', u.toString());
		} catch (e) {}
	}

	function onClick(e) {
		var el = e.target;
		if (el && el.id === 'fpfi-pk-cancel') {
			e.preventDefault();
			cleanup();
			return;
		}
		if (!el || el === bar || bar.contains(el)) {
			return;
		}
		e.preventDefault();
		e.stopPropagation();

		var url = '';
		var isBg = false;

		if (el.tagName === 'IMG') {
			url = el.currentSrc || el.src;
		} else {
			var bg = backgroundUrl(el);
			if (bg) {
				url = bg;
				isBg = true;
			}
		}
		if (!url && el.querySelector) {
			var img = el.querySelector('img');
			if (img) {
				url = img.currentSrc || img.src;
			}
		}
		if (!url) {
			window.alert('No image found on that element. Try clicking the image itself.');
			return;
		}

		var data = new FormData();
		data.append('action', 'fpfi_picker_save');
		data.append('nonce', cfg.nonce);
		data.append('template', cfg.template);
		data.append('url', url);
		data.append('is_bg', isBg ? '1' : '0');
		data.append('selector', selectorOf(el));

		fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: data })
			.then(function (r) {
				return r.json();
			})
			.then(function (j) {
				window.alert(
					j && j.success
						? 'Saved LCP target for this template:\n' + url
						: 'Save failed: ' + (j && j.data ? j.data.message : 'unknown error')
				);
				cleanup();
			})
			.catch(function () {
				window.alert('Save failed (network error).');
				cleanup();
			});
	}

	document.addEventListener('mousemove', onMove, true);
	document.addEventListener('click', onClick, true);
})();
