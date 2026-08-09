(function () {
	'use strict';

	if (!window.indexedDB) return;
	const LEGACY_DB = 'supc-future-recovery-v1';
	const root = document.querySelector('[data-supc-workflow]');
	const panel = root ? root.querySelector('.supc-intel') : null;
	let attempted = false;

	const setResult = (message, state) => {
		const node = panel ? panel.querySelector('[data-future-result="offline"]') : null;
		if (!node || String(node.dataset.status || '') === 'blocked') return;
		node.textContent = String(message || '');
		node.dataset.status = state || 'ready';
	};
	const retire = () => {
		if (attempted) return;
		attempted = true;
		let request;
		try {
			request = indexedDB.deleteDatabase(LEGACY_DB);
		} catch (error) {
			setResult('Legacy browser recovery retirement could not be requested. No legacy recovery data will be read or reused by File 22.', 'warning');
			return;
		}
		request.onsuccess = () => {
			// Silent success: absence of the retired store is the normal state.
		};
		request.onerror = () => {
			setResult('Legacy browser recovery retirement failed. The retired database remains inaccessible to the current recovery path and should be cleared by the browser/site-data lifecycle.', 'warning');
		};
		request.onblocked = () => {
			setResult('Legacy browser recovery retirement is waiting for another older Composer tab to close. The current File 22 recovery path will not read that retired database.', 'warning');
		};
	};

	retire();
}());