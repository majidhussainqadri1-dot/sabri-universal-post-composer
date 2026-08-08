(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	const config = window.SUPCFuture;
	if (!root || !config) return;

	const form = root.querySelector('[data-supc-form]');
	const panel = root.querySelector('.supc-intel');
	const editor = root.querySelector('[data-supc-rte]');
	const source = root.querySelector('[data-supc-rte-source]');
	if (!form || !panel) return;

	const capabilities = new Set();
	const restRoot = String(config.restRoot || '').replace(/\/+$/, '');
	let remoteFields = null;
	let lastConflict = null;

	const sessionUuid = () => new URL(window.location.href).searchParams.get('session') || '';
	const sensitive = () => Boolean(root.querySelector('[data-privacy="sensitive"], [data-field-key*="patient"], [data-field-key*="consent"]'));
	const fieldSnapshot = () => {
		const out = {};
		form.querySelectorAll('[data-supc-field]').forEach((field) => {
			if (field.dataset.fieldType === 'checkbox') out[field.name] = field.checked;
			else if (field.dataset.fieldType === 'multiselect') out[field.name] = Array.from(field.selectedOptions || []).map((option) => option.value);
			else out[field.name] = field.value;
		});
		return out;
	};

	const request = async (path, method, body) => {
		const response = await fetch(restRoot + path, {
			method,
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: body === undefined ? undefined : JSON.stringify(body)
		});
		const data = await response.json().catch(() => ({}));
		if (!response.ok) {
			const error = new Error(data.message || 'Capability request failed');
			error.code = data.code || 'supc_future_request_failed';
			throw error;
		}
		return data;
	};

	const invoke = async (capability, payload) => {
		if (!capabilities.has(capability)) throw new Error('Authorized provider unavailable');
		return request('/future/invoke', 'POST', {
			adapter_key: config.adapter || root.dataset.adapter,
			capability,
			session_uuid: sessionUuid(),
			payload: Object.assign({ sensitive: sensitive() }, payload || {})
		});
	};

	const resultPayload = (response) => response && response.result ? response.result : {};
	const resultText = (response) => {
		const value = resultPayload(response);
		if (typeof value === 'string') return value;
		if (typeof value.text === 'string') return value.text;
		if (typeof value.summary === 'string') return value.summary;
		if (typeof value.content === 'string') return value.content;
		return '';
	};

	const setResult = (tool, message, state) => {
		const node = panel.querySelector('[data-future-result="' + tool + '"]');
		if (!node) return;
		node.textContent = String(message || '');
		node.dataset.status = state || 'ready';
	};

	const addButton = (tool, label, attr) => {
		const details = panel.querySelector('[data-future-tool="' + tool + '"] .supc-intel__body');
		if (!details || details.querySelector('[' + attr + ']')) return null;
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'button';
		button.textContent = label;
		button.setAttribute(attr, '1');
		details.insertBefore(button, details.querySelector('[data-future-result]'));
		return button;
	};

	const dispatchEditorInput = () => {
		if (editor) editor.dispatchEvent(new Event('input', { bubbles: true }));
		else if (source) source.dispatchEvent(new Event('input', { bubbles: true }));
	};

	const insertExplicitSuggestion = (text) => {
		const value = String(text || '').trim();
		if (!value) return false;
		if (editor) {
			editor.focus();
			document.execCommand('insertText', false, value);
			dispatchEditorInput();
			return true;
		}
		if (source) {
			const start = typeof source.selectionStart === 'number' ? source.selectionStart : source.value.length;
			const end = typeof source.selectionEnd === 'number' ? source.selectionEnd : start;
			source.value = source.value.slice(0, start) + value + source.value.slice(end);
			source.dispatchEvent(new Event('input', { bubbles: true }));
			return true;
		}
		return false;
	};

	const openPalette = () => {
		const dialog = document.querySelector('.supc-intel__palette');
		if (!dialog) return;
		if (typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
		else dialog.setAttribute('open', '');
	};

	// Complete the literal slash-command experience, not only Ctrl/Cmd+K.
	if (editor) {
		editor.addEventListener('keydown', (event) => {
			if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
			const selection = window.getSelection();
			if (!selection || !selection.isCollapsed) return;
			const prefix = String(selection.anchorNode && selection.anchorNode.textContent || '').slice(0, selection.anchorOffset);
			if (prefix && !/\s$/.test(prefix)) return;
			window.setTimeout(openPalette, 0);
		});
	}

	const clearAnnotationMarkers = () => {
		root.querySelectorAll('.supc-intel-annotation-marker').forEach((node) => node.remove());
		root.querySelectorAll('.has-supc-intel-annotation').forEach((node) => node.classList.remove('has-supc-intel-annotation'));
	};

	const renderAnnotations = (annotations) => {
		clearAnnotationMarkers();
		let rendered = 0;
		(annotations || []).slice(0, 30).forEach((annotation) => {
			if (!annotation || typeof annotation !== 'object') return;
			const field = String(annotation.field || '').replace(/[^A-Za-z0-9_.-]/g, '');
			if (!field) return;
			const target = root.querySelector('[data-field-key="' + CSS.escape(field) + '"]');
			if (!target) return;
			const marker = document.createElement('aside');
			marker.className = 'supc-intel-annotation-marker';
			marker.setAttribute('role', 'note');
			marker.dataset.annotationId = String(annotation.id || '');
			const author = String(annotation.author_label || annotation.author || 'Reviewer');
			const message = String(annotation.message || annotation.comment || '').slice(0, 2000);
			marker.textContent = author + ': ' + message;
			target.classList.add('has-supc-intel-annotation');
			target.appendChild(marker);
			rendered += 1;
		});
		return rendered;
	};

	const annotationButton = panel.querySelector('[data-tool="annotations"]');
	if (annotationButton) {
		annotationButton.replaceWith(annotationButton.cloneNode(true));
		const fresh = panel.querySelector('[data-tool="annotations"]');
		fresh.addEventListener('click', async () => {
			fresh.disabled = true;
			try {
				const response = await invoke('review_annotations', { action: 'list' });
				const data = resultPayload(response);
				const list = Array.isArray(data.annotations) ? data.annotations : [];
				const count = renderAnnotations(list);
				setResult('annotations', count ? count + ' inline reviewer annotation(s) shown beside their fields.' : 'No field-addressable reviewer annotations were returned.', 'ready');
			} catch (error) { setResult('annotations', error.message, 'error'); }
			finally { fresh.disabled = false; }
		});
	}

	const collabBody = panel.querySelector('[data-future-tool="collaboration"] .supc-intel__body');
	if (collabBody) {
		const pull = addButton('collaboration', 'Pull remote collaborator update', 'data-collaboration-pull');
		const apply = addButton('collaboration', 'Apply remote update explicitly', 'data-collaboration-apply');
		if (apply) apply.disabled = true;
		if (pull) pull.addEventListener('click', async () => {
			pull.disabled = true;
			try {
				const response = await invoke('collaboration', { action: 'pull', current: fieldSnapshot() });
				const data = resultPayload(response);
				remoteFields = data && data.fields && typeof data.fields === 'object' ? data.fields : null;
				if (apply) apply.disabled = !remoteFields;
				setResult('collaboration', remoteFields ? 'Remote update is available. Review provider details, then use Apply Remote Update if appropriate.' : 'No remote field update was returned.', 'ready');
			} catch (error) { setResult('collaboration', error.message, 'error'); }
			finally { pull.disabled = false; }
		});
		if (apply) apply.addEventListener('click', () => {
			if (!remoteFields) return;
			form.querySelectorAll('[data-supc-field]').forEach((field) => {
				if (!Object.prototype.hasOwnProperty.call(remoteFields, field.name)) return;
				const value = remoteFields[field.name];
				if (field.dataset.fieldType === 'checkbox') field.checked = Boolean(value);
				else field.value = value == null ? '' : String(value);
				if (field.matches('[data-supc-rte-source]') && editor) editor.textContent = String(value == null ? '' : value);
			});
			remoteFields = null;
			apply.disabled = true;
			form.dispatchEvent(new Event('input', { bubbles: true }));
			setResult('collaboration', 'Remote update applied by explicit human action. Review and save to the native owner.', 'ready');
		});
	}

	const conflictBody = panel.querySelector('[data-future-tool="conflict"] .supc-intel__body');
	if (conflictBody) {
		const inspect = panel.querySelector('[data-tool="conflict"]');
		if (inspect) {
			inspect.replaceWith(inspect.cloneNode(true));
			const fresh = panel.querySelector('[data-tool="conflict"]');
			fresh.addEventListener('click', async () => {
				fresh.disabled = true;
				try {
					const response = await invoke('conflict_merge', { action: 'inspect', current: fieldSnapshot() });
					lastConflict = resultPayload(response);
					setResult('conflict', JSON.stringify(lastConflict, null, 2), 'ready');
				} catch (error) { setResult('conflict', error.message, 'error'); }
				finally { fresh.disabled = false; }
			});
		}
		['keep_current', 'accept_native', 'manual'].forEach((resolution) => {
			const label = resolution === 'keep_current' ? 'Resolve: keep current' : (resolution === 'accept_native' ? 'Resolve: accept native' : 'Resolve: manual merge');
			const button = addButton('conflict', label, 'data-conflict-' + resolution.replace('_', '-'));
			if (!button) return;
			button.addEventListener('click', async () => {
				if (!lastConflict) { setResult('conflict', 'Inspect the authoritative conflict before resolving it.', 'warning'); return; }
				button.disabled = true;
				try {
					const response = await invoke('conflict_merge', { action: 'resolve', resolution, conflict_token: lastConflict.conflict_token || '', current: resolution === 'keep_current' || resolution === 'manual' ? fieldSnapshot() : {} });
					setResult('conflict', 'Resolution sent explicitly: ' + resolution + '\n' + JSON.stringify(resultPayload(response), null, 2), 'ready');
				} catch (error) { setResult('conflict', error.message, 'error'); }
				finally { button.disabled = false; }
			});
		});
	}

	const mediaProvider = addButton('media', 'Check native advanced media tools', 'data-media-provider');
	if (mediaProvider) mediaProvider.addEventListener('click', async () => {
		mediaProvider.disabled = true;
		try {
			const response = await invoke('media_workbench', { action: 'capabilities' });
			setResult('media', JSON.stringify(resultPayload(response), null, 2), 'ready');
		} catch (error) { setResult('media', error.message, 'error'); }
		finally { mediaProvider.disabled = false; }
	});

	const makeApplySuggestion = (tool, capability) => {
		const button = addButton(tool, 'Insert returned suggestion at cursor', 'data-explicit-suggestion-apply');
		if (!button) return;
		button.disabled = true;
		const result = panel.querySelector('[data-future-result="' + tool + '"]');
		if (result) {
			new MutationObserver(() => { button.disabled = !String(result.textContent || '').trim() || result.dataset.status === 'error' || result.dataset.status === 'blocked'; }).observe(result, { childList: true, characterData: true, subtree: true, attributes: true });
		}
		button.addEventListener('click', async () => {
			let text = result ? String(result.textContent || '').trim() : '';
			// Ask the provider for a machine-readable applyable form when supported;
			// fall back to the already visible result so insertion is still explicit.
			try {
				const response = await invoke(capability, { action: 'applyable', visible_result: text.slice(0, 12000) });
				text = resultText(response) || text;
			} catch (error) { /* Visible result remains available for explicit insertion. */ }
			if (insertExplicitSuggestion(text)) setResult(tool, 'Suggestion inserted by explicit human action. Review it before saving or publishing.', 'ready');
		});
	};
	makeApplySuggestion('ai', 'ai_copilot');
	makeApplySuggestion('derivative', 'cross_format_derivative');

	const loadCapabilities = async () => {
		try {
			const response = await request('/future/capabilities/' + encodeURIComponent(config.adapter || root.dataset.adapter), 'GET');
			(response.bridge_capabilities || []).forEach((item) => capabilities.add(item));
			if (capabilities.has('collaboration')) {
				invoke('collaboration', { action: 'join', cursor: { collapsed: true } }).catch(() => {});
			}
		} catch (error) { /* Base layer already renders provider availability. */ }
	};

	loadCapabilities();
}());
