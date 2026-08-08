(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	const config = window.SUPCFuture;
	if (!root || !config) return;
	const form = root.querySelector('[data-supc-form]');
	const panel = root.querySelector('.supc-intel');
	const editor = root.querySelector('[data-supc-rte]');
	const source = root.querySelector('[data-supc-rte-source]');
	if (!form || !panel || !config.privacy) return;

	const enabledByPolicy = Boolean(config.privacy.auditedEncryptedRecovery);
	config.privacy.localEncryptedRecovery = false;

	const DB_NAME = 'supc-future-recovery-v2';
	const DB_VERSION = 1;
	const MAX_AGE_MS = 24 * 60 * 60 * 1000;
	const MAX_RECOVERY_BYTES = 262144;
	const MAX_RECOVERY_FIELDS = 128;
	const MAX_FIELD_BYTES = 131072;
	const MAX_MULTISELECT_ITEMS = 128;
	const MAX_OPTION_BYTES = 1024;
	const SESSION_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i;
	const REFERENCE_PATTERN = /^[A-Za-z0-9][A-Za-z0-9._:-]{0,254}$/;
	let timer = null;

	const encoder = typeof TextEncoder === 'function' ? new TextEncoder() : null;
	const byteLength = (value) => encoder ? encoder.encode(String(value == null ? '' : value)).byteLength : Number.POSITIVE_INFINITY;
	const isSensitive = () => String(config.adapterPrivacyClassification || '').toLowerCase() === 'sensitive' || Boolean(root.querySelector('[data-supc-field][data-privacy="sensitive"], .supc-workflow__field[data-privacy="sensitive"], [data-field-key*="patient"], [data-field-key*="consent"], [data-field-key*="clinical_case"], [data-field-key*="successful_case"], [data-field-key*="guardian"], [data-field-key*="credential"]'));
	const supported = () => Boolean(enabledByPolicy && window.isSecureContext && window.crypto && crypto.subtle && window.indexedDB && encoder && !isSensitive());
	const adapter = () => String(config.adapter || root.dataset.adapter || 'unknown');
	const session = () => {
		const value = new URL(window.location.href).searchParams.get('session') || '';
		return SESSION_PATTERN.test(value) ? value.toLowerCase() : '';
	};
	const nativeReference = () => {
		const field = form.querySelector('[name="native_reference"]');
		const value = field ? String(field.value || '').trim() : '';
		return REFERENCE_PATTERN.test(value) ? value : '';
	};
	const tabToken = () => {
		const state = history.state && typeof history.state === 'object' ? history.state : {};
		if (typeof state.supcRecoveryToken === 'string' && /^(?:[0-9a-f]{32}|[0-9a-f-]{36})$/i.test(state.supcRecoveryToken)) return state.supcRecoveryToken;
		const token = typeof crypto.randomUUID === 'function' ? crypto.randomUUID() : Array.from(crypto.getRandomValues(new Uint8Array(16))).map((value) => value.toString(16).padStart(2, '0')).join('');
		try { history.replaceState(Object.assign({}, state, { supcRecoveryToken: token }), document.title, window.location.href); } catch (error) { /* In-memory token remains valid for this page. */ }
		return token;
	};
	const stableTabToken = tabToken();
	const stableScope = session() || nativeReference() || stableTabToken;
	const scope = () => stableScope;
	const keyId = () => String(config.userId || 0) + ':' + adapter() + ':' + scope();
	const userPrefix = () => String(config.userId || 0) + ':';
	const protectedRecoveryField = (field) => {
		const name = String(field && field.name || '');
		return !field || !name || field.dataset.privacy === 'sensitive' || field.dataset.fieldType === 'opaque_reference' || /^(?:native_reference|publication_action)$/i.test(name) || /(?:consent|privacy_confirm|medical_disclaimer_confirm|copyright_declaration|rights_declaration|verification|capability|moderation|status|guardian|credential|identity_evidence)/i.test(name);
	};
	const fieldsContainSensitiveContent = (fields) => {
		const walk = (value, key, depth) => {
			if (depth > 8) return true;
			if (key && /(?:patient|consent|clinical|guardian|credential|identity|passport|cnic|phone|email|address|date_of_birth|dob|medical_record)/i.test(key)) return true;
			if (typeof value === 'string') {
				const sample = value.slice(0, MAX_FIELD_BYTES);
				return /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i.test(sample)
					|| /\b\d{5}-?\d{7}-?\d\b/.test(sample)
					|| /(?:\+?\d[\d\s().-]{8,}\d)/.test(sample)
					|| /\b(?:\d{1,3}\.){3}\d{1,3}\b/.test(sample)
					|| /\b(?:DOB|date of birth|تاریخ پیدائش)\s*[:\-]?\s*\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}\b/i.test(sample);
			}
			if (Array.isArray(value)) return value.some((item) => walk(item, key, depth + 1));
			if (value && typeof value === 'object') return Object.keys(value).some((childKey) => walk(value[childKey], childKey, depth + 1));
			return false;
		};
		return Boolean(fields && typeof fields === 'object' && walk(fields, '', 0));
	};
	const fieldsAreBounded = (fields) => {
		if (!fields || typeof fields !== 'object' || Array.isArray(fields) || Object.keys(fields).length > MAX_RECOVERY_FIELDS) return false;
		try {
			const encoded = JSON.stringify(fields);
			return typeof encoded === 'string' && byteLength(encoded) <= MAX_RECOVERY_BYTES;
		} catch (error) {
			return false;
		}
	};

	const openDb = () => new Promise((resolve, reject) => {
		const request = indexedDB.open(DB_NAME, DB_VERSION);
		request.onupgradeneeded = () => {
			const db = request.result;
			if (!db.objectStoreNames.contains('keys')) db.createObjectStore('keys');
			if (!db.objectStoreNames.contains('drafts')) db.createObjectStore('drafts');
		};
		request.onsuccess = () => resolve(request.result);
		request.onerror = () => reject(request.error);
	});
	const get = async (store, key) => { const db = await openDb(); return new Promise((resolve, reject) => { const tx = db.transaction(store, 'readonly'); const req = tx.objectStore(store).get(key); req.onsuccess = () => resolve(req.result); req.onerror = () => reject(req.error); }); };
	const put = async (store, key, value) => { const db = await openDb(); return new Promise((resolve, reject) => { const tx = db.transaction(store, 'readwrite'); tx.objectStore(store).put(value, key); tx.oncomplete = () => resolve(true); tx.onerror = () => reject(tx.error); }); };
	const del = async (store, key) => { const db = await openDb(); return new Promise((resolve, reject) => { const tx = db.transaction(store, 'readwrite'); tx.objectStore(store).delete(key); tx.oncomplete = () => resolve(true); tx.onerror = () => reject(tx.error); }); };
	const deletePair = async (id) => { await Promise.allSettled([del('drafts', id), del('keys', id)]); };

	const iterateKeys = async (store, visitor) => {
		const db = await openDb();
		return new Promise((resolve, reject) => {
			const tx = db.transaction(store, 'readwrite');
			const objectStore = tx.objectStore(store);
			const request = objectStore.openCursor();
			request.onsuccess = () => {
				const cursor = request.result;
				if (!cursor) return;
				if (visitor(String(cursor.key), cursor.value)) cursor.delete();
				cursor.continue();
			};
			tx.oncomplete = () => resolve(true);
			tx.onerror = () => reject(tx.error);
		});
	};

	const purgeExpiredAndForeign = async () => {
		const now = Date.now();
		const keep = userPrefix();
		const deleted = new Set();
		await iterateKeys('drafts', (id, value) => {
			const updated = value && typeof value.updated_at === 'string' ? Date.parse(value.updated_at) : 0;
			const remove = !id.startsWith(keep) || !updated || now - updated > MAX_AGE_MS;
			if (remove) deleted.add(id);
			return remove;
		});
		if (deleted.size) await iterateKeys('keys', (id) => deleted.has(id));
	};
	const purgeCurrent = async () => deletePair(keyId());
	const purgeCurrentUser = async () => {
		const prefix = userPrefix();
		await iterateKeys('drafts', (id) => id.startsWith(prefix));
		await iterateKeys('keys', (id) => id.startsWith(prefix));
	};

	// Return null rather than silently truncating. Recovery is optional, but when
	// it claims to be available it must represent the complete eligible envelope.
	const snapshot = () => {
		const fields = {};
		let eligibleCount = 0;
		let invalid = false;
		form.querySelectorAll('[data-supc-field]').forEach((field) => {
			if (protectedRecoveryField(field)) return;
			eligibleCount += 1;
			if (eligibleCount > MAX_RECOVERY_FIELDS) { invalid = true; return; }
			if (field.dataset.fieldType === 'checkbox') {
				fields[field.name] = field.checked;
				return;
			}
			if (field.dataset.fieldType === 'multiselect') {
				const values = Array.from(field.selectedOptions || []).map((option) => String(option.value));
				if (values.length > MAX_MULTISELECT_ITEMS || values.some((value) => byteLength(value) > MAX_OPTION_BYTES)) { invalid = true; return; }
				fields[field.name] = values;
				return;
			}
			const value = String(field.value == null ? '' : field.value);
			if (byteLength(value) > MAX_FIELD_BYTES) { invalid = true; return; }
			fields[field.name] = value;
		});
		return invalid ? null : fields;
	};
	const recoveryKey = async () => {
		const id = keyId();
		let key = await get('keys', id);
		if (!key) {
			key = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
			await put('keys', id, key);
		}
		return key;
	};
	const result = (message, status) => {
		const node = panel.querySelector('[data-future-result="offline"]');
		if (!node) return;
		node.textContent = String(message || '');
		node.dataset.status = status || 'ready';
	};
	const persist = async () => {
		if (!supported()) { await purgeCurrent(); return; }
		const id = keyId();
		const fields = snapshot();
		if (!fieldsAreBounded(fields)) {
			await purgeCurrent();
			result('Draft exceeds the bounded encrypted-recovery envelope. Continue with the authoritative online save; no partial or truncated browser recovery was retained.', 'blocked');
			return;
		}
		if (fieldsContainSensitiveContent(fields)) {
			await purgeCurrent();
			result('Potential personal/sensitive content detected — encrypted browser recovery is disabled and any local recovery for this draft was purged. Use the authoritative online save.', 'blocked');
			return;
		}
		const payload = JSON.stringify({ user_id: Number(config.userId || 0), adapter: adapter(), scope: scope(), fields, updated_at: new Date().toISOString() });
		if (byteLength(payload) > MAX_RECOVERY_BYTES + 4096) {
			await purgeCurrent();
			result('Encrypted recovery envelope exceeded its local safety bound and was not stored.', 'blocked');
			return;
		}
		const key = await recoveryKey();
		const iv = crypto.getRandomValues(new Uint8Array(12));
		const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, encoder.encode(payload));
		await put('drafts', id, { iv: Array.from(iv), cipher: Array.from(new Uint8Array(cipher)), updated_at: new Date().toISOString(), expires_at: new Date(Date.now() + MAX_AGE_MS).toISOString() });
	};
	const read = async () => {
		if (!supported()) return null;
		const id = keyId();
		const record = await get('drafts', id);
		if (!record) return null;
		const updated = Date.parse(String(record.updated_at || ''));
		if (!updated || Date.now() - updated > MAX_AGE_MS) { await deletePair(id); return null; }
		if (!Array.isArray(record.iv) || record.iv.length !== 12 || !Array.isArray(record.cipher) || record.cipher.length > MAX_RECOVERY_BYTES + 8192) {
			await deletePair(id);
			throw new Error('Recovery envelope is malformed or oversized');
		}
		try {
			const key = await recoveryKey();
			const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: new Uint8Array(record.iv), tagLength: 128 }, key, new Uint8Array(record.cipher));
			if (plain.byteLength > MAX_RECOVERY_BYTES + 4096) throw new Error('Recovery plaintext exceeds safety bound');
			const parsed = JSON.parse(new TextDecoder().decode(plain));
			if (!parsed || Number(parsed.user_id) !== Number(config.userId || 0) || parsed.adapter !== adapter() || parsed.scope !== scope()) throw new Error('Recovery scope mismatch');
			if (!fieldsAreBounded(parsed.fields) || fieldsContainSensitiveContent(parsed.fields)) throw new Error('Recovery is not eligible for local storage');
			return parsed;
		} catch (error) {
			await deletePair(id);
			throw error;
		}
	};
	const apply = (recovered) => {
		if (!recovered || !recovered.fields || isSensitive() || !fieldsAreBounded(recovered.fields) || fieldsContainSensitiveContent(recovered.fields)) return false;
		let invalid = false;
		form.querySelectorAll('[data-supc-field]').forEach((field) => {
			if (protectedRecoveryField(field) || !Object.prototype.hasOwnProperty.call(recovered.fields, field.name)) return;
			const value = recovered.fields[field.name];
			if (field.dataset.fieldType === 'checkbox') {
				field.checked = Boolean(value);
			} else if (field.dataset.fieldType === 'multiselect' && Array.isArray(value)) {
				const allowed = new Set(Array.from(field.options || []).map((option) => option.value));
				if (value.some((item) => !allowed.has(String(item)))) { invalid = true; return; }
				const selected = new Set(value.map(String));
				Array.from(field.options || []).forEach((option) => { option.selected = selected.has(option.value); });
			} else {
				const text = value == null ? '' : String(value);
				if ((field.maxLength && field.maxLength > 0 && text.length > field.maxLength) || byteLength(text) > MAX_FIELD_BYTES) { invalid = true; return; }
				field.value = text;
			}
		});
		if (invalid) return false;
		if (source && editor) editor.innerHTML = source.value;
		form.dispatchEvent(new Event('input', { bubbles: true }));
		return true;
	};

	const offlineButtonOld = panel.querySelector('[data-local-tool="offline"]');
	const restoreOld = panel.querySelector('[data-recovery-restore]');
	const discardOld = panel.querySelector('[data-recovery-discard]');
	const offlineButton = offlineButtonOld ? offlineButtonOld.cloneNode(true) : null;
	const restore = restoreOld ? restoreOld.cloneNode(true) : null;
	const discard = discardOld ? discardOld.cloneNode(true) : null;
	if (offlineButtonOld && offlineButton) offlineButtonOld.replaceWith(offlineButton);
	if (restoreOld && restore) restoreOld.replaceWith(restore);
	if (discardOld && discard) discardOld.replaceWith(discard);

	const show = async () => {
		if (!restore || !discard) return;
		const currentFields = snapshot();
		if (isSensitive() || !fieldsAreBounded(currentFields) || fieldsContainSensitiveContent(currentFields)) {
			await purgeCurrent();
			restore.hidden = true; discard.hidden = true;
			result('Sensitive, personally identifying, or oversized draft content — online secure save required. Local recovery has been purged.', 'blocked');
			return;
		}
		if (!supported()) {
			restore.hidden = true; discard.hidden = true;
			result('Encrypted recovery requires an approved non-sensitive adapter policy, HTTPS, WebCrypto, TextEncoder and IndexedDB.', 'blocked');
			return;
		}
		const record = await get('drafts', keyId());
		const updated = record ? Date.parse(String(record.updated_at || '')) : 0;
		if (record && (!updated || Date.now() - updated > MAX_AGE_MS)) { await purgeCurrent(); }
		const current = await get('drafts', keyId());
		restore.hidden = !current; discard.hidden = !current;
		result(current ? 'Encrypted device-bound recovery is available from ' + current.updated_at + '. Shared-device warning: anyone with this browser profile may retain access to the encrypted browser store until logout/purge/expiry.' : 'No encrypted recovery is stored for this draft. Maximum local retention is 24 hours.', 'ready');
	};

	if (offlineButton) offlineButton.addEventListener('click', () => show().catch(() => result('Recovery storage could not be read.', 'error')));
	if (restore) restore.addEventListener('click', async () => {
		try {
			const recovered = await read();
			if (apply(recovered)) result('Encrypted recovery restored locally. Review and save to the authoritative native owner.', 'ready');
			else result('No complete, schema-valid safe recovery was available for this draft.', 'warning');
		} catch (error) { result('Encrypted recovery failed authentication/privacy/size eligibility and was securely discarded.', 'error'); }
	});
	if (discard) discard.addEventListener('click', async () => { await purgeCurrent(); await show(); });

	form.addEventListener('input', () => {
		window.clearTimeout(timer);
		timer = window.setTimeout(() => persist().catch(() => {}), 2500);
	});
	const autosave = root.querySelector('[data-supc-autosave-state]');
	const autosaveSucceeded = () => /^(?:saved|completed)$/i.test(String(autosave && autosave.textContent || '').trim());
	if (autosave) new MutationObserver(() => { if (autosaveSucceeded()) purgeCurrent().catch(() => {}); }).observe(autosave, { childList: true, characterData: true, subtree: true });

	document.addEventListener('click', (event) => {
		if (event.defaultPrevented || event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
		const link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
		if (!link || !/action=logout/i.test(String(link.href || ''))) return;
		event.preventDefault();
		const destination = link.href;
		Promise.race([purgeCurrentUser(), new Promise((resolve) => window.setTimeout(resolve, 500))]).finally(() => { window.location.assign(destination); });
	}, true);

	purgeExpiredAndForeign().then(show).catch(() => result('Recovery initialization failed closed.', 'error'));
}());
