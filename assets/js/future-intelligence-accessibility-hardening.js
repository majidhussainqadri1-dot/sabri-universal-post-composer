(function () {
	'use strict';

	const dialog = document.querySelector('.supc-intel__palette');
	if (!dialog) return;
	let returnFocus = null;
	const nativeModal = typeof dialog.showModal === 'function';

	const heading = dialog.querySelector('header strong');
	if (heading) {
		if (!heading.id) heading.id = 'supc-intel-command-palette-title';
		dialog.setAttribute('aria-labelledby', heading.id);
	}
	dialog.setAttribute('aria-modal', 'true');
	if (!dialog.getAttribute('role')) dialog.setAttribute('role', 'dialog');

	const focusables = () => Array.from(dialog.querySelectorAll('button:not([disabled]), input:not([disabled]), select:not([disabled]), textarea:not([disabled]), [href], [tabindex]:not([tabindex="-1"])')).filter((node) => !node.hidden && node.getClientRects().length > 0);
	const focusFirst = () => {
		const items = focusables();
		if (items.length) items[0].focus();
		else {
			dialog.setAttribute('tabindex', '-1');
			dialog.focus();
		}
	};
	const rememberOpener = () => {
		const active = document.activeElement;
		if (active && active !== document.body && active !== dialog && !dialog.contains(active)) returnFocus = active;
	};
	const restoreFocus = () => {
		const active = document.activeElement;
		if (active && active !== document.body && active !== dialog && !dialog.contains(active)) return;
		if (returnFocus && document.contains(returnFocus) && typeof returnFocus.focus === 'function') returnFocus.focus();
		returnFocus = null;
	};

	new MutationObserver(() => {
		if (!dialog.hasAttribute('open')) return;
		rememberOpener();
		window.setTimeout(focusFirst, 0);
	}).observe(dialog, { attributes: true, attributeFilter: ['open'] });

	dialog.addEventListener('close', restoreFocus);
	dialog.addEventListener('cancel', () => window.setTimeout(restoreFocus, 0));
	document.addEventListener('keydown', (event) => {
		if (!dialog.hasAttribute('open')) return;
		if (event.key === 'Escape' && !nativeModal) {
			event.preventDefault();
			dialog.removeAttribute('open');
			restoreFocus();
			return;
		}
		if (event.key !== 'Tab' || nativeModal) return;
		const items = focusables();
		if (!items.length) { event.preventDefault(); dialog.focus(); return; }
		const first = items[0];
		const last = items[items.length - 1];
		if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
		else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
	}, true);

	const fallbackClose = dialog.querySelector('button[value="cancel"]');
	if (fallbackClose) fallbackClose.addEventListener('click', () => {
		if (!nativeModal) {
			dialog.removeAttribute('open');
			restoreFocus();
		}
	});

	// Any command that closes the palette must leave a deterministic focus
	// destination. If the command itself moved focus, restoreFocus deliberately
	// does not override that destination.
	dialog.querySelectorAll('[data-command-list] button').forEach((button) => {
		button.addEventListener('click', () => window.setTimeout(restoreFocus, 0));
	});
}());
