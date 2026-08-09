(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	const config = window.SUPCFuture;
	if (!root || !config || !window.indexedDB || !window.crypto || !crypto.subtle) return;
	const form = root.querySelector('[data-supc-form]');
	const panel = root.querySelector('.supc-intel');
	const editor = root.querySelector('[data-supc-rte]');
	if (!form || !panel) return;

	const DB_NAME = 'supc-future-recovery-v3';
	const DB_VERSION = 1;
	const MAX_RECOVERY_BYTES = 262144;
	const MAX_RECOVERY_FIELDS = 128;
	const MAX_TEXT_LENGTH = 131072;
	const MAX_MULTI_VALUES = 128;
	const MAX_OPTION_LENGTH = 1024;
	const MAX_AGE_MS = 24 * 60 * 60 * 1000;
	const TOKEN_PATTERN = /^[0-9a-f]{32}$/;
	const SESSION_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
	const protectedName = /(?:native_reference|publication_action|consent|privacy_confirm|medical_disclaimer_confirm|copyright_declaration|rights_declaration|verification|capability|moderation|status|guardian|credential|identity_evidence|author_id|effective_author|patient_id|medical_record)/i;
	let persistTimer = null;
	let lastBoundSession = '';

	const userId = () => Number(config.userId || 0);
	const adapterKey = () => String(config.adapter || root.dataset.adapter || '').trim();
	const sessionUuid = () => {
		const value = String(new URL(window.location.href).searchParams.get('session') || '').toLowerCase();
		return SESSION_PATTERN.test(value) ? value : '';
	};
	const setResult = (message, state) => {
		const node = panel.querySelector('[data-future-result="offline"]');
		if (!node) return;
		node.textContent = String(message || '');
		node.dataset.status = state || 'ready';
	};
	const encodedBytes = (value) => new TextEncoder().encode(String(value || '')).byteLength;
	const policyAllows = () => Boolean(config.privacy && config.privacy.auditedEncryptedRecovery) && String(config.adapterPrivacyClassification || '').toLowerCase() !== 'sensitive';
	const fieldIsEligible = (field) => {
		if (!field || !field.name || field.dataset.privacy === 'sensitive' || field.dataset.fieldType === 'opaque_reference') return false;
		return !protectedName.test(String(field.name));
	};
	const workflowIsSensitive = () => {
		if (!policyAllows()) return true;
		return Array.from(form.querySelectorAll('[data-supc-field]')).some((field) => {
			const name = String(field.name || '');
			return field.dataset.privacy === 'sensitive' || /(?:patient|consent|clinical_case|successful_case|guardian|credential|identity_evidence|medical_record)/i.test(name);
		});
	};
	const textContainsSensitiveContent = (value) => {
		const sample = String(value || '').slice(0, MAX_TEXT_LENGTH);
		return /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i.test(sample)
			|| /(?:\+?\d[\d\s().-]{8,}\d)/.test(sample)
			|| /\b\d{5}-?\d{7}-?\d\b/.test(sample)
			|| /\b(?:passport|cnic|national\s+id|medical\s+record|mrn|patient\s+id|registration\s+number)\s*[:#-]?\s*[A-Z0-9-]{4,}\b/i.test(sample)
			|| /\b(?:DOB|date of birth|تاریخ پیدائش)\s*[:\-]?\s*\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}\b/i.test(sample)
			|| /\b-?\d{1,2}\.\d{4,}\s*,\s*-?\d{1,3}\.\d{4,}\b/.test(sample)
			|| /\b(?:address|street|گھر\s*کا\s*پتہ|پتہ)\s*[:\-]\s*[^\n]{8,}/i.test(sample);
	};
	const fieldsContainSensitiveContent = (fields) => Object.keys(fields || {}).some((name) => {
		if (protectedName.test(name)) return true;
		const value = fields[name];
		if (Array.isArray(value)) return value.some(textContainsSensitiveContent);
		return typeof value === 'string' && textContainsSensitiveContent(value);
	});

	const openDb = () => new Promise((resolve, reject) => {
		const request = indexedDB.open(DB_NAME, DB_VERSION);
		request.onupgradeneeded = () => {
			const db = request.result;
			['keys', 'drafts', 'aliases'].forEach((store) => {
				if (!db.objectStoreNames.contains(store)) db.createObjectStore(store);
			});
		};
		request.onsuccess = () => resolve(request.result);
		request.onerror = () => reject(request.error || new Error('Recovery database unavailable.'));
	});
	const dbGet = async (store, key) => {
		const db = await openDb();
		return new Promise((resolve, reject) => {
			const tx = db.transaction(store, 'readonly');
			const request = tx.objectStore(store).get(key);
			request.onsuccess = () => resolve(request.result);
			request.onerror = () => reject(request.error);
			tx.oncomplete = () => db.close();
			tx.onabort = () => db.close();
		});
	};
	const dbPut = async (store, key, value) => {
		const db = await openDb();
		return new Promise((resolve, reject) => {
			const tx = db.transaction(store, 'readwrite');
			tx.objectStore(store).put(value, key);
			tx.oncomplete = () => { db.close(); resolve(true); };
			tx.onerror = () => { db.close(); reject(tx.error); };
			tx.onabort = () => { db.close(); reject(tx.error); };
		});
	};
	const dbDelete = async (store, key) => {
		const db = await openDb();
		return new Promise((resolve, reject) => {
			const tx = db.transaction(store, 'readwrite');
			tx.objectStore(store).delete(key);
			tx.oncomplete = () => { db.close(); resolve(true); };
			tx.onerror = () => { db.close(); reject(tx.error); };
			tx.onabort = () => { db.close(); reject(tx.error); };
		});
	};
	const deleteByPrefix = async (store, prefix, keepMatching) => {
		const db = await openDb();
		return new Promise((resolve, reject) => {
			const tx = db.transaction(store, 'readwrite');
			const request = tx.objectStore(store).openCursor();
			request.onsuccess = () => {
				const cursor = request.result;
				if (!cursor) return;
				const matches = String(cursor.key || '').startsWith(prefix);
				if ((keepMatching && !matches) || (!keepMatching && matches)) cursor.delete();
				cursor.continue();
			};
			tx.oncomplete = () => { db.close(); resolve(true); };
			tx.onerror = () => { db.close(); reject(tx.error); };
			tx.onabort = () => { db.close(); reject(tx.error); };
		});
	};

	const makeToken = () => Array.from(crypto.getRandomValues(new Uint8Array(16))).map((byte) => byte.toString(16).padStart(2, '0')).join('');
	const historyToken = () => {
		const state = window.history.state && typeof window.history.state === 'object' ? window.history.state : {};
		const existing = String(state.supcRecoveryToken || '').toLowerCase();
		return TOKEN_PATTERN.test(existing) ? existing : '';
	};
	const rememberTokenInHistory = (token) => {
		if (!TOKEN_PATTERN.test(token)) return;
		const state = window.history.state && typeof window.history.state === 'object' ? window.history.state : {};
		if (String(state.supcRecoveryToken || '').toLowerCase() === token) return;
		window.history.replaceState(Object.assign({}, state, { supcRecoveryToken: token }), '', window.location.href);
	};
	const aliasKey = (session) => String(userId()) + ':alias:' + adapterKey() + ':' + session;
	const resolveStableToken = async () => {
		const session = sessionUuid();
		if (session) {
			const alias = await dbGet('aliases', aliasKey(session)).catch(() => null);
			if (typeof alias === 'string' && TOKEN_PATTERN.test(alias)) {
				rememberTokenInHistory(alias);
				return alias;
			}
		}
		const existing = historyToken();
		const token = existing || makeToken();
		rememberTokenInHistory(token);
		if (session) await dbPut('aliases', aliasKey(session), token);
		return token;
	};
	const tokenPromise = resolveStableToken();
	const scopeId = async () => String(userId()) + ':' + adapterKey() + ':' + await tokenPromise;
	const bindCurrentSession = async () => {
		const session = sessionUuid();
		if (!session || session === lastBoundSession) return;
		const token = await tokenPromise;
		await dbPut('aliases', aliasKey(session), token);
		rememberTokenInHistory(token);
		lastBoundSession = session;
	};

	const schemaDescriptor = () => {
		const descriptors = [];
		const fields = Array.from(form.querySelectorAll('[data-supc-field]')).filter(fieldIsEligible);
		if (fields.length > MAX_RECOVERY_FIELDS) return null;
		for (const field of fields) {
			const options = field.tagName === 'SELECT' ? Array.from(field.options || []).map((option) => String(option.value)) : [];
			if (options.length > MAX_MULTI_VALUES || options.some((value) => value.length > MAX_OPTION_LENGTH)) return null;
			descriptors.push({
				name: String(field.name),
				field_type: String(field.dataset.fieldType || ''),
				tag: String(field.tagName || ''),
				type: String(field.getAttribute('type') || ''),
				max_length: Number(field.maxLength > 0 ? field.maxLength : 0),
				options
			});
		}
		descriptors.sort((left, right) => left.name.localeCompare(right.name));
		return descriptors;
	};
	const schemaFingerprint = async () => {
		const descriptor = schemaDescriptor();
		if (!descriptor) return '';
		const digest = await crypto.subtle.digest('SHA-256', new TextEncoder().encode(JSON.stringify(descriptor)));
		return Array.from(new Uint8Array(digest)).map((byte) => byte.toString(16).padStart(2, '0')).join('');
	};
	const snapshot = () => {
		const out = {};
		const fields = Array.from(form.querySelectorAll('[data-supc-field]')).filter(fieldIsEligible);
		if (fields.length > MAX_RECOVERY_FIELDS) return null;
		for (const field of fields) {
			let value;
			if (field.dataset.fieldType === 'checkbox') value = Boolean(field.checked);
			else if (field.dataset.fieldType === 'multiselect') {
				value = Array.from(field.selectedOptions || []).map((option) => String(option.value));
				if (value.length > MAX_MULTI_VALUES || value.some((item) => item.length > MAX_OPTION_LENGTH)) return null;
			} else {
				value = String(field.value == null ? '' : field.value);
				const limit = field.maxLength && field.maxLength > 0 ? Math.min(field.maxLength, MAX_TEXT_LENGTH) : MAX_TEXT_LENGTH;
				if (value.length > limit) return null;
			}
			out[field.name] = value;
		}
		if (fieldsContainSensitiveContent(out) || encodedBytes(JSON.stringify(out)) > MAX_RECOVERY_BYTES) return null;
		return out;
	};
	const getKey = async () => {
		const id = await scopeId();
		let key = await dbGet('keys', id);
		if (!key) {
			key = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']);
			await dbPut('keys', id, key);
		}
		return key;
	};
	const purgeCurrent = async () => {
		const id = await scopeId();
		await Promise.all([
			dbDelete('drafts', id).catch(() => false),
			dbDelete('keys', id).catch(() => false)
		]);
	};
	const persist = async () => {
		await bindCurrentSession().catch(() => {});
		if (workflowIsSensitive()) {
			await purgeCurrent().catch(() => {});
			return false;
		}
		const fields = snapshot();
		const fingerprint = await schemaFingerprint();
		if (!fields || !fingerprint) {
			await purgeCurrent().catch(() => {});
			setResult('Encrypted recovery was not stored because the draft exceeded the bounded recovery schema or contained sensitive/identifying content.', 'warning');
			return false;
		}
		const token = await tokenPromise;
		const now = Date.now();
		const envelope = {
			version: 3,
			user_id: userId(),
			adapter_key: adapterKey(),
			scope_token: token,
			schema_fingerprint: fingerprint,
			fields,
			updated_at: new Date(now).toISOString(),
			expires_at: now + MAX_AGE_MS
		};
		const plaintext = JSON.stringify(envelope);
		if (encodedBytes(plaintext) > MAX_RECOVERY_BYTES) return false;
		const key = await getKey();
		const iv = crypto.getRandomValues(new Uint8Array(12));
		const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode(plaintext));
		await dbPut('drafts', await scopeId(), {
			iv: Array.from(iv),
			cipher: Array.from(new Uint8Array(cipher)),
			updated_at: envelope.updated_at,
			expires_at: envelope.expires_at
		});
		return true;
	};
	const read = async () => {
		if (workflowIsSensitive()) return null;
		await bindCurrentSession().catch(() => {});
		const id = await scopeId();
		const record = await dbGet('drafts', id);
		if (!record || Number(record.expires_at || 0) < Date.now()) {
			if (record) await purgeCurrent().catch(() => {});
			return null;
		}
		const key = await dbGet('keys', id);
		if (!key || !Array.isArray(record.iv) || !Array.isArray(record.cipher) || record.iv.length !== 12) return null;
		const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: new Uint8Array(record.iv) }, key, new Uint8Array(record.cipher));
		if (plain.byteLength > MAX_RECOVERY_BYTES) return null;
		const parsed = JSON.parse(new TextDecoder().decode(plain));
		const token = await tokenPromise;
		const fingerprint = await schemaFingerprint();
		if (!parsed || parsed.version !== 3 || parsed.user_id !== userId() || parsed.adapter_key !== adapterKey() || parsed.scope_token !== token || parsed.schema_fingerprint !== fingerprint || Number(parsed.expires_at || 0) < Date.now()) return null;
		if (!parsed.fields || typeof parsed.fields !== 'object' || Array.isArray(parsed.fields) || Object.keys(parsed.fields).length > MAX_RECOVERY_FIELDS || fieldsContainSensitiveContent(parsed.fields)) return null;
		return parsed;
	};

	const prepareAssignments = (fields) => {
		if (!fields || typeof fields !== 'object' || Array.isArray(fields)) return null;
		const current = new Map();
		form.querySelectorAll('[data-supc-field]').forEach((field) => { if (field.name) current.set(field.name, field); });
		const prepared = [];
		for (const name of Object.keys(fields)) {
			const field = current.get(name);
			if (!field || !fieldIsEligible(field)) return null;
			const value = fields[name];
			if (field.dataset.fieldType === 'checkbox') {
				if (typeof value !== 'boolean') return null;
				prepared.push(() => { field.checked = value; });
				continue;
			}
			if (field.dataset.fieldType === 'multiselect') {
				if (!Array.isArray(value) || value.length > MAX_MULTI_VALUES) return null;
				const allowed = new Set(Array.from(field.options || []).map((option) => option.value));
				const candidates = value.map(String);
				if (candidates.some((item) => item.length > MAX_OPTION_LENGTH || !allowed.has(item))) return null;
				prepared.push(() => {
					const selected = new Set(candidates);
					Array.from(field.options || []).forEach((option) => { option.selected = selected.has(option.value); });
				});
				continue;
			}
			if (field.tagName === 'SELECT') {
				const candidate = String(value == null ? '' : value);
				if (!Array.from(field.options || []).some((option) => option.value === candidate)) return null;
				prepared.push(() => { field.value = candidate; });
				continue;
			}
			if (typeof value !== 'string') return null;
			const limit = field.maxLength && field.maxLength > 0 ? Math.min(field.maxLength, MAX_TEXT_LENGTH) : MAX_TEXT_LENGTH;
			if (value.length > limit) return null;
			prepared.push(() => { field.value = value; });
		}
		return prepared;
	};
	const apply = async (parsed) => {
		if (!parsed || !parsed.fields || workflowIsSensitive()) return false;
		const fingerprint = await schemaFingerprint();
		if (!fingerprint || parsed.schema_fingerprint !== fingerprint) return false;
		const prepared = prepareAssignments(parsed.fields);
		if (!prepared) return false;
		// Two-phase restore: nothing is mutated until every recovered field has
		// passed the current schema/type/option/length/privacy validation.
		prepared.forEach((assignment) => assignment());
		form.dispatchEvent(new Event('input', { bubbles: true }));
		if (editor) editor.dispatchEvent(new Event('input', { bubbles: true }));
		return true;
	};

	const oldCheck = panel.querySelector('[data-local-tool="offline"]');
	const oldRestore = panel.querySelector('[data-recovery-restore]');
	const oldDiscard = panel.querySelector('[data-recovery-discard]');
	if (!oldCheck || !oldRestore || !oldDiscard) return;
	const check = oldCheck.cloneNode(true);
	const restore = oldRestore.cloneNode(true);
	const discard = oldDiscard.cloneNode(true);
	oldCheck.replaceWith(check);
	oldRestore.replaceWith(restore);
	oldDiscard.replaceWith(discard);

	const showState = async () => {
		if (workflowIsSensitive()) {
			restore.hidden = true;
			discard.hidden = true;
			setResult('Encrypted local recovery is disabled for sensitive/patient/identity-shaped workflows.', 'blocked');
			return;
		}
		try {
			const recovered = await read();
			restore.hidden = !recovered;
			discard.hidden = !recovered;
			setResult(recovered ? 'A schema-bound encrypted recovery is available from ' + recovered.updated_at + '.' : 'No eligible encrypted recovery is currently stored for this tab/session lineage.', 'ready');
		} catch (error) {
			restore.hidden = true;
			discard.hidden = true;
			setResult('Encrypted recovery could not be inspected safely.', 'error');
		}
	};
	check.addEventListener('click', () => showState());
	restore.addEventListener('click', async () => {
		restore.disabled = true;
		try {
			const recovered = await read();
			if (!recovered || !(await apply(recovered))) throw new Error('Recovery no longer matches the current Composer schema.');
			setResult('Encrypted recovery restored atomically. Review the complete draft before saving to the native owner.', 'ready');
		} catch (error) {
			setResult(error.message || 'Encrypted recovery could not be restored.', 'error');
		} finally { restore.disabled = false; }
	});
	discard.addEventListener('click', async () => {
		discard.disabled = true;
		await purgeCurrent().catch(() => {});
		discard.disabled = false;
		showState();
	});

	form.addEventListener('input', () => {
		window.clearTimeout(persistTimer);
		if (!workflowIsSensitive()) persistTimer = window.setTimeout(() => persist().catch(() => {}), 2500);
	});
	const autosave = root.querySelector('[data-supc-autosave-state]');
	if (autosave) {
		new MutationObserver(() => {
			const state = String(autosave.textContent || '').trim();
			if (/^(?:saved|completed)$/i.test(state)) purgeCurrent().catch(() => {});
		}).observe(autosave, { childList: true, characterData: true, subtree: true });
	}

	document.addEventListener('click', (event) => {
		const link = event.target && event.target.closest ? event.target.closest('a[href]') : null;
		if (!link || !/[?&]action=logout(?:&|$)/i.test(String(link.href || ''))) return;
		const prefix = String(userId()) + ':';
		Promise.all([
			deleteByPrefix('drafts', prefix, false),
			deleteByPrefix('keys', prefix, false),
			deleteByPrefix('aliases', prefix, false)
		]).catch(() => {});
	}, true);

	const currentPrefix = String(userId()) + ':';
	Promise.all([
		deleteByPrefix('drafts', currentPrefix, true),
		deleteByPrefix('keys', currentPrefix, true),
		deleteByPrefix('aliases', currentPrefix, true)
	]).catch(() => {});

	// v2 is no longer an active recovery path. Best-effort deletion is safe
	// because this v3 implementation never reads, imports, or reuses v2 data.
	try { indexedDB.deleteDatabase('supc-future-recovery-v2'); } catch (error) { /* No v2 reuse. */ }

	window.setInterval(() => bindCurrentSession().catch(() => {}), 250);
	bindCurrentSession().catch(() => {});
	showState().catch(() => {});
}());
