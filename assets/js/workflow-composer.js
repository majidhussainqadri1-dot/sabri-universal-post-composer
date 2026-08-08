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
	const steps = Array.from(root.querySelectorAll('[data-supc-step]'));
	const stepButtons = Array.from(root.querySelectorAll('[data-supc-step-button]'));
	const actionSelect = root.querySelector('[data-supc-publication-action]');
	const connectionBox = root.querySelector('[data-supc-connection]');
	const connectionLabel = root.querySelector('[data-supc-connection-label]');
	const autosaveState = root.querySelector('[data-supc-autosave-state]');
	const wordCount = root.querySelector('[data-supc-word-count]');
	const readingTime = root.querySelector('[data-supc-reading-time]');
	const safetySummary = root.querySelector('[data-supc-safety-summary]');
	const previewCanvas = root.querySelector('[data-supc-preview-canvas]');
	const previewTitle = root.querySelector('[data-supc-preview-title]');
	const previewBody = root.querySelector('[data-supc-preview-body]');
	const previewMeta = root.querySelector('[data-supc-preview-meta]');
	const uploadInput = root.querySelector('[data-supc-upload-input]');
	const uploadList = root.querySelector('[data-supc-upload-list]');
	const nativeUrl = String(root.dataset.nativeUrl || '');
	let session = null;
	let busy = false;
	let dirty = false;
	let timer = null;
	let periodicTimer = null;
	let resumePromise = null;
	let reconciling = false;
	let currentStep = 'compose';
	let mode = 'advanced';
	let connectionState = 'online';

	const strings = Object.assign({
		offline: 'Offline — editing remains available, but File 22 will not claim a save or submit until the connection returns.',
		degraded: 'Weak connection — autosave and uploads may take longer.',
		online: 'Online',
		syncing: 'Syncing with the native owner…',
		conflict: 'Conflict detected — reload the authoritative native draft before continuing.',
		validationPassed: 'Validation passed.',
		validationRequired: 'Complete the required fields before continuing.',
		uploadPreparing: 'Preparing native upload…',
		uploadPending: 'The native owner issued an upload token but did not provide a direct transfer URL. Continue in the native owner.',
		uploadComplete: 'Upload completed with the native owner.',
		uploadFailed: 'Upload failed. Your draft text was not discarded.'
	}, config.strings || {});

	const announce = (message) => {
		if (status) {
			status.textContent = message;
		}
	};

	const escapeHtml = (value) => {
		const div = document.createElement('div');
		div.textContent = String(value);
		return div.innerHTML;
	};

	const setAutosaveState = (value) => {
		if (autosaveState) {
			autosaveState.textContent = value;
		}
	};

	const setConnection = (state, message) => {
		connectionState = state;
		root.dataset.connection = state;
		if (connectionBox) {
			connectionBox.dataset.state = state;
		}
		if (connectionLabel) {
			connectionLabel.textContent = message;
		}
		root.querySelectorAll('[data-supc-final-action]').forEach((button) => {
			button.disabled = state === 'offline' || state === 'conflict';
		});
	};

	const evaluateConnection = () => {
		if (!navigator.onLine) {
			setConnection('offline', strings.offline);
			return;
		}
		const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
		if (connection && (connection.effectiveType === 'slow-2g' || connection.effectiveType === '2g' || connection.saveData)) {
			setConnection('degraded', strings.degraded);
			return;
		}
		setConnection('online', strings.online);
	};

	const showErrors = (messages) => {
		if (!errors) {
			return;
		}
		if (!messages.length) {
			errors.hidden = true;
			errors.textContent = '';
			return;
		}
		errors.innerHTML = '<h3>' + escapeHtml(config.strings.errorHeading) + '</h3><ul>' + messages.map((item) => '<li>' + escapeHtml(item) + '</li>').join('') + '</ul>';
		errors.hidden = false;
		errors.focus();
	};

	const safeUrl = (value) => {
		try {
			const url = new URL(value, window.location.origin);
			return ['http:', 'https:'].includes(url.protocol) ? url.href : '';
		} catch (error) {
			return '';
		}
	};

	const sanitizeRichHtml = (html) => {
		const template = document.createElement('template');
		template.innerHTML = String(html || '');
		const allowed = new Set(['P', 'BR', 'H2', 'H3', 'H4', 'STRONG', 'B', 'EM', 'I', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'A', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'HR', 'SUP', 'SUB', 'FIGURE', 'FIGCAPTION', 'IMG']);
		const walk = (node) => {
			Array.from(node.children || []).forEach((child) => {
				if (!allowed.has(child.tagName)) {
					child.replaceWith(...Array.from(child.childNodes));
					return;
				}
				Array.from(child.attributes).forEach((attribute) => {
					const name = attribute.name.toLowerCase();
					if (child.tagName === 'A' && name === 'href') {
						const href = safeUrl(attribute.value);
						if (href) {
							child.setAttribute('href', href);
							child.setAttribute('rel', 'noopener noreferrer');
						} else {
							child.removeAttribute('href');
						}
						return;
					}
					if (child.tagName === 'IMG' && ['src', 'alt', 'title'].includes(name)) {
						if (name === 'src') {
							const src = safeUrl(attribute.value);
							if (src) {
								child.setAttribute('src', src);
							} else {
								child.removeAttribute('src');
							}
						}
						return;
					}
					if (!(child.tagName === 'A' && name === 'rel')) {
						child.removeAttribute(attribute.name);
					}
				});
				walk(child);
			});
		};
		walk(template.content);
		return template.innerHTML;
	};

	const syncRichEditor = (source) => {
		const wrap = source.closest('[data-supc-rte-wrap]');
		const editor = wrap ? wrap.querySelector('[data-supc-rte]') : null;
		if (!editor) {
			return;
		}
		source.value = sanitizeRichHtml(editor.innerHTML);
	};

	const syncAllRichEditors = () => {
		root.querySelectorAll('[data-supc-rte-source]').forEach(syncRichEditor);
	};

	const richTextPlain = () => {
		const editor = root.querySelector('[data-supc-rte]');
		return editor ? String(editor.textContent || '').replace(/\s+/g, ' ').trim() : '';
	};

	const updateMetrics = () => {
		const text = richTextPlain();
		const words = text ? text.split(/\s+/).filter(Boolean).length : 0;
		const minutes = words ? Math.max(1, Math.ceil(words / 200)) : 0;
		if (wordCount) {
			wordCount.textContent = String(words);
		}
		if (readingTime) {
			readingTime.textContent = minutes + ' min';
		}
		root.querySelectorAll('[data-supc-rte-words]').forEach((node) => { node.textContent = words + ' words'; });
		root.querySelectorAll('[data-supc-rte-reading]').forEach((node) => { node.textContent = minutes + ' min read'; });
	};

	const getField = (tokens) => {
		const fields = Array.from(form.querySelectorAll('[data-supc-field]'));
		return fields.find((field) => tokens.some((token) => String(field.name || '').toLowerCase().includes(token))) || null;
	};

	const updateDraftPreview = () => {
		if (!previewCanvas) {
			return;
		}
		syncAllRichEditors();
		const titleField = getField(['title']);
		const excerptField = getField(['excerpt', 'introduction']);
		const richSource = root.querySelector('[data-supc-rte-source]');
		const title = titleField && titleField.value.trim() ? titleField.value.trim() : 'Your title will appear here';
		const excerpt = excerptField && excerptField.value.trim() ? excerptField.value.trim() : '';
		const body = richSource && richSource.value.trim() ? sanitizeRichHtml(richSource.value) : escapeHtml(excerpt || 'Your content preview will appear here as you write.');
		if (previewTitle) {
			previewTitle.textContent = title;
		}
		if (previewBody) {
			previewBody.innerHTML = body;
		}
		if (previewMeta) {
			previewMeta.textContent = 'Draft · ' + (session && session.updated_at ? 'Last saved ' + session.updated_at + ' UTC' : 'Not saved yet');
		}
	};

	const initRichEditors = () => {
		root.querySelectorAll('[data-supc-rte-wrap]').forEach((wrap) => {
			const editor = wrap.querySelector('[data-supc-rte]');
			const source = wrap.querySelector('[data-supc-rte-source]');
			if (!editor || !source) {
				return;
			}
			editor.addEventListener('input', () => {
				syncRichEditor(source);
				updateMetrics();
				updateDraftPreview();
			});
			editor.addEventListener('paste', (event) => {
				event.preventDefault();
				const clipboard = event.clipboardData;
				const raw = clipboard ? (clipboard.getData('text/html') || escapeHtml(clipboard.getData('text/plain'))) : '';
				const clean = sanitizeRichHtml(raw);
				document.execCommand('insertHTML', false, clean);
				syncRichEditor(source);
				updateMetrics();
				updateDraftPreview();
			});
			wrap.querySelectorAll('[data-supc-rte-command]').forEach((button) => {
				button.addEventListener('click', () => {
					editor.focus();
					const command = button.dataset.supcRteCommand;
					if (command === 'bold' || command === 'italic' || command === 'undo' || command === 'redo') {
						document.execCommand(command, false, null);
					} else if (command === 'h2') {
						document.execCommand('formatBlock', false, 'h2');
					} else if (command === 'ul') {
						document.execCommand('insertUnorderedList', false, null);
					} else if (command === 'ol') {
						document.execCommand('insertOrderedList', false, null);
					} else if (command === 'quote') {
						document.execCommand('formatBlock', false, 'blockquote');
					} else if (command === 'link') {
						const href = safeUrl(window.prompt('Enter an http(s) URL') || '');
						if (href) {
							document.execCommand('createLink', false, href);
						}
					} else if (command === 'table') {
						document.execCommand('insertHTML', false, '<table><tbody><tr><th>Heading</th><th>Heading</th></tr><tr><td>Cell</td><td>Cell</td></tr></tbody></table>');
					} else if (command === 'footnote') {
						document.execCommand('insertHTML', false, '<sup>[1]</sup>');
					} else if (command === 'hr') {
						document.execCommand('insertHorizontalRule', false, null);
					}
					syncRichEditor(source);
					updateMetrics();
					updateDraftPreview();
				});
			});
		});
		updateMetrics();
		updateDraftPreview();
	};

	const payload = () => {
		syncAllRichEditors();
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
		if (!navigator.onLine) {
			const offline = new Error(strings.offline);
			offline.code = 'supc_offline';
			throw offline;
		}
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

	const applyRecoveredPayload = (recovered) => {
		if (!recovered || typeof recovered !== 'object' || Array.isArray(recovered)) {
			return false;
		}
		form.querySelectorAll('[data-supc-field]').forEach((field) => {
			if (!Object.prototype.hasOwnProperty.call(recovered, field.name)) {
				return;
			}
			const value = recovered[field.name];
			const type = field.dataset.fieldType;
			if (type === 'checkbox') {
				field.checked = Boolean(value);
			} else if (type === 'multiselect' && Array.isArray(value)) {
				const selected = new Set(value.map((item) => String(item)));
				Array.from(field.options).forEach((option) => { option.selected = selected.has(option.value); });
			} else if (value === null || value === undefined) {
				field.value = '';
			} else {
				field.value = String(value);
			}
			if (field.matches('[data-supc-rte-source]')) {
				const wrap = field.closest('[data-supc-rte-wrap]');
				const editor = wrap ? wrap.querySelector('[data-supc-rte]') : null;
				if (editor) {
					editor.innerHTML = sanitizeRichHtml(field.value);
				}
			}
		});
		dirty = false;
		updateMetrics();
		updateDraftPreview();
		return true;
	};

	const disableUnsafeEditing = () => {
		form.querySelectorAll('[data-supc-field], [data-supc-rte], button').forEach((control) => {
			if ('contentEditable' in control) {
				control.contentEditable = 'false';
			} else {
				control.disabled = true;
			}
		});
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
				if (restored.draft_recovery === 'recovered' && applyRecoveredPayload(restored.draft_payload)) {
					announce(config.strings.draftRecovered.replace('%s', session.updated_at));
				} else if (session.native_reference_present && restored.draft_recovery === 'unsupported') {
					disableUnsafeEditing();
					announce(config.strings.draftRecoveryUnsupported);
				} else {
					announce(config.strings.sessionRecovered.replace('%s', session.updated_at));
				}
				if (session.reconciliation_required) {
					window.setTimeout(() => attemptReconciliation(true), 1000);
				}
			}
		} catch (error) {
			const details = error.data && error.data.details ? error.data.details : {};
			if (details.session && details.session.adapter_key === root.dataset.adapter) {
				session = details.session;
				announce(details.reconciliation_required ? config.strings.reconciliationPending : config.strings.sessionNotRecovered);
				if (details.reconciliation_required) {
					window.setTimeout(() => attemptReconciliation(true), 1000);
				}
				return;
			}
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
			if (error.code === 'supc_session_conflict') {
				setConnection('conflict', strings.conflict);
			}
			showErrors([(error.message || config.strings.genericError) + (error.code ? ' [' + error.code + ']' : '')]);
			throw error;
		} finally {
			busy = false;
			form.removeAttribute('aria-busy');
		}
	};

	const save = async (silent) => {
		if (!dirty && session) {
			return true;
		}
		if (!navigator.onLine) {
			setAutosaveState('Offline');
			announce(strings.offline);
			return false;
		}
		if (!silent) {
			announce(config.strings.saving);
		}
		setConnection('syncing', strings.syncing);
		setAutosaveState('Saving…');
		try {
			await run('autosave');
			dirty = false;
			announce(config.strings.saved);
			setAutosaveState('Saved');
			updateDraftPreview();
			evaluateConnection();
			return true;
		} catch (error) {
			announce(config.strings.notSaved);
			setAutosaveState('Not saved');
			if (error.code !== 'supc_session_conflict') {
				evaluateConnection();
			}
			return false;
		}
	};

	const setStep = (step) => {
		if (!steps.some((candidate) => candidate.dataset.supcStep === step)) {
			return;
		}
		currentStep = step;
		if (mode === 'quick') {
			return;
		}
		steps.forEach((section) => {
			const active = section.dataset.supcStep === step;
			section.hidden = !active;
			section.classList.toggle('is-active', active);
		});
		stepButtons.forEach((button) => {
			const active = button.dataset.supcStepButton === step;
			button.setAttribute('aria-current', active ? 'step' : 'false');
			const item = button.closest('li');
			if (item) {
				item.classList.toggle('is-current', active);
			}
		});
		const activeSection = steps.find((section) => section.dataset.supcStep === step);
		if (activeSection) {
			activeSection.scrollIntoView({ behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth', block: 'start' });
		}
	};

	const setMode = (nextMode) => {
		mode = nextMode === 'quick' ? 'quick' : 'advanced';
		root.dataset.mode = mode;
		root.querySelectorAll('[data-supc-mode]').forEach((button) => {
			const active = button.dataset.supcMode === mode;
			button.setAttribute('aria-pressed', active ? 'true' : 'false');
			button.classList.toggle('button-primary', active);
		});
		if (mode === 'quick') {
			steps.forEach((section) => {
				const visible = ['compose', 'media'].includes(section.dataset.supcStep);
				section.hidden = !visible;
				section.classList.toggle('is-active', visible);
			});
		} else {
			setStep(currentStep);
		}
	};

	const clientRequiredMissing = () => {
		const missing = [];
		form.querySelectorAll('[data-supc-field][data-required="1"]').forEach((field) => {
			let empty = false;
			if (field.matches('[data-supc-rte-source]')) {
				const wrap = field.closest('[data-supc-rte-wrap]');
				const editor = wrap ? wrap.querySelector('[data-supc-rte]') : null;
				empty = !editor || !String(editor.textContent || '').trim();
			} else if (field.dataset.fieldType === 'checkbox') {
				empty = !field.checked;
			} else if (field.dataset.fieldType === 'multiselect') {
				empty = field.selectedOptions.length === 0;
			} else {
				empty = !String(field.value || '').trim();
			}
			if (empty && field.name !== 'publication_action') {
				missing.push(field.name);
			}
		});
		return missing;
	};

	const validationGroupForCode = (code) => {
		const value = String(code || '').toLowerCase();
		if (/required|missing/.test(value)) return 'required';
		if (/media|upload|image|video|pdf|mime/.test(value)) return 'media';
		if (/permission|authoriz|account|capab|role/.test(value)) return 'permission';
		if (/medical|emergency|treatment|clinical/.test(value)) return 'medical';
		if (/patient|privacy|consent|anonym/.test(value)) return 'privacy';
		if (/copyright|rights|license/.test(value)) return 'copyright';
		if (/reference|citation|source/.test(value)) return 'references';
		return 'technical';
	};

	const renderValidationSummary = (codes) => {
		const groups = {};
		root.querySelectorAll('[data-validation-group]').forEach((node) => { groups[node.dataset.validationGroup] = node; });
		Object.keys(groups).forEach((key) => {
			const paragraph = groups[key].querySelector('p');
			if (paragraph) paragraph.textContent = 'Pass';
			groups[key].classList.remove('has-errors');
		});
		codes.forEach((code) => {
			const group = groups[validationGroupForCode(code)];
			if (!group) return;
			const paragraph = group.querySelector('p');
			if (paragraph) paragraph.textContent = config.errors[code] || code;
			group.classList.add('has-errors');
		});
		if (safetySummary) {
			safetySummary.textContent = codes.length ? codes.length + ' issue(s) require attention.' : strings.validationPassed;
		}
	};

	const validateWorkflow = async () => {
		const missing = clientRequiredMissing();
		if (missing.length) {
			const codes = missing.map((name) => 'required_field_' + name);
			renderValidationSummary(codes);
			showErrors(missing.map((name) => 'Required field: ' + name));
			announce(strings.validationRequired);
			return false;
		}
		if (!(await save(false))) {
			return false;
		}
		announce(config.strings.validating);
		try {
			const validation = await run('validate');
			const codes = Array.isArray(validation.errors) ? validation.errors : [];
			renderValidationSummary(codes);
			if (!validation.valid) {
				showErrors(codes.map((code) => config.errors[code] || code));
				announce(config.strings.validationFailed);
				return false;
			}
			announce(strings.validationPassed);
			return true;
		} catch (error) {
			const details = error.data && error.data.details ? error.data.details : {};
			const codes = Array.isArray(details.codes) ? details.codes : [error.code || 'technical_error'];
			renderValidationSummary(codes);
			announce(config.strings.validationFailed);
			return false;
		}
	};

	const finishSubmission = (result, message) => {
		dirty = false;
		announce(message || config.strings.submitted);
		setAutosaveState('Completed');
		window.clearTimeout(timer);
		window.clearInterval(periodicTimer);
		form.querySelectorAll('button').forEach((button) => { button.disabled = true; });
		const native = result && result.native ? result.native : null;
		if (native && native.canonical_url) {
			const link = document.createElement('a');
			link.href = native.canonical_url;
			link.textContent = config.strings.viewPublication;
			link.className = 'button';
			const actions = form.querySelector('.supc-workflow__actions');
			if (actions) actions.appendChild(link);
		}
	};

	const attemptReconciliation = async (silent) => {
		if (reconciling || !session || !navigator.onLine) {
			return null;
		}
		reconciling = true;
		if (!silent) {
			announce(config.strings.reconciling);
		}
		try {
			const result = await run('reconcile');
			if (result.resolved) {
				finishSubmission(result, config.strings.reconciliationResolved);
				return result;
			}
			announce(config.strings.reconciliationRetryable);
			return result;
		} catch (error) {
			announce(config.strings.reconciliationPending);
			return null;
		} finally {
			reconciling = false;
		}
	};

	const authoritativePreview = async () => {
		const previewWindow = window.open('about:blank', '_blank');
		if (previewWindow) previewWindow.opener = null;
		if (!(await validateWorkflow())) {
			if (previewWindow) previewWindow.close();
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
				const actions = form.querySelector('.supc-workflow__actions');
				if (actions) actions.appendChild(link);
			}
		} catch (error) {
			if (previewWindow) previewWindow.close();
			announce(config.strings.previewFailed);
		}
	};

	const sha256 = async (file) => {
		if (!window.crypto || !window.crypto.subtle) return null;
		const buffer = await file.arrayBuffer();
		const digest = await window.crypto.subtle.digest('SHA-256', buffer);
		return Array.from(new Uint8Array(digest)).map((byte) => byte.toString(16).padStart(2, '0')).join('');
	};

	const uploadViaXhr = (url, file, item) => new Promise((resolve, reject) => {
		const xhr = new XMLHttpRequest();
		xhr.open('PUT', url, true);
		xhr.upload.addEventListener('progress', (event) => {
			if (!event.lengthComputable) return;
			const progress = Math.round((event.loaded / event.total) * 100);
			const meter = item.querySelector('progress');
			if (meter) meter.value = progress;
		});
		xhr.addEventListener('load', () => {
			if (xhr.status >= 200 && xhr.status < 300) resolve();
			else reject(new Error('Native upload failed with HTTP ' + xhr.status));
		});
		xhr.addEventListener('error', () => reject(new Error('Native upload network error')));
		xhr.setRequestHeader('Content-Type', file.type || 'application/octet-stream');
		xhr.send(file);
	});

	const bindOpaqueReference = (reference) => {
		const target = Array.from(form.querySelectorAll('[data-supc-opaque-reference]')).find((field) => !field.value);
		if (target) {
			target.value = reference;
			dirty = true;
		}
	};

	const uploadFile = async (file) => {
		if (!uploadList) return;
		const item = document.createElement('li');
		item.innerHTML = '<strong>' + escapeHtml(file.name) + '</strong><progress max="100" value="0"></progress><span data-upload-status>' + escapeHtml(strings.uploadPreparing) + '</span>';
		uploadList.appendChild(item);
		const itemStatus = item.querySelector('[data-upload-status]');
		try {
			await ensureSession();
			const hash = await sha256(file);
			const begin = await request('/sessions/' + encodeURIComponent(session.session_uuid) + '/uploads', 'POST', {
				purpose: 'composer_media',
				metadata: {
					mime_type: file.type || 'application/octet-stream',
					size_bytes: file.size,
					checksum_sha256: hash
				}
			});
			if (!begin.upload || !begin.native) throw new Error(strings.uploadFailed);
			if (!begin.native.upload_url) {
				if (itemStatus) itemStatus.textContent = strings.uploadPending;
				if (nativeUrl) {
					const link = document.createElement('a');
					link.href = nativeUrl;
					link.textContent = 'Open native owner';
					link.className = 'button';
					item.appendChild(link);
				}
				return;
			}
			await uploadViaXhr(begin.native.upload_url, file, item);
			const completed = await request('/sessions/' + encodeURIComponent(session.session_uuid) + '/uploads/' + encodeURIComponent(begin.upload.upload_uuid) + '/complete', 'POST', {});
			const reference = completed.native && completed.native.upload_reference ? completed.native.upload_reference : begin.native.upload_reference;
			if (reference) bindOpaqueReference(reference);
			const meter = item.querySelector('progress');
			if (meter) meter.value = 100;
			if (itemStatus) itemStatus.textContent = strings.uploadComplete;
			announce(strings.uploadComplete);
		} catch (error) {
			if (itemStatus) itemStatus.textContent = strings.uploadFailed + (error.message ? ' ' + error.message : '');
			announce(strings.uploadFailed);
		}
	};

	form.addEventListener('input', (event) => {
		if (event.target && event.target.matches('[data-supc-rte-source]')) return;
		dirty = true;
		window.clearTimeout(timer);
		announce(config.strings.unsaved);
		setAutosaveState('Unsaved');
		updateDraftPreview();
		if (navigator.onLine) timer = window.setTimeout(() => save(true), 2000);
	});

	periodicTimer = window.setInterval(() => {
		if (dirty && !busy && navigator.onLine && document.visibilityState !== 'hidden') {
			save(true);
		}
	}, 25000);

	root.querySelectorAll('[data-supc-mode]').forEach((button) => {
		button.addEventListener('click', () => setMode(button.dataset.supcMode));
	});
	stepButtons.forEach((button) => button.addEventListener('click', () => setStep(button.dataset.supcStepButton)));
	root.querySelectorAll('[data-supc-step-go]').forEach((button) => button.addEventListener('click', () => setStep(button.dataset.supcStepGo)));
	root.querySelectorAll('[data-supc-action="save"]').forEach((button) => button.addEventListener('click', () => save(false)));
	root.querySelectorAll('[data-supc-action="validate"]').forEach((button) => button.addEventListener('click', validateWorkflow));
	root.querySelectorAll('[data-supc-action="preview"]').forEach((button) => button.addEventListener('click', authoritativePreview));
	root.querySelectorAll('[data-supc-preview-surface]').forEach((button) => {
		button.addEventListener('click', () => {
			root.querySelectorAll('[data-supc-preview-surface]').forEach((candidate) => candidate.classList.remove('is-active'));
			button.classList.add('is-active');
			if (previewCanvas) previewCanvas.dataset.surface = button.dataset.supcPreviewSurface;
		});
	});
	root.querySelectorAll('[data-supc-preview-device]').forEach((button) => {
		button.addEventListener('click', () => {
			root.querySelectorAll('[data-supc-preview-device]').forEach((candidate) => candidate.classList.remove('is-active'));
			button.classList.add('is-active');
			if (previewCanvas) previewCanvas.dataset.device = button.dataset.supcPreviewDevice;
		});
	});
	if (uploadInput) {
		uploadInput.addEventListener('change', () => {
			Array.from(uploadInput.files || []).forEach(uploadFile);
			uploadInput.value = '';
		});
	}

	form.addEventListener('submit', async (event) => {
		event.preventDefault();
		const submitter = event.submitter;
		if (submitter && submitter.dataset.supcFinalAction && actionSelect) {
			actionSelect.value = submitter.dataset.supcFinalAction;
		}
		if (!form.reportValidity()) {
			announce(config.strings.fixFields);
			return;
		}
		if (!(await validateWorkflow())) {
			setStep('validation');
			return;
		}
		announce(config.strings.submitting);
		try {
			const result = await run('submit');
			finishSubmission(result);
		} catch (error) {
			announce(config.strings.submitFailed);
			const details = error.data && error.data.details ? error.data.details : {};
			const uncertain = Boolean(
				session && session.native_reference_present && (
					session.reconciliation_required ||
					details.reconciliation_required ||
					!error.code ||
					error.code === 'supc_request_failed' ||
					error.code === 'supc_session_finalize_failed' ||
					error.code === 'supc_submission_ack_record_failed'
				)
			);
			if (uncertain) window.setTimeout(() => attemptReconciliation(false), 1000);
		}
	});

	window.addEventListener('online', () => {
		evaluateConnection();
		if (dirty && !busy) save(true);
	});
	window.addEventListener('offline', evaluateConnection);
	const connection = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
	if (connection && typeof connection.addEventListener === 'function') connection.addEventListener('change', evaluateConnection);

	window.addEventListener('beforeunload', (event) => {
		if (dirty || busy) {
			event.preventDefault();
			event.returnValue = '';
		}
	});

	initRichEditors();
	evaluateConnection();
	setStep('compose');
	resumePromise = resumeSession().finally(() => {
		resumePromise = null;
		updateDraftPreview();
	});
}());
