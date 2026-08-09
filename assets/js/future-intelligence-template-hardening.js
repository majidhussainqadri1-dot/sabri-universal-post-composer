(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	const config = window.SUPCFuture;
	if (!root || !config) return;
	const form = root.querySelector('[data-supc-form]');
	const panel = root.querySelector('.supc-intel');
	const editor = root.querySelector('[data-supc-rte]');
	if (!form || !panel) return;

	const oldApply = panel.querySelector('[data-template-apply]');
	const tool = panel.querySelector('[data-tool="templates"]');
	if (!oldApply || !tool) return;
	const apply = oldApply.cloneNode(true);
	oldApply.replaceWith(apply);

	const MAX_TEMPLATE_FIELDS = 128;
	const MAX_TEXT_LENGTH = 131072;
	const protectedField = (field) => {
		const name = String(field && field.name || '');
		return !field || !name || field.dataset.fieldType === 'opaque_reference' || field.dataset.privacy === 'sensitive' || /^(?:native_reference|publication_action)$/i.test(name) || /(?:consent|privacy_confirm|medical_disclaimer_confirm|copyright_declaration|rights_declaration|verification|capability|moderation|status|guardian|credential|identity_evidence|author_id|effective_author)/i.test(name);
	};
	const snapshot = () => {
		const out = {};
		let count = 0;
		form.querySelectorAll('[data-supc-field]').forEach((field) => {
			if (!field.name || protectedField(field) || count >= MAX_TEMPLATE_FIELDS) return;
			count += 1;
			if (field.dataset.fieldType === 'checkbox') out[field.name] = field.checked;
			else if (field.dataset.fieldType === 'multiselect') out[field.name] = Array.from(field.selectedOptions || []).slice(0, 128).map((option) => String(option.value).slice(0, 1024));
			else out[field.name] = String(field.value == null ? '' : field.value).slice(0, MAX_TEXT_LENGTH);
		});
		return out;
	};
	const request = async () => {
		const response = await fetch(String(config.restRoot || '').replace(/\/+$/, '') + '/future/invoke', {
			method: 'POST', credentials: 'same-origin', cache: 'no-store',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify({ adapter_key: String(config.adapter || root.dataset.adapter || ''), capability: 'template_library', session_uuid: new URL(window.location.href).searchParams.get('session') || '', payload: { action: 'recommended', current: snapshot() } })
		});
		const data = await response.json().catch(() => ({}));
		if (!response.ok) throw new Error(data.message || 'Template request failed.');
		return data;
	};
	const setResult = (message, state) => {
		const node = panel.querySelector('[data-future-result="templates"]');
		if (!node) return;
		node.textContent = String(message || '');
		node.dataset.status = state || 'ready';
	};
	const setValue = (field, value) => {
		if (protectedField(field)) return false;
		if (field.dataset.fieldType === 'checkbox') return false;
		if (field.dataset.fieldType === 'multiselect') {
			if (!Array.isArray(value) || value.length > 128) return false;
			const allowed = new Set(Array.from(field.options || []).map((option) => option.value));
			const candidates = value.map(String);
			if (candidates.some((item) => item.length > 1024 || !allowed.has(item))) return false;
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
		const limit = field.maxLength && field.maxLength > 0 ? Math.min(field.maxLength, MAX_TEXT_LENGTH) : MAX_TEXT_LENGTH;
		if (candidate.length > limit) return false;
		field.value = candidate;
		return true;
	};

	const syncAvailability = () => { apply.disabled = Boolean(tool.disabled); };
	new MutationObserver(syncAvailability).observe(tool, { attributes: true, attributeFilter: ['disabled'] });
	tool.addEventListener('click', () => window.setTimeout(syncAvailability, 0));
	syncAvailability();

	apply.addEventListener('click', async () => {
		apply.disabled = true;
		try {
			const response = await request();
			const returned = response && response.result ? response.result : {};
			const fields = returned.template && returned.template.fields ? returned.template.fields : returned.fields;
			if (!fields || typeof fields !== 'object' || Array.isArray(fields) || Object.keys(fields).length > MAX_TEMPLATE_FIELDS) throw new Error('Provider did not return an applicable bounded field template.');
			let applied = 0;
			form.querySelectorAll('[data-supc-field]').forEach((field) => {
				if (protectedField(field) || !Object.prototype.hasOwnProperty.call(fields, field.name)) return;
				const occupied = field.dataset.fieldType === 'multiselect' ? (field.selectedOptions && field.selectedOptions.length > 0) : String(field.value || '').trim() !== '';
				if (occupied) return;
				if (setValue(field, fields[field.name])) {
					if (field.matches('[data-supc-rte-source]') && editor) editor.textContent = field.value;
					applied += 1;
				}
			});
			form.dispatchEvent(new Event('input', { bubbles: true }));
			setResult(applied ? applied + ' safe empty field(s) populated by explicit human action. Authority, identity, consent, privacy, moderation and opaque reference fields were excluded.' : 'No safe empty fields were eligible for template application.', applied ? 'ready' : 'warning');
		} catch (error) {
			setResult(error.message || 'Template failed.', 'error');
		} finally {
			syncAvailability();
		}
	});
}());
