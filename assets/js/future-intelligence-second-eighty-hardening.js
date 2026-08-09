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

	const restRoot = String(config.restRoot || '').replace(/\/+$/, '');
	const capabilities = new Set();
	const MAX_PROVIDER_FIELDS = 128;
	const MAX_FIELD_LENGTH = 131072;
	const MAX_PROVIDER_TEXT = 60000;
	const MAX_TITLE_LENGTH = 1000;
	let collaborationJoined = false;
	let remoteFields = null;
	let conflictState = null;
	let voiceRecognition = null;
	let voiceTarget = null;

	const adapterKey = () => String(config.adapter || root.dataset.adapter || '');
	const sessionUuid = () => String(new URL(window.location.href).searchParams.get('session') || '').toLowerCase();
	const protectedField = (field) => {
		const name = String(field && field.name || '');
		return !field || !name || field.dataset.privacy === 'sensitive' || field.dataset.fieldType === 'opaque_reference' || /^(?:native_reference|publication_action)$/i.test(name) || /(?:consent|privacy_confirm|medical_disclaimer_confirm|copyright_declaration|rights_declaration|verification|capability|moderation|status|guardian|credential|identity_evidence|author_id|effective_author|patient_id|medical_record)/i.test(name);
	};
	const isSensitiveWorkflow = () => String(config.adapterPrivacyClassification || '').toLowerCase() === 'sensitive' || Boolean(root.querySelector('[data-supc-field][data-privacy="sensitive"], [data-field-key*="patient"], [data-field-key*="consent"], [data-field-key*="clinical_case"], [data-field-key*="successful_case"], [data-field-key*="guardian"], [data-field-key*="credential"]'));
	const setResult = (tool, message, state) => {
		const node = panel.querySelector('[data-future-result="' + tool + '"]');
		if (!node) return;
		node.textContent = String(message || '');
		node.dataset.status = state || 'ready';
	};
	const replaceButton = (selector) => {
		const old = panel.querySelector(selector);
		if (!old) return null;
		const fresh = old.cloneNode(true);
		old.replaceWith(fresh);
		return fresh;
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
		if (!capabilities.has(capability)) throw new Error('Authorized provider unavailable.');
		return request('/future/invoke', 'POST', {
			adapter_key: adapterKey(),
			capability,
			session_uuid: sessionUuid(),
			payload: payload || {}
		});
	};
	const resultPayload = (response) => response && response.result && typeof response.result === 'object' ? response.result : {};
	const resultText = (response) => {
		const value = response && Object.prototype.hasOwnProperty.call(response, 'result') ? response.result : response;
		if (typeof value === 'string') return value;
		if (value && typeof value.text === 'string') return value.text;
		if (value && typeof value.summary === 'string') return value.summary;
		if (value && typeof value.content === 'string') return value.content;
		return '';
	};
	const completeText = () => {
		if (editor) return String(editor.textContent || '').replace(/\s+/g, ' ').trim();
		const field = form.querySelector('[name="content"], [name="body"], textarea[data-supc-field]');
		return field ? String(field.value || '').replace(/\s+/g, ' ').trim() : '';
	};
	const completeTitle = () => {
		const field = form.querySelector('[name="title"]');
		return field ? String(field.value || '').trim() : '';
	};
	const boundedWritingPayload = () => {
		const title = completeTitle();
		const content = completeText();
		if (title.length > MAX_TITLE_LENGTH || content.length > MAX_PROVIDER_TEXT) return null;
		return { title, content };
	};
	const boundedSnapshot = () => {
		const out = {};
		const fields = Array.from(form.querySelectorAll('[data-supc-field]')).filter((field) => !protectedField(field));
		if (fields.length > MAX_PROVIDER_FIELDS) return null;
		for (const field of fields) {
			if (field.dataset.fieldType === 'checkbox') {
				out[field.name] = Boolean(field.checked);
				continue;
			}
			if (field.dataset.fieldType === 'multiselect') {
				const values = Array.from(field.selectedOptions || []).map((option) => String(option.value));
				if (values.length > 128 || values.some((value) => value.length > 512)) return null;
				out[field.name] = values;
				continue;
			}
			const value = String(field.value == null ? '' : field.value);
			const limit = field.maxLength && field.maxLength > 0 ? Math.min(field.maxLength, MAX_FIELD_LENGTH) : MAX_FIELD_LENGTH;
			if (value.length > limit) return null;
			out[field.name] = value;
		}
		return out;
	};
	const requireSnapshot = (tool) => {
		const snapshot = boundedSnapshot();
		if (snapshot) return snapshot;
		setResult(tool, 'This draft exceeds the complete bounded provider envelope. Nothing was silently truncated or sent. Reduce the relevant content or use the authoritative native workflow.', 'blocked');
		return null;
	};

	const generationSpecs = [
		['ai', 'ai_copilot', () => {
			const base = boundedWritingPayload();
			if (!base) return null;
			const task = panel.querySelector('[data-ai-task]');
			return Object.assign({ task: task ? String(task.value || '') : 'outline' }, base);
		}],
		['terminology', 'medical_terminology', () => boundedWritingPayload()],
		['derivative', 'cross_format_derivative', () => {
			const base = boundedWritingPayload();
			if (!base) return null;
			const target = panel.querySelector('[data-derivative-target]');
			return Object.assign({ target: target ? String(target.value || '') : '' }, base);
		}]
	];
	generationSpecs.forEach(([tool, capability, payloadFactory]) => {
		const button = replaceButton('[data-tool="' + tool + '"]');
		if (!button) return;
		button.addEventListener('click', async () => {
			const payload = payloadFactory();
			if (!payload) {
				setResult(tool, 'The complete draft is larger than the bounded provider envelope; no partial/truncated advisory request was sent.', 'blocked');
				return;
			}
			button.disabled = true;
			setResult(tool, 'Working…', 'working');
			try {
				const response = await invoke(capability, payload);
				const text = resultText(response);
				setResult(tool, text || JSON.stringify(resultPayload(response), null, 2), 'ready');
			} catch (error) { setResult(tool, error.message || 'Advisory request failed.', 'error'); }
			finally { button.disabled = !capabilities.has(capability); }
		});
	});

	const diff = replaceButton('[data-tool="diff"]');
	if (diff) diff.addEventListener('click', async () => {
		const current = requireSnapshot('diff');
		if (!current) return;
		diff.disabled = true;
		try {
			const response = await invoke('semantic_diff', { action: 'compare_current', current });
			setResult('diff', JSON.stringify(resultPayload(response), null, 2), 'ready');
		} catch (error) { setResult('diff', error.message || 'Semantic diff failed.', 'error'); }
		finally { diff.disabled = !capabilities.has('semantic_diff'); }
	});

	const impact = replaceButton('[data-tool="impact"]');
	if (impact) impact.addEventListener('click', async () => {
		const fields = requireSnapshot('impact');
		if (!fields) return;
		const action = form.querySelector('[data-supc-publication-action]');
		impact.disabled = true;
		try {
			const response = await invoke('publication_impact', { action: action ? String(action.value || '') : '', fields });
			setResult('impact', JSON.stringify(resultPayload(response), null, 2), 'ready');
		} catch (error) { setResult('impact', error.message || 'Publication impact simulation failed.', 'error'); }
		finally { impact.disabled = !capabilities.has('publication_impact'); }
	});

	const remoteEnvelopeIsSafe = (fields) => {
		if (!fields || typeof fields !== 'object' || Array.isArray(fields) || Object.keys(fields).length > MAX_PROVIDER_FIELDS) return false;
		return Object.keys(fields).every((name) => {
			const field = Array.from(form.querySelectorAll('[data-supc-field]')).find((candidate) => candidate.name === name);
			if (!field || protectedField(field)) return false;
			const value = fields[name];
			if (field.dataset.fieldType === 'checkbox') return typeof value === 'boolean';
			if (field.dataset.fieldType === 'multiselect') {
				if (!Array.isArray(value) || value.length > 128) return false;
				const allowed = new Set(Array.from(field.options || []).map((option) => option.value));
				return value.every((item) => String(item).length <= 512 && allowed.has(String(item)));
			}
			if (field.tagName === 'SELECT') return Array.from(field.options || []).some((option) => option.value === String(value == null ? '' : value));
			if (typeof value !== 'string' && typeof value !== 'number') return false;
			const limit = field.maxLength && field.maxLength > 0 ? Math.min(field.maxLength, MAX_FIELD_LENGTH) : MAX_FIELD_LENGTH;
			return String(value).length <= limit;
		});
	};
	const prepareRemoteAssignments = (fields, emptyOnly) => {
		if (!remoteEnvelopeIsSafe(fields)) return null;
		const prepared = [];
		for (const name of Object.keys(fields)) {
			const field = Array.from(form.querySelectorAll('[data-supc-field]')).find((candidate) => candidate.name === name);
			if (!field || protectedField(field)) return null;
			if (emptyOnly && ((field.dataset.fieldType === 'checkbox' && field.checked) || (field.dataset.fieldType !== 'checkbox' && String(field.value || '').trim()))) continue;
			const value = fields[name];
			if (field.dataset.fieldType === 'checkbox') prepared.push(() => { field.checked = value; });
			else if (field.dataset.fieldType === 'multiselect') {
				const selected = new Set(value.map(String));
				prepared.push(() => Array.from(field.options || []).forEach((option) => { option.selected = selected.has(option.value); }));
			} else prepared.push(() => { field.value = String(value == null ? '' : value); });
		}
		return prepared;
	};

	const collaborationDetails = panel.querySelector('[data-future-tool="collaboration"]');
	if (collaborationDetails) {
		collaborationDetails.addEventListener('toggle', async () => {
			if (!collaborationDetails.open || collaborationJoined || !capabilities.has('collaboration')) return;
			try {
				await invoke('collaboration', { action: 'join', user_initiated: true, cursor: { collapsed: true } });
				collaborationJoined = true;
				setResult('collaboration', 'Collaboration joined only after your explicit opening of this tool.', 'ready');
			} catch (error) { setResult('collaboration', error.message || 'Collaboration join failed.', 'error'); }
		});
	}
	const pull = replaceButton('[data-collaboration-pull]');
	const applyRemote = replaceButton('[data-collaboration-apply]');
	if (applyRemote) applyRemote.disabled = true;
	if (pull) pull.addEventListener('click', async () => {
		const current = requireSnapshot('collaboration');
		if (!current) return;
		remoteFields = null;
		if (applyRemote) applyRemote.disabled = true;
		pull.disabled = true;
		try {
			const response = await invoke('collaboration', { action: 'pull', current });
			const candidate = resultPayload(response).fields;
			remoteFields = remoteEnvelopeIsSafe(candidate) ? candidate : null;
			if (applyRemote) applyRemote.disabled = !remoteFields;
			setResult('collaboration', remoteFields ? 'A complete bounded remote update is available for explicit review/application.' : 'No complete safe remote field envelope was returned; partial application is prohibited.', remoteFields ? 'ready' : 'warning');
		} catch (error) { setResult('collaboration', error.message || 'Collaboration pull failed.', 'error'); }
		finally { pull.disabled = !capabilities.has('collaboration'); }
	});
	if (applyRemote) applyRemote.addEventListener('click', () => {
		const prepared = prepareRemoteAssignments(remoteFields, false);
		if (!prepared) {
			remoteFields = null;
			applyRemote.disabled = true;
			setResult('collaboration', 'Remote update failed final all-or-nothing validation and was not applied.', 'blocked');
			return;
		}
		prepared.forEach((assignment) => assignment());
		remoteFields = null;
		applyRemote.disabled = true;
		form.dispatchEvent(new Event('input', { bubbles: true }));
		setResult('collaboration', prepared.length + ' bounded remote field update(s) applied atomically by explicit human action. Review before saving.', 'ready');
	});

	const inspect = replaceButton('[data-tool="conflict"]');
	if (inspect) inspect.addEventListener('click', async () => {
		const current = requireSnapshot('conflict');
		if (!current) return;
		conflictState = null;
		inspect.disabled = true;
		try {
			const response = await invoke('conflict_merge', { action: 'inspect', current });
			const result = resultPayload(response);
			const token = String(result.conflict_token || '');
			if (!token || token.length > 512) throw new Error('Provider did not return a bounded authoritative conflict token.');
			conflictState = result;
			setResult('conflict', JSON.stringify(result, null, 2), 'ready');
		} catch (error) { setResult('conflict', error.message || 'Conflict inspection failed.', 'error'); }
		finally { inspect.disabled = !capabilities.has('conflict_merge'); }
	});
	[
		['[data-conflict-keep-current]', 'keep_current'],
		['[data-conflict-accept-native]', 'accept_native'],
		['[data-conflict-manual]', 'manual']
	].forEach(([selector, resolution]) => {
		const button = replaceButton(selector);
		if (!button) return;
		button.addEventListener('click', async () => {
			if (!conflictState) return setResult('conflict', 'Inspect the authoritative conflict before resolving it.', 'warning');
			const token = String(conflictState.conflict_token || '');
			const current = resolution === 'accept_native' ? {} : requireSnapshot('conflict');
			if (resolution !== 'accept_native' && !current) return;
			button.disabled = true;
			try {
				const response = await invoke('conflict_merge', { action: 'resolve', resolution, conflict_token: token, current });
				const result = resultPayload(response);
				const confirmed = (result.resolved === true || String(result.status || '').toLowerCase() === 'resolved')
					&& String(result.conflict_token || '') === token
					&& (!result.resolution || String(result.resolution) === resolution);
				if (!confirmed) {
					setResult('conflict', 'The native owner did not positively confirm this exact conflict token/resolution. The conflict remains open locally.', 'warning');
					return;
				}
				conflictState = null;
				setResult('conflict', 'Native owner confirmed resolution: ' + resolution + '.', 'ready');
			} catch (error) { setResult('conflict', error.message || 'Conflict resolution failed.', 'error'); }
			finally { button.disabled = false; }
		});
	});

	const templateApply = replaceButton('[data-template-apply]');
	if (templateApply) templateApply.addEventListener('click', async () => {
		const current = requireSnapshot('templates');
		if (!current) return;
		templateApply.disabled = true;
		try {
			const response = await invoke('template_library', { action: 'recommended', current });
			const result = resultPayload(response);
			const fields = result.template && result.template.fields ? result.template.fields : result.fields;
			const prepared = prepareRemoteAssignments(fields, true);
			if (!prepared) throw new Error('Provider template failed complete current-schema validation; no field was changed.');
			prepared.forEach((assignment) => assignment());
			form.dispatchEvent(new Event('input', { bubbles: true }));
			setResult('templates', prepared.length + ' empty field(s) filled atomically from the approved template. Review before saving.', 'ready');
		} catch (error) { setResult('templates', error.message || 'Template application failed.', 'error'); }
		finally { templateApply.disabled = !capabilities.has('template_library'); }
	});

	const configureSuggestionApply = (tool) => {
		const oldApply = panel.querySelector('[data-future-tool="' + tool + '"] [data-explicit-suggestion-apply]');
		const generator = panel.querySelector('[data-tool="' + tool + '"]');
		const result = panel.querySelector('[data-future-result="' + tool + '"]');
		if (!oldApply || !generator || !result) return;
		const apply = oldApply.cloneNode(true);
		oldApply.replaceWith(apply);
		let consumed = true;
		const refresh = () => { apply.disabled = consumed || result.dataset.status !== 'ready' || !String(result.textContent || '').trim(); };
		generator.addEventListener('click', () => { consumed = false; apply.disabled = true; });
		new MutationObserver(refresh).observe(result, { childList: true, characterData: true, subtree: true, attributes: true, attributeFilter: ['data-status'] });
		apply.addEventListener('click', () => {
			if (consumed || result.dataset.status !== 'ready') return;
			const text = String(result.textContent || '').trim();
			if (!text) return;
			consumed = true;
			apply.disabled = true;
			if (editor) {
				editor.focus();
				document.execCommand('insertText', false, text);
				editor.dispatchEvent(new Event('input', { bubbles: true }));
			} else if (source) {
				const start = typeof source.selectionStart === 'number' ? source.selectionStart : source.value.length;
				const end = typeof source.selectionEnd === 'number' ? source.selectionEnd : start;
				source.value = source.value.slice(0, start) + text + source.value.slice(end);
				source.dispatchEvent(new Event('input', { bubbles: true }));
			}
			setResult(tool, 'Suggestion inserted once by explicit human action. Generate a new advisory before another insertion.', 'ready');
		});
		refresh();
	};
	configureSuggestionApply('ai');
	configureSuggestionApply('derivative');

	const privacyButton = replaceButton('[data-local-tool="privacy"]');
	if (privacyButton) privacyButton.addEventListener('click', () => {
		const values = [];
		form.querySelectorAll('[data-supc-field]').forEach((field) => {
			if (field.dataset.fieldType === 'checkbox') return;
			if (field.dataset.fieldType === 'multiselect') values.push(Array.from(field.selectedOptions || []).map((option) => option.value).join(' '));
			else values.push(String(field.value || ''));
		});
		const text = values.join('\n');
		const patterns = {
			email: /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/gi,
			phone: /(?:\+?\d[\d\s().-]{8,}\d)/g,
			cnic_or_national_id: /\b\d{5}-?\d{7}-?\d\b/g,
			passport_like: /\b(?:passport|national\s+id|cnic)\s*[:#-]?\s*[A-Z0-9-]{4,}\b/gi,
			medical_record: /\b(?:medical\s+record|mrn|patient\s+id|registration\s+number)\s*[:#-]?\s*[A-Z0-9-]{3,}\b/gi,
			date_of_birth: /\b(?:DOB|date of birth|تاریخ پیدائش)\s*[:\-]?\s*\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}\b/gi,
			gps_coordinates: /\b-?\d{1,2}\.\d{4,}\s*,\s*-?\d{1,3}\.\d{4,}\b/g,
			explicit_address: /\b(?:address|street|گھر\s*کا\s*پتہ|پتہ)\s*[:\-]\s*[^\n]{8,}/gi
		};
		const findings = {};
		Object.keys(patterns).forEach((key) => { findings[key] = (text.match(patterns[key]) || []).length; });
		const total = Object.values(findings).reduce((sum, count) => sum + count, 0);
		setResult('privacy', (total ? 'Potential identifying data was detected. This is advisory; the authoritative Patient Case privacy/consent workflow still decides publication.' : 'No configured identifier pattern was detected. This is not a guarantee of anonymity.') + '\n' + JSON.stringify(findings, null, 2), total ? 'warning' : 'ready');
	});

	const voiceButton = replaceButton('[data-local-tool="voice"]');
	const writableVoiceTarget = (node) => Boolean(node && node.matches && node.matches('[data-supc-rte], textarea[data-supc-field], input[type="text"][data-supc-field], input[type="search"][data-supc-field]') && !protectedField(node));
	root.addEventListener('focusin', (event) => { if (writableVoiceTarget(event.target)) voiceTarget = event.target; });
	const voiceAllowed = () => !isSensitiveWorkflow() || Boolean(config.privacy && config.privacy.sensitiveVoiceAllowed);
	const stopVoice = (message, state) => {
		if (voiceRecognition) {
			try { voiceRecognition.stop(); } catch (error) { /* Browser already stopped. */ }
		}
		voiceRecognition = null;
		if (voiceButton) voiceButton.setAttribute('aria-pressed', 'false');
		if (message) setResult('voice', message, state || 'ready');
	};
	if (voiceButton) {
		voiceButton.setAttribute('aria-pressed', 'false');
		voiceButton.addEventListener('click', () => {
			if (voiceRecognition) return stopVoice('Dictation stopped. File 22 retained no separate audio/transcript record.', 'ready');
			if (!voiceAllowed()) return setResult('voice', 'Voice dictation is blocked for this sensitive workflow unless the governing privacy owner explicitly opts in.', 'blocked');
			const Voice = window.SpeechRecognition || window.webkitSpeechRecognition;
			if (!Voice) return setResult('voice', 'Speech recognition is not available in this browser.', 'blocked');
			const target = writableVoiceTarget(voiceTarget) ? voiceTarget : (writableVoiceTarget(editor) ? editor : form.querySelector('textarea[data-supc-field], input[type="text"][data-supc-field]'));
			if (!target || protectedField(target)) return setResult('voice', 'Focus a permitted text field before starting dictation.', 'warning');
			voiceTarget = target;
			voiceRecognition = new Voice();
			voiceRecognition.continuous = true;
			voiceRecognition.interimResults = false;
			voiceRecognition.lang = String(config.locale || document.documentElement.lang || 'en-US').replace('_', '-');
			voiceRecognition.onresult = (event) => {
				if (!voiceAllowed() || !writableVoiceTarget(voiceTarget)) return stopVoice('Dictation stopped before insertion because the workflow/target is no longer permitted.', 'blocked');
				let text = '';
				for (let index = event.resultIndex; index < event.results.length; index += 1) if (event.results[index].isFinal) text += event.results[index][0].transcript + ' ';
				if (!text) return;
				if (voiceTarget.matches('[data-supc-rte]')) {
					const max = source && source.maxLength > 0 ? source.maxLength : MAX_FIELD_LENGTH;
					if (String(voiceTarget.textContent || '').length + text.length > max) return stopVoice('Dictation stopped because the complete transcript would exceed this field limit; no partial transcript was inserted.', 'warning');
					voiceTarget.focus();
					document.execCommand('insertText', false, text);
					voiceTarget.dispatchEvent(new Event('input', { bubbles: true }));
					return;
				}
				const value = String(voiceTarget.value || '');
				const start = typeof voiceTarget.selectionStart === 'number' ? voiceTarget.selectionStart : value.length;
				const end = typeof voiceTarget.selectionEnd === 'number' ? voiceTarget.selectionEnd : start;
				const next = value.slice(0, start) + text + value.slice(end);
				const max = voiceTarget.maxLength > 0 ? voiceTarget.maxLength : MAX_FIELD_LENGTH;
				if (next.length > max) return stopVoice('Dictation stopped because the complete transcript would exceed this field limit; no partial transcript was inserted.', 'warning');
				voiceTarget.value = next;
				voiceTarget.dispatchEvent(new Event('input', { bubbles: true }));
			};
			voiceRecognition.onerror = (event) => setResult('voice', 'Dictation error: ' + String(event.error || 'unknown'), 'error');
			voiceRecognition.onend = () => { voiceRecognition = null; voiceButton.setAttribute('aria-pressed', 'false'); };
			try {
				voiceRecognition.start();
				voiceButton.setAttribute('aria-pressed', 'true');
				setResult('voice', 'Listening into the selected permitted text field. Your browser/speech vendor may process audio; File 22 creates no separate audio/transcript record.', 'working');
			} catch (error) { stopVoice('Dictation could not be started.', 'error'); }
		});
	}
	window.addEventListener('pagehide', () => stopVoice('', 'ready'), { once: true });

	request('/future/capabilities/' + encodeURIComponent(adapterKey()), 'GET').then((data) => {
		(data.bridge_capabilities || []).forEach((item) => capabilities.add(String(item)));
		[
			['ai_copilot', panel.querySelector('[data-tool="ai"]')],
			['medical_terminology', panel.querySelector('[data-tool="terminology"]')],
			['cross_format_derivative', panel.querySelector('[data-tool="derivative"]')],
			['semantic_diff', diff],
			['publication_impact', impact],
			['collaboration', pull],
			['conflict_merge', inspect],
			['template_library', templateApply]
		].forEach(([name, button]) => { if (button) button.disabled = !capabilities.has(name); });
	}).catch(() => {
		panel.querySelectorAll('[data-tool]').forEach((button) => { button.disabled = true; });
	});
}());
