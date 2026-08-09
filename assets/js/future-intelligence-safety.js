(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	if (!root) return;
	const form = root.querySelector('[data-supc-form]');
	const source = root.querySelector('[data-supc-rte-source]');
	const editor = root.querySelector('[data-supc-rte]');
	if (!form || !source || !editor) return;

	const safeUrl = (value, image) => {
		try {
			const url = new URL(String(value || ''), window.location.origin);
			if (image) {
				if (url.protocol === 'blob:' && url.origin === window.location.origin) return url.href;
				if (['http:', 'https:'].includes(url.protocol) && url.origin === window.location.origin) return url.href;
				return '';
			}
			return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
		} catch (error) {
			return '';
		}
	};

	const sanitize = (html) => {
		const template = document.createElement('template');
		template.innerHTML = String(html || '');
		const allowed = new Set(['P', 'BR', 'H2', 'H3', 'H4', 'STRONG', 'B', 'EM', 'I', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'A', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'HR', 'SUP', 'SUB', 'FIGURE', 'FIGCAPTION', 'IMG']);
		const walk = (parent) => {
			Array.from(parent.children || []).forEach((child) => {
				walk(child);
				if (!allowed.has(child.tagName)) {
					child.replaceWith(...Array.from(child.childNodes));
					return;
				}
				Array.from(child.attributes).forEach((attribute) => {
					const name = attribute.name.toLowerCase();
					if (child.tagName === 'A' && name === 'href') {
						const href = safeUrl(attribute.value, false);
						if (href) {
							child.setAttribute('href', href);
							child.setAttribute('rel', 'noopener noreferrer');
						} else child.removeAttribute('href');
						return;
					}
					if (child.tagName === 'IMG' && ['src', 'alt', 'title'].includes(name)) {
						if (name === 'src') {
							const src = safeUrl(attribute.value, true);
							if (src) {
								child.setAttribute('src', src);
								child.setAttribute('referrerpolicy', 'no-referrer');
							} else child.removeAttribute('src');
						}
						return;
					}
					if (!(child.tagName === 'A' && name === 'rel') && !(child.tagName === 'IMG' && name === 'referrerpolicy')) child.removeAttribute(attribute.name);
				});
			});
		};
		walk(template.content);
		return template.innerHTML;
	};

	editor.addEventListener('paste', (event) => {
		const clipboard = event.clipboardData;
		if (!clipboard) return;
		event.preventDefault();
		event.stopImmediatePropagation();
		const html = clipboard.getData('text/html');
		const plain = clipboard.getData('text/plain');
		const raw = html || (() => {
			const node = document.createElement('div');
			node.textContent = plain;
			return node.innerHTML;
		})();
		const cleaned = sanitize(raw);
		document.execCommand('insertHTML', false, cleaned);
		editor.dispatchEvent(new Event('input', { bubbles: true }));
	}, true);

	form.addEventListener('input', (event) => {
		if (event.target !== form) return;
		const cleaned = sanitize(source.value);
		source.value = cleaned;
		editor.innerHTML = cleaned;
	}, true);

	// Native draft recovery is asynchronous and can write editor.innerHTML after
	// all scripts have already loaded. Observe the rendered editor as a final
	// browser-side privacy barrier so a restored third-party IMG cannot remain a
	// tracking resource merely because no form-level input event was dispatched.
	let sanitizing = false;
	const sanitizeRenderedEditor = () => {
		if (sanitizing) return;
		const current = editor.innerHTML;
		const cleaned = sanitize(current);
		if (cleaned === current) return;
		sanitizing = true;
		editor.innerHTML = cleaned;
		source.value = cleaned;
		sanitizing = false;
	};
	new MutationObserver(sanitizeRenderedEditor).observe(editor, { childList: true, subtree: true, attributes: true, attributeFilter: ['src', 'href'] });
	sanitizeRenderedEditor();
}());
