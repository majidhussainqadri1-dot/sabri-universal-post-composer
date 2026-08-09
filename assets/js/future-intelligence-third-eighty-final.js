(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	const config = window.SUPCFuture;
	if (!root || !config) return;
	const form = root.querySelector('[data-supc-form]');
	const panel = root.querySelector('.supc-intel');
	const editor = root.querySelector('[data-supc-rte]');
	if (!form || !panel) return;

	const DB_NAME = 'supc-future-recovery-v3';
	const DB_VERSION = 1;
	const TOKEN_PATTERN = /^[0-9a-f]{32}$/;
	const SESSION_PATTERN = /^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/;
	const MAX_LOCAL_ANALYSIS_TEXT = 131072;
	const protectedName = /(?:native_reference|publication_action|consent|privacy_confirm|medical_disclaimer_confirm|copyright_declaration|rights_declaration|verification|capability|moderation|status|guardian|credential|identity_evidence|author_id|effective_author|patient_id|medical_record)/i;
	let purgeInFlight = false;

	const userId = () => Number(config.userId || 0);
	const adapterKey = () => String(config.adapter || root.dataset.adapter || '').trim();
	const sessionUuid = () => {
		const value = String(new URL(window.location.href).searchParams.get('session') || '').toLowerCase();
		return SESSION_PATTERN.test(value) ? value : '';
	};
	const historyToken = () => {
		const state = window.history.state && typeof window.history.state === 'object' ? window.history.state : {};
		const token = String(state.supcRecoveryToken || '').toLowerCase();
		return TOKEN_PATTERN.test(token) ? token : '';
	};
	const containsSensitiveText = (value) => {
		const raw = String(value || '');
		// Recovery v3 refuses values beyond this ceiling. Treat an over-bound value
		// as ineligible immediately so an older low-risk recovery cannot linger
		// until the later debounced persistence attempt.
		if (raw.length > MAX_LOCAL_ANALYSIS_TEXT) return true;
		return /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/i.test(raw)
			|| /(?:\+?\d[\d\s().-]{8,}\d)/.test(raw)
			|| /\b\d{5}-?\d{7}-?\d\b/.test(raw)
			|| /\b(?:passport|cnic|national\s+id|medical\s+record|mrn|patient\s+id|registration\s+number)\s*[:#-]?\s*[A-Z0-9-]{3,}\b/i.test(raw)
			|| /\b(?:DOB|date of birth|تاریخ پیدائش)\s*[:\-]?\s*\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}\b/i.test(raw)
			|| /\b-?\d{1,2}\.\d{4,}\s*,\s*-?\d{1,3}\.\d{4,}\b/.test(raw)
			|| /\b(?:address|street|گھر\s*کا\s*پتہ|پتہ)\s*[:\-]\s*[^\n]{8,}/i.test(raw);
	};
	const workflowIsSensitiveNow = () => {
		if (String(config.adapterPrivacyClassification || '').toLowerCase() === 'sensitive') return true;
		return Array.from(form.querySelectorAll('[data-supc-field]')).some((field) => {
			const name = String(field.name || '');
			if (field.dataset.privacy === 'sensitive' || protectedName.test(name) || /(?:patient|clinical_case|successful_case)/i.test(name)) return true;
			if (field.dataset.fieldType === 'checkbox') return false;
			if (field.dataset.fieldType === 'multiselect') return Array.from(field.selectedOptions || []).some((option) => containsSensitiveText(option.value));
			return containsSensitiveText(field.value);
		});
	};

	const openDb = () => new Promise((resolve, reject) => {
		const request = indexedDB.open(DB_NAME, DB_VERSION);
		request.onsuccess = () => resolve(request.result);
		request.onerror = () => reject(request.error || new Error('Recovery database unavailable.'));
		request.onupgradeneeded = () => {
			const db = request.result;
			['keys', 'drafts', 'aliases'].forEach((store) => {
				if (!db.objectStoreNames.contains(store)) db.createObjectStore(store);
			});
		};
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
	const resolveScopeToken = async () => {
		const direct = historyToken();
		if (direct) return direct;
		const session = sessionUuid();
		if (!session) return '';
		const alias = await dbGet('aliases', String(userId()) + ':alias:' + adapterKey() + ':' + session).catch(() => null);
		return typeof alias === 'string' && TOKEN_PATTERN.test(alias) ? alias : '';
	};
	const purgeCurrentRecovery = async () => {
		if (purgeInFlight || !window.indexedDB) return;
		purgeInFlight = true;
		try {
			const token = await resolveScopeToken();
			if (!token) return;
			const id = String(userId()) + ':' + adapterKey() + ':' + token;
			await Promise.all([
				dbDelete('drafts', id).catch(() => false),
				dbDelete('keys', id).catch(() => false)
			]);
			const session = sessionUuid();
			if (session) await dbDelete('aliases', String(userId()) + ':alias:' + adapterKey() + ':' + session).catch(() => false);
		} finally {
			purgeInFlight = false;
		}
	};
	const enforceRecoveryPrivacy = () => {
		if (workflowIsSensitiveNow()) purgeCurrentRecovery().catch(() => {});
	};
	form.addEventListener('input', enforceRecoveryPrivacy, true);
	enforceRecoveryPrivacy();

	// Replace the evidence trigger so long drafts are never silently sampled as
	// if they had been fully analyzed. The local analysis is complete-or-reject.
	const oldEvidence = panel.querySelector('[data-local-tool="evidence"]');
	if (oldEvidence) {
		const evidence = oldEvidence.cloneNode(true);
		oldEvidence.replaceWith(evidence);
		evidence.addEventListener('click', () => {
			const raw = editor
				? String(editor.textContent || '').replace(/\s+/g, ' ').trim()
				: String((form.querySelector('[name="content"], [name="body"], textarea[data-supc-field]') || {}).value || '').replace(/\s+/g, ' ').trim();
			const result = panel.querySelector('[data-future-result="evidence"]');
			if (!result) return;
			if (raw.length > MAX_LOCAL_ANALYSIS_TEXT) {
				result.textContent = 'The complete draft exceeds the bounded local evidence-analysis envelope. No partial heatmap was presented as complete.';
				result.dataset.status = 'blocked';
				return;
			}
			const sentences = raw.split(/(?<=[.!?؟])\s+/).filter((value) => value.trim().length > 25);
			const refPattern = /(\[[0-9]{1,3}\]|\([A-Z][A-Za-z-]+,?\s+20\d{2}\)|https?:\/\/|doi:|PMID|ISBN)/i;
			const cited = sentences.filter((sentence) => refPattern.test(sentence));
			const uncited = sentences.filter((sentence) => !refPattern.test(sentence));
			const sample = uncited.slice(0, 8).map((sentence) => '• ' + sentence.slice(0, 140)).join('\n');
			result.textContent = 'Claims analyzed completely: ' + sentences.length + '\nCited/linked: ' + cited.length + '\nNeeds evidence review: ' + uncited.length + (sample ? '\n\nSample uncited claims:\n' + sample : '');
			result.dataset.status = uncited.length ? 'warning' : 'ready';
		});
	}

	// The earlier slash-command listener opens the dialog directly. Capture the
	// keystroke first and route it through the hardened launcher so focus origin,
	// modal semantics and restoration remain deterministic.
	const launcher = document.querySelector('[data-local-tool="command"]');
	if (editor && launcher) {
		editor.addEventListener('keydown', (event) => {
			if (event.key !== '/' || event.ctrlKey || event.metaKey || event.altKey) return;
			const selection = window.getSelection();
			if (!selection || !selection.isCollapsed) return;
			const prefix = String(selection.anchorNode && selection.anchorNode.textContent || '').slice(0, selection.anchorOffset);
			if (prefix && !/\s$/.test(prefix)) return;
			event.preventDefault();
			event.stopImmediatePropagation();
			window.setTimeout(() => launcher.click(), 0);
		}, true);
	}
}());
