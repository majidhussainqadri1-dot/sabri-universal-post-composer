(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	const config = window.SUPCFuture;
	if (!root || !config) return;
	const form = root.querySelector('[data-supc-form]');
	const panel = root.querySelector('.supc-intel');
	const editor = root.querySelector('[data-supc-rte]');
	if (!form || !panel) return;

	const restRoot = String(config.restRoot || '').replace(/\/+$/, '');
	const capabilities = new Set();
	const MAX_PROVIDER_FIELDS = 128;
	const MAX_FIELD_LENGTH = 131072;
	let remoteFields = null;
	let conflictState = null;

	const adapterKey = () => String(config.adapter || root.dataset.adapter || '');
	const sessionUuid = () => new URL(window.location.href).searchParams.get('session') || '';
	const nativeReference = () => {
		const field = form.querySelector('[name="native_reference"]');
		return field ? String(field.value || '').trim().slice(0, 256) : '';
	};
	const protectedField = (field) => {
		const name = String(field && field.name || '');
		return !field || !name || field.dataset.privacy === 'sensitive' || /^(?:publication_action)$/i.test(name) || /(?:consent|privacy_confirm|medical_disclaimer_confirm|copyright_declaration|rights_declaration|verification|capability|moderation|status|guardian|credential|identity_evidence|author_id|effective_author)/i.test(name);
	};
	const providerSnapshot = () => {
		const out = {};
		form.querySelectorAll('[data-supc-field]').forEach((field) => {
			if (Object.keys(out).length >= MAX_PROVIDER_FIELDS) return;
			if (!field.name || field.name === 'native_reference' || field.dataset.fieldType === 'opaque_reference' || protectedField(field)) return;
			if (field.dataset.fieldType === 'checkbox') out[field.name] = Boolean(field.checked);
			else if (field.dataset.fieldType === 'multiselect') out[field.name] = Array.from(field.selectedOptions || []).map((option) => String(option.value).slice(0, 512)).slice(0, 128);
			else out[field.name] = String(field.value == null ? '' : field.value).slice(0, MAX_FIELD_LENGTH);
		});
		const reference = nativeReference();
		if (reference) out.native_reference = reference;
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
		if (!response.ok) throw new Error(data.message || 'Capability request failed');
		return data;
	};
	const invoke = (capability, payload) => request('/future/invoke', 'POST', {
		adapter_key: adapterKey(),
		capability,
		session_uuid: sessionUuid(),
		payload
	});
	const resultPayload = (response) => response && response.result && typeof response.result === 'object' ? response.result : {};
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
	const capabilityAllowed = (name) => capabilities.has(name);

	const safeAssign = (field, value) => {
		if (protectedField(field) || field.name === 'native_reference' || field.dataset.fieldType === 'opaque_reference') return false;
		if (field.dataset.fieldType === 'checkbox') {
			if (typeof value !== 'boolean') return false;
			field.checked = value;
			return true;
		}
		if (field.dataset.fieldType === 'multiselect') {
			if (!Array.isArray(value) || value.length > 128) return false;
			const allowed = new Set(Array.from(field.options || []).map((option) => option.value));
			const candidates = value.map(String);
			if (candidates.some((item) => item.length > 512 || !allowed.has(item))) return false;
			const selected = new Set(candidates);
			Array.from(field.options || []).forEach((option) => { option.selected = selected.has(option.value); });
			return true;
		}
		if (field.tagName === 'SELECT') {
			const candidate = String(value == null ? '' : value);
			if (!Array.from(field.options || []).some((option) => option.value === candidate)) return false;
			field.value = candidate;
			return true;
		}
		if (typeof value !== 'string' && typeof value !== 'number') return false;
		const candidate = String(value);
		const limit = field.maxLength && field.maxLength > 0 ? Math.min(field.maxLength, MAX_FIELD_LENGTH) : MAX_FIELD_LENGTH;
		if (candidate.length > limit) return false;
		field.value = candidate;
		if (field.matches('[data-supc-rte-source]') && editor) editor.textContent = field.value;
		return true;
	};

	const remoteEnvelopeIsSafe = (fields) => {
		if (!fields || typeof fields !== 'object' || Array.isArray(fields) || Object.keys(fields).length > MAX_PROVIDER_FIELDS) return false;
		return Object.keys(fields).every((name) => {
			const field = form.querySelector('[data-supc-field][name="' + CSS.escape(String(name)) + '"]');
			if (!field || protectedField(field) || field.name === 'native_reference' || field.dataset.fieldType === 'opaque_reference') return false;
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

	const diff = replaceButton('[data-tool="diff"]');
	if (diff) diff.addEventListener('click', async () => {
		if (!capabilityAllowed('semantic_diff')) return setResult('diff', 'Authorized provider unavailable.', 'unavailable');
		diff.disabled = true;
		try {
			const response = await invoke('semantic_diff', { action: 'compare_current', current: providerSnapshot() });
			setResult('diff', JSON.stringify(resultPayload(response), null, 2), 'ready');
		} catch (error) { setResult('diff', error.message || 'Semantic diff failed.', 'error'); }
		finally { diff.disabled = false; }
	});

	const impact = replaceButton('[data-tool="impact"]');
	if (impact) impact.addEventListener('click', async () => {
		if (!capabilityAllowed('publication_impact')) return setResult('impact', 'Authorized provider unavailable.', 'unavailable');
		const action = form.querySelector('[data-supc-publication-action]');
		impact.disabled = true;
		try {
			const response = await invoke('publication_impact', { action: action ? String(action.value || '') : '', fields: providerSnapshot() });
			setResult('impact', JSON.stringify(resultPayload(response), null, 2), 'ready');
		} catch (error) { setResult('impact', error.message || 'Publication impact simulation failed.', 'error'); }
		finally { impact.disabled = false; }
	});

	const pull = replaceButton('[data-collaboration-pull]');
	const apply = replaceButton('[data-collaboration-apply]');
	if (apply) apply.disabled = true;
	if (pull) pull.addEventListener('click', async () => {
		if (!capabilityAllowed('collaboration')) return setResult('collaboration', 'Authorized provider unavailable.', 'unavailable');
		pull.disabled = true;
		remoteFields = null;
		if (apply) apply.disabled = true;
		try {
			const response = await invoke('collaboration', { action: 'pull', current: providerSnapshot() });
			const result = resultPayload(response);
			const candidate = result.fields && typeof result.fields === 'object' && !Array.isArray(result.fields) ? result.fields : null;
			remoteFields = remoteEnvelopeIsSafe(candidate) ? candidate : null;
			if (apply) apply.disabled = !remoteFields;
			setResult('collaboration', remoteFields ? 'A complete bounded remote update is available. Authority, identity, consent, privacy, moderation and opaque-reference fields are excluded from provider egress and remote application.' : 'No complete safe remote field update was returned; partial/truncated application is prohibited.', remoteFields ? 'ready' : 'warning');
		} catch (error) { setResult('collaboration', error.message || 'Collaboration pull failed.', 'error'); }
		finally { pull.disabled = false; }
	});
	if (apply) apply.addEventListener('click', () => {
		if (!remoteFields || !remoteEnvelopeIsSafe(remoteFields)) {
			remoteFields = null;
			apply.disabled = true;
			return setResult('collaboration', 'Remote update no longer matches the current safe schema and was discarded.', 'blocked');
		}
		const assignments = [];
		form.querySelectorAll('[data-supc-field]').forEach((field) => {
			if (Object.prototype.hasOwnProperty.call(remoteFields, field.name)) assignments.push([field, remoteFields[field.name]]);
		});
		if (assignments.some(([field, value]) => !remoteEnvelopeIsSafe({ [field.name]: value }))) {
			remoteFields = null;
			apply.disabled = true;
			return setResult('collaboration', 'Remote update failed final validation and was not partially applied.', 'blocked');
		}
		let count = 0;
		assignments.forEach(([field, value]) => { if (safeAssign(field, value)) count += 1; });
		remoteFields = null;
		apply.disabled = true;
		form.dispatchEvent(new Event('input', { bubbles: true }));
		setResult('collaboration', count === assignments.length ? count + ' bounded remote field update(s) applied by explicit human action. Review and save to the native owner.' : 'Remote update could not be applied completely; review the current draft before saving.', count === assignments.length ? 'ready' : 'warning');
	});

	const inspect = replaceButton('[data-tool="conflict"]');
	if (inspect) inspect.addEventListener('click', async () => {
		if (!capabilityAllowed('conflict_merge')) return setResult('conflict', 'Authorized provider unavailable.', 'unavailable');
		inspect.disabled = true;
		conflictState = null;
		try {
			const response = await invoke('conflict_merge', { action: 'inspect', current: providerSnapshot() });
			conflictState = resultPayload(response);
			setResult('conflict', JSON.stringify(conflictState, null, 2), 'ready');
		} catch (error) { setResult('conflict', error.message || 'Conflict inspection failed.', 'error'); }
		finally { inspect.disabled = false; }
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
			button.disabled = true;
			try {
				const response = await invoke('conflict_merge', {
					action: 'resolve',
					resolution,
					conflict_token: String(conflictState.conflict_token || '').slice(0, 512),
					current: resolution === 'accept_native' ? {} : providerSnapshot()
				});
				setResult('conflict', 'Resolution sent explicitly: ' + resolution + '\n' + JSON.stringify(resultPayload(response), null, 2), 'ready');
				conflictState = null;
			} catch (error) { setResult('conflict', error.message || 'Conflict resolution failed.', 'error'); }
			finally { button.disabled = false; }
		});
	});

	request('/future/capabilities/' + encodeURIComponent(adapterKey()), 'GET').then((data) => {
		(data.bridge_capabilities || []).forEach((item) => capabilities.add(String(item)));
		[['semantic_diff', diff], ['publication_impact', impact], ['collaboration', pull], ['conflict_merge', inspect]].forEach(([name, button]) => {
			if (button && !capabilityAllowed(name)) button.disabled = true;
		});
	}).catch(() => {
		[diff, impact, pull, apply, inspect].forEach((button) => { if (button) button.disabled = true; });
	});
}());
