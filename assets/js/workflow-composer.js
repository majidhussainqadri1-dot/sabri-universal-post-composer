(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	if (!root || !window.SUPCWorkflow) {
		return;
	}

	const form = root.querySelector('[data-supc-form]');
	const status = root.querySelector('[data-supc-status]');
	const errors = root.querySelector('.supc-workflow__errors');
	const config = window.SUPCWorkflow;
	const restRoot = String(config.restRoot || '').replace(/\/+$/, '');
	let session = null;
	let busy = false;
	let dirty = false;
	let timer = null;
	let periodicTimer = null;
	let resumePromise = null;

	const announce = (message) => {
		status.textContent = message;
	};

	const escapeHtml = (value) => {
		const div = document.createElement('div');
		div.textContent = String(value);
		return div.innerHTML;
	};

	const showErrors = (messages) => {
		if (!messages.length) {
			errors.hidden = true;
			errors.textContent = '';
			return;
		}
		errors.innerHTML = '<h3>' + escapeHtml(config.strings.errorHeading) + '</h3><ul>' + messages.map((item) => '<li>' + escapeHtml(item) + '</li>').join('') + '</ul>';
		errors.hidden = false;
		errors.focus();
	};

	const payload = () => {
		const result = {};
		form.querySelectorAll('[data-supc-field]').forEach((field) => {
			const type = field.dataset.fieldType;
			if (type === 'checkbox') {
				result[field.name] = field.checked;
			} else if (type === 'multiselect') {
				result[field.name] = Array.from(field.selectedOptions).map((option) => option.value);
			} else if (type === 'number') {
				result[field.name] = field.value === '' ? null : Number(field.value);
			} else if (type === 'datetime' && field.value) {
				result[field.name] = field.value.length === 16 ? field.value + ':00' : field.value;
			} else {
				result[field.name] = field.value;
			}
		});
		return result;
	};

	const request = async (path, method, body) => {
		const response = await fetch(restRoot + path, {
			method: method,
			credentials: 'same-origin',
			cache: 'no-store',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': config.nonce
			},
			body: body === undefined ? undefined : JSON.stringify(body),
			keepalive: false
		});
		const data = await response.json().catch(() => ({}));
		if (!response.ok) {
			const code = data.code || 'supc_request_failed';
			const error = new Error(config.errors[code] || config.strings.genericError);
			error.code = code;
			error.data = data.data || {};
			throw error;
		}
		return data;
	};

	const ensureSession = async () => {
		if (resumePromise) {
			await resumePromise;
		}
		if (session) {
			return session;
		}
		const created = await request('/sessions', 'POST', { adapter_key: root.dataset.adapter });
		session = created.session;
		const url = new URL(window.location.href);
		url.searchParams.set('session', session.session_uuid);
		window.history.replaceState({}, '', url.toString());
		return session;
	};

	const resumeSession = async () => {
		const uuid = new URL(window.location.href).searchParams.get('session');
		if (!uuid) {
			return;
		}
		try {
			const restored = await request('/sessions/' + encodeURIComponent(uuid), 'GET');
			if (restored.session.adapter_key === root.dataset.adapter) {
				session = restored.session;
				announce(config.strings.sessionRecovered.replace('%s', session.updated_at));
			}
		} catch (error) {
			const url = new URL(window.location.href);
			url.searchParams.delete('session');
			window.history.replaceState({}, '', url.toString());
			announce(config.strings.sessionNotRecovered);
		}
	};

	const run = async (operation) => {
		if (busy) {
			const error = new Error(config.strings.requestBusy || config.strings.genericError);
			error.code = 'supc_session_busy';
			throw error;
		}
		busy = true;
		form.setAttribute('aria-busy', 'true');
		showErrors([]);
		try {
			await ensureSession();
			const data = await request('/sessions/' + encodeURIComponent(session.session_uuid) + '/' + operation, 'POST', {
				lock_version: session.lock_version,
				payload: payload()
			});
			if (data.session) {
				session = data.session;
			}
			return data;
		} catch (error) {
			if (error.data && error.data.details && error.data.details.session) {
				session = error.data.details.session;
			}
			showErrors([error.message || config.strings.genericError]);
			throw error;
		} finally {
			busy = false;
			form.removeAttribute('aria-busy');
		}
	};

	const save = async (silent) => {
		if (!dirty && session && session.native_reference) {
			return true;
		}
		if (!silent) {
			announce(config.strings.saving);
		}
		try {
			await run('autosave');
			dirty = false;
			announce(config.strings.saved);
			return true;
		} catch (error) {
			announce(config.strings.notSaved);
			return false;
		}
	};

	form.addEventListener('input', () => {
		dirty = true;
		window.clearTimeout(timer);
		announce(config.strings.unsaved);
		timer = window.setTimeout(() => save(true), 2000);
	});

	periodicTimer = window.setInterval(() => {
		if (dirty && !busy && document.visibilityState !== 'hidden') {
			save(true);
		}
	}, 25000);

	form.querySelector('[data-supc-action="save"]').addEventListener('click', () => save(false));
	form.querySelector('[data-supc-action="preview"]').addEventListener('click', async () => {
		const previewWindow = window.open('about:blank', '_blank');
		if (previewWindow) {
			previewWindow.opener = null;
		}
		if (!(await save(false))) {
			if (previewWindow) {
				previewWindow.close();
			}
			return;
		}
		announce(config.strings.previewing);
		try {
			const result = await run('preview');
			announce(config.strings.previewReady);
			if (previewWindow) {
				previewWindow.location.replace(result.preview_url);
			} else {
				const link = document.createElement('a');
				link.href = result.preview_url;
				link.target = '_blank';
				link.rel = 'noopener noreferrer';
				link.textContent = config.strings.openPreview;
				link.className = 'button';
				form.querySelector('.supc-workflow__actions').appendChild(link);
			}
		} catch (error) {
			if (previewWindow) {
				previewWindow.close();
			}
			announce(config.strings.previewFailed);
		}
	});

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		if (!form.reportValidity()) {
			announce(config.strings.fixFields);
			return;
		}
		if (!(await save(false))) {
			return;
		}
		announce(config.strings.validating);
		try {
			const validation = await run('validate');
			if (!validation.valid) {
				showErrors((validation.errors || []).map((code) => config.errors[code] || code));
				announce(config.strings.validationFailed);
				return;
			}
			announce(config.strings.submitting);
			const result = await run('submit');
			dirty = false;
			announce(config.strings.submitted);
			window.clearTimeout(timer);
			window.clearInterval(periodicTimer);
			form.querySelectorAll('button').forEach((button) => { button.disabled = true; });
			if (result.native && result.native.canonical_url) {
				const link = document.createElement('a');
				link.href = result.native.canonical_url;
				link.textContent = config.strings.viewPublication;
				link.className = 'button';
				form.querySelector('.supc-workflow__actions').appendChild(link);
			}
		} catch (error) {
			announce(config.strings.submitFailed);
		}
	});

	window.addEventListener('beforeunload', (event) => {
		if (dirty || busy) {
			event.preventDefault();
			event.returnValue = '';
		}
	});

	resumePromise = resumeSession().finally(() => {
		resumePromise = null;
	});
}());
