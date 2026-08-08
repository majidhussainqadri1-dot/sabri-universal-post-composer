(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	const config = window.SUPCFuture;
	if (!root || !config) return;

	const form = root.querySelector('[data-supc-form]');
	const rail = root.querySelector('.supc-workflow__rail');
	const richEditor = root.querySelector('[data-supc-rte]');
	const richSource = root.querySelector('[data-supc-rte-source]');
	const uploadInput = root.querySelector('[data-supc-upload-input]');
	const bridgeCapabilities = new Set();
	const localCapabilities = new Set();
	let mediaState = null;
	let recoveryTimer = null;
	let collaborationTimer = null;

	if (!form || !rail) return;

	const escapeHtml = (value) => {
		const node = document.createElement('div');
		node.textContent = String(value == null ? '' : value);
		return node.innerHTML;
	};

	const safeRichHtml = (html) => {
		const template = document.createElement('template');
		template.innerHTML = String(html || '');
		const allowed = new Set(['P', 'BR', 'H2', 'H3', 'H4', 'STRONG', 'B', 'EM', 'I', 'UL', 'OL', 'LI', 'BLOCKQUOTE', 'A', 'TABLE', 'THEAD', 'TBODY', 'TR', 'TH', 'TD', 'HR', 'SUP', 'SUB', 'FIGURE', 'FIGCAPTION', 'IMG']);
		const clean = (parent) => {
			Array.from(parent.children || []).forEach((child) => {
				clean(child);
				if (!allowed.has(child.tagName)) {
					child.replaceWith(...Array.from(child.childNodes));
					return;
				}
				Array.from(child.attributes).forEach((attribute) => {
					const name = attribute.name.toLowerCase();
					if (child.tagName === 'A' && name === 'href') {
						try {
							const url = new URL(attribute.value, window.location.origin);
							if (!['http:', 'https:'].includes(url.protocol)) child.removeAttribute('href');
						} catch (error) { child.removeAttribute('href'); }
						return;
					}
					if (child.tagName === 'IMG' && ['src', 'alt', 'title'].includes(name)) {
						if (name === 'src') {
							try {
								const url = new URL(attribute.value, window.location.origin);
								if (!['http:', 'https:', 'blob:'].includes(url.protocol)) child.removeAttribute('src');
							} catch (error) { child.removeAttribute('src'); }
						}
						return;
					}
					child.removeAttribute(attribute.name);
				});
			});
		};
		clean(template.content);
		return template.innerHTML;
	};

	const fieldValue = (field) => {
		if (field.matches('[data-supc-rte-source]')) return safeRichHtml(field.value);
		if (field.dataset.fieldType === 'checkbox') return field.checked;
		if (field.dataset.fieldType === 'multiselect') return Array.from(field.selectedOptions || []).map((item) => item.value);
		return field.value;
	};

	const snapshot = () => {
		const out = {};
		form.querySelectorAll('[data-supc-field]').forEach((field) => { out[field.name] = fieldValue(field); });
		return out;
	};

	const plainText = () => {
		if (richEditor) return String(richEditor.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 60000);
		const content = form.querySelector('[name="content"], [name="body"], textarea');
		return content ? String(content.value || '').replace(/\s+/g, ' ').trim().slice(0, 60000) : '';
	};

	const titleText = () => {
		const field = form.querySelector('[name="title"]');
		return field ? String(field.value || '').trim().slice(0, 1000) : '';
	};

	const sessionUuid = () => new URL(window.location.href).searchParams.get('session') || '';
	const hasSensitiveFields = () => Boolean(root.querySelector('[data-supc-field][data-privacy="sensitive"], .supc-workflow__field[data-privacy="sensitive"]'));
	const hasPatientShape = () => Array.from(form.querySelectorAll('[data-supc-field]')).some((field) => /patient|consent|anonym|clinical_case|successful_case/i.test(field.name || ''));
	const isSensitiveDraft = () => hasSensitiveFields() || hasPatientShape();

	const request = async (path, method, body) => {
		const response = await fetch(String(config.restRoot || '').replace(/\/+$/, '') + path, {
			method,
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: body === undefined ? undefined : JSON.stringify(body)
		});
		const data = await response.json().catch(() => ({}));
		if (!response.ok) {
			const error = new Error(data.message || (config.strings && config.strings.failed) || 'Request failed');
			error.code = data.code || 'supc_future_request_failed';
			throw error;
		}
		return data;
	};

	const bridgeInvoke = async (capability, payload) => {
		if (!bridgeCapabilities.has(capability)) throw new Error((config.strings && config.strings.providerUnavailable) || 'Provider unavailable');
		return request('/future/invoke', 'POST', {
			adapter_key: config.adapter || root.dataset.adapter,
			capability,
			session_uuid: sessionUuid(),
			payload: Object.assign({ sensitive: isSensitiveDraft() }, payload || {})
		});
	};

	const resultText = (response) => {
		const value = response && Object.prototype.hasOwnProperty.call(response, 'result') ? response.result : response;
		if (typeof value === 'string') return value;
		if (value && typeof value.text === 'string') return value.text;
		if (value && typeof value.summary === 'string') return value.summary;
		return JSON.stringify(value || {}, null, 2);
	};

	const setResult = (tool, text, status) => {
		const node = panel.querySelector('[data-future-result="' + tool + '"]');
		if (!node) return;
		node.textContent = String(text || '');
		node.dataset.status = status || 'ready';
	};

	const setBusy = (button, busy) => {
		if (!button) return;
		button.disabled = Boolean(busy);
		button.setAttribute('aria-busy', busy ? 'true' : 'false');
	};

	const bridgeTool = (button, capability, payloadFactory) => {
		if (!button) return;
		button.addEventListener('click', async () => {
			if (isSensitiveDraft() && ['ai_copilot', 'medical_terminology', 'cross_format_derivative'].includes(capability) && !(config.privacy && config.privacy.externalSensitiveAdvice)) {
				setResult(button.dataset.tool, (config.strings && config.strings.sensitiveBlocked) || 'External advisory blocked for sensitive draft.', 'blocked');
				return;
			}
			setBusy(button, true);
			setResult(button.dataset.tool, (config.strings && config.strings.working) || 'Working…', 'working');
			try {
				const response = await bridgeInvoke(capability, payloadFactory ? payloadFactory() : {});
				setResult(button.dataset.tool, resultText(response), 'ready');
			} catch (error) {
				setResult(button.dataset.tool, error.message || 'Request failed', 'error');
			} finally { setBusy(button, false); }
		});
	};

	const toolDetails = (key, title, description, controls) => '<details class="supc-intel__tool" data-future-tool="' + key + '"><summary><strong>' + escapeHtml(title) + '</strong><span>' + escapeHtml(description) + '</span></summary><div class="supc-intel__body">' + controls + '<pre class="supc-intel__result" data-future-result="' + key + '" aria-live="polite"></pre></div></details>';

	const panel = document.createElement('section');
	panel.className = 'supc-intel';
	panel.setAttribute('aria-label', 'Composer Intelligence');
	panel.innerHTML = '<header class="supc-intel__header"><p class="supc-intel__eyebrow">Future Composer Intelligence</p><h3>' + escapeHtml((config.strings && config.strings.title) || 'Composer Intelligence') + '</h3><p>Advisory and orchestration tools. Native modules remain authoritative owners.</p></header>' +
		toolDetails('ai', 'AI Composer Copilot', 'Human-approved writing assistance through the owning AI provider.', '<label>Task <select data-ai-task><option value="outline">Outline</option><option value="rewrite">Rewrite</option><option value="summary">Summary</option><option value="title">Title alternatives</option><option value="citations">Citation suggestions</option></select></label><button type="button" class="button" data-tool="ai">Generate advisory</button>') +
		toolDetails('evidence', 'Evidence Graph & Citation Heatmap', 'Find uncited or weakly supported claims locally.', '<button type="button" class="button" data-local-tool="evidence">Analyze evidence</button>') +
		toolDetails('terminology', 'Medical Terminology Intelligence', 'Terminology guidance from an authorized provider; never clinical authority.', '<button type="button" class="button" data-tool="terminology">Check terminology</button>') +
		toolDetails('privacy', 'Patient Privacy De-identification Assistant', 'Detect likely identifiers locally without sending draft text away.', '<button type="button" class="button" data-local-tool="privacy">Scan privacy</button>') +
		toolDetails('voice', 'Voice-to-Structured Composer', 'Browser speech recognition into the active editor.', '<button type="button" class="button" data-local-tool="voice">Start / stop dictation</button><span data-voice-state></span>') +
		toolDetails('collaboration', 'Real-Time Collaborative Drafting', 'Provider-backed presence and cursor metadata; no duplicate File 22 draft store.', '<button type="button" class="button" data-tool="collaboration">Refresh collaborators</button>') +
		toolDetails('annotations', 'Inline Reviewer Annotation Layer', 'Read provider-owned reviewer annotations and change requests.', '<button type="button" class="button" data-tool="annotations">Load annotations</button>') +
		toolDetails('diff', 'Semantic Revision Diff', 'Explain meaning-level changes through the native provider.', '<button type="button" class="button" data-tool="diff">Compare revision</button>') +
		toolDetails('conflict', 'Conflict Merge Studio', 'Inspect and resolve authoritative draft conflicts without blind overwrite.', '<button type="button" class="button" data-tool="conflict">Inspect conflict</button>') +
		toolDetails('templates', 'Governed Template & Block Library', 'Load approved native templates; applying is always explicit.', '<button type="button" class="button" data-tool="templates">Load templates</button><button type="button" class="button" data-template-apply disabled>Apply returned template</button>') +
		toolDetails('command', 'Command Palette / Slash Commands', 'Keyboard-first navigation and actions.', '<button type="button" class="button" data-local-tool="command">Open command palette</button><small>Shortcut: Ctrl/⌘ + K</small>') +
		toolDetails('adaptive', 'Adaptive Composer Mode', 'Choose Quick or Advanced from risk, required fields and device.', '<button type="button" class="button" data-local-tool="adaptive">Re-evaluate mode</button>') +
		toolDetails('accessibility', 'Accessibility Coach', 'Check headings, links, images and tables locally.', '<button type="button" class="button" data-local-tool="accessibility">Run accessibility coach</button>') +
		toolDetails('media', 'Advanced Media Workbench Bridge', 'Local image rotate/crop before direct native upload; native module keeps bytes.', '<input type="file" accept="image/jpeg,image/png,image/webp" data-media-workbench-input><div class="supc-intel__media" hidden data-media-workbench><canvas data-media-canvas></canvas><div><button type="button" class="button" data-media-rotate>Rotate 90°</button><button type="button" class="button" data-media-crop>Toggle square crop</button><button type="button" class="button button-primary" data-media-send>Send to native upload</button></div></div>') +
		toolDetails('derivative', 'Cross-Format Derivative Studio', 'Generate human-approved derivatives without auto-publishing.', '<label>Target <select data-derivative-target><option value="summary">Summary</option><option value="reel_script">Reel script</option><option value="video_outline">Video outline</option><option value="lesson_abstract">Lesson abstract</option><option value="social_excerpt">Social excerpt</option></select></label><button type="button" class="button" data-tool="derivative">Generate derivative</button>') +
		toolDetails('readiness', 'Explainable Content Readiness Score', 'Advisory score from completeness, evidence, privacy and accessibility.', '<button type="button" class="button" data-local-tool="readiness">Calculate readiness</button>') +
		toolDetails('impact', 'Publication Impact Simulator', 'Ask the authoritative distribution provider what publishing would affect.', '<button type="button" class="button" data-tool="impact">Simulate impact</button>') +
		toolDetails('offline', 'Encrypted Offline Recovery', 'Device-bound encrypted recovery for low-risk drafts only.', '<button type="button" class="button" data-local-tool="offline">Check encrypted recovery</button><button type="button" class="button" data-recovery-restore hidden>Restore</button><button type="button" class="button" data-recovery-discard hidden>Discard</button>');
	rail.appendChild(panel);

	const evidenceAnalysis = () => {
		const raw = plainText();
		const sentences = raw.split(/(?<=[.!?؟])\s+/).filter((value) => value.trim().length > 25).slice(0, 80);
		const refPattern = /(\[[0-9]{1,3}\]|\([A-Z][A-Za-z-]+,?\s+20\d{2}\)|https?:\/\/|doi:|PMID|ISBN)/i;
		const cited = sentences.filter((sentence) => refPattern.test(sentence));
		const uncited = sentences.filter((sentence) => !refPattern.test(sentence));
		const sample = uncited.slice(0, 8).map((sentence) => '• ' + sentence.slice(0, 140)).join('\n');
		return { total: sentences.length, cited: cited.length, uncited: uncited.length, sample };
	};

	const privacyAnalysis = () => {
		const text = [titleText(), plainText(), JSON.stringify(snapshot())].join('\n').slice(0, 120000);
		const checks = {
			email: /\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b/gi,
			phone: /(?:\+?\d[\d\s().-]{7,}\d)/g,
			cnic_or_long_id: /\b\d{5}-?\d{7}-?\d\b/g,
			ip_address: /\b(?:\d{1,3}\.){3}\d{1,3}\b/g,
			date_of_birth_shape: /\b(?:DOB|date of birth|تاریخ پیدائش)\s*[:\-]?\s*\d{1,2}[\/\-.]\d{1,2}[\/\-.]\d{2,4}\b/gi
		};
		const findings = {};
		Object.keys(checks).forEach((key) => { findings[key] = (text.match(checks[key]) || []).length; });
		return findings;
	};

	const accessibilityAnalysis = () => {
		const template = document.createElement('template');
		template.innerHTML = richSource ? safeRichHtml(richSource.value) : '';
		const issues = [];
		template.content.querySelectorAll('img').forEach((img) => { if (!String(img.getAttribute('alt') || '').trim()) issues.push('Image missing alt text'); });
		template.content.querySelectorAll('a').forEach((link) => { if (/^(click here|read more|link|یہاں)$/i.test(String(link.textContent || '').trim())) issues.push('Non-descriptive link text'); });
		let previous = 1;
		template.content.querySelectorAll('h2,h3,h4').forEach((heading) => {
			const level = Number(heading.tagName.substring(1));
			if (level > previous + 1) issues.push('Heading level skips from H' + previous + ' to H' + level);
			previous = level;
			if (!String(heading.textContent || '').trim()) issues.push('Empty heading');
		});
		template.content.querySelectorAll('table').forEach((table) => { if (!table.querySelector('th')) issues.push('Table has no header cells'); });
		return Array.from(new Set(issues));
	};

	const readiness = () => {
		const required = Array.from(form.querySelectorAll('[data-supc-field][data-required="1"]')).filter((field) => field.name !== 'publication_action');
		const complete = required.filter((field) => {
			if (field.matches('[data-supc-rte-source]')) return Boolean(plainText());
			if (field.dataset.fieldType === 'checkbox') return field.checked;
			if (field.dataset.fieldType === 'multiselect') return field.selectedOptions && field.selectedOptions.length > 0;
			return Boolean(String(field.value || '').trim());
		}).length;
		const completeness = required.length ? Math.round((complete / required.length) * 30) : 30;
		const evidence = evidenceAnalysis();
		const evidenceScore = evidence.total ? Math.round((evidence.cited / evidence.total) * 20) : 10;
		const privacy = privacyAnalysis();
		const privacyCount = Object.values(privacy).reduce((sum, value) => sum + value, 0);
		const privacyScore = privacyCount === 0 ? 15 : Math.max(0, 15 - privacyCount * 3);
		const accessibilityIssues = accessibilityAnalysis();
		const accessibilityScore = Math.max(0, 15 - accessibilityIssues.length * 3);
		const safetyScore = root.querySelector('[data-supc-safety-summary]') && /passed|0 issue/i.test(root.querySelector('[data-supc-safety-summary]').textContent || '') ? 10 : 5;
		const metadataScore = titleText() && plainText().length > 80 ? 10 : 5;
		return { score: Math.min(100, completeness + evidenceScore + privacyScore + accessibilityScore + safetyScore + metadataScore), completeness, evidence: evidenceScore, privacy: privacyScore, accessibility: accessibilityScore, safety: safetyScore, metadata: metadataScore };
	};

	const runAdaptiveMode = () => {
		const risk = isSensitiveDraft() || Boolean(root.querySelector('[data-field-key*="medical"], [data-field-key*="patient"], [data-field-key*="copyright"], [data-field-key*="reference"]'));
		const target = risk ? 'advanced' : (window.matchMedia('(max-width: 700px)').matches ? 'quick' : 'advanced');
		const button = root.querySelector('[data-supc-mode="' + target + '"]');
		if (button && button.getAttribute('aria-pressed') !== 'true') button.click();
		setResult('adaptive', 'Mode: ' + target + '. Reason: ' + (risk ? 'risk/compliance fields require full controls.' : (target === 'quick' ? 'low-risk mobile authoring.' : 'desktop authoring.')), 'ready');
	};

	let recognition = null;
	const toggleVoice = () => {
		const Voice = window.SpeechRecognition || window.webkitSpeechRecognition;
		if (!Voice) {
			setResult('voice', 'Speech recognition is not available in this browser.', 'blocked');
			return;
		}
		if (recognition) { recognition.stop(); recognition = null; setResult('voice', 'Dictation stopped.', 'ready'); return; }
		recognition = new Voice();
		recognition.continuous = true;
		recognition.interimResults = false;
		recognition.lang = String(config.locale || document.documentElement.lang || 'en-US').replace('_', '-');
		recognition.onresult = (event) => {
			let text = '';
			for (let index = event.resultIndex; index < event.results.length; index += 1) if (event.results[index].isFinal) text += event.results[index][0].transcript + ' ';
			if (richEditor && text) {
				richEditor.focus();
				document.execCommand('insertText', false, text);
				richEditor.dispatchEvent(new Event('input', { bubbles: true }));
			}
		};
		recognition.onerror = (event) => setResult('voice', 'Dictation error: ' + event.error, 'error');
		recognition.onend = () => { recognition = null; };
		recognition.start();
		setResult('voice', 'Listening…', 'working');
	};

	const selectionMetadata = () => {
		const selection = window.getSelection();
		return selection ? { anchor_offset: selection.anchorOffset, focus_offset: selection.focusOffset, collapsed: selection.isCollapsed } : { anchor_offset: 0, focus_offset: 0, collapsed: true };
	};

	const commandDialog = document.createElement('dialog');
	commandDialog.className = 'supc-intel__palette';
	commandDialog.innerHTML = '<form method="dialog"><header><strong>Command Palette</strong><button value="cancel" aria-label="Close">×</button></header><div data-command-list></div></form>';
	document.body.appendChild(commandDialog);
	const commands = [
		['Save draft', () => root.querySelector('[data-supc-action="save"]') && root.querySelector('[data-supc-action="save"]').click()],
		['Run validation', () => root.querySelector('[data-supc-action="validate"]') && root.querySelector('[data-supc-action="validate"]').click()],
		['Open preview', () => root.querySelector('[data-supc-action="preview"]') && root.querySelector('[data-supc-action="preview"]').click()],
		['Go to Compose', () => root.querySelector('[data-supc-step-button="compose"]') && root.querySelector('[data-supc-step-button="compose"]').click()],
		['Go to References & Compliance', () => root.querySelector('[data-supc-step-button="compliance"]') && root.querySelector('[data-supc-step-button="compliance"]').click()],
		['Go to Publish', () => root.querySelector('[data-supc-step-button="publish"]') && root.querySelector('[data-supc-step-button="publish"]').click()],
		['Focus title', () => { const title = form.querySelector('[name="title"]'); if (title) title.focus(); }],
		['Open intelligence tools', () => { panel.scrollIntoView({ block: 'start' }); }]
	];
	const commandList = commandDialog.querySelector('[data-command-list]');
	commands.forEach((entry, index) => {
		const button = document.createElement('button');
		button.type = 'button'; button.className = 'button'; button.textContent = entry[0];
		button.addEventListener('click', () => { commandDialog.close(); commands[index][1](); });
		commandList.appendChild(button);
	});
	const openPalette = () => { if (typeof commandDialog.showModal === 'function') commandDialog.showModal(); else commandDialog.setAttribute('open', ''); };
	window.addEventListener('keydown', (event) => { if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'k') { event.preventDefault(); openPalette(); } });

	const renderImageWorkbench = async (file) => {
		const canvas = panel.querySelector('[data-media-canvas]');
		const wrap = panel.querySelector('[data-media-workbench]');
		if (!canvas || !wrap || !file) return;
		const bitmap = await createImageBitmap(file);
		mediaState = { file, bitmap, rotation: 0, square: false };
		wrap.hidden = false;
		const draw = () => {
			const sourceW = bitmap.width; const sourceH = bitmap.height;
			const cropSize = mediaState.square ? Math.min(sourceW, sourceH) : null;
			const sx = cropSize ? Math.floor((sourceW - cropSize) / 2) : 0;
			const sy = cropSize ? Math.floor((sourceH - cropSize) / 2) : 0;
			const sw = cropSize || sourceW; const sh = cropSize || sourceH;
			const rotated = mediaState.rotation % 180 !== 0;
			canvas.width = rotated ? sh : sw; canvas.height = rotated ? sw : sh;
			const ctx = canvas.getContext('2d');
			ctx.clearRect(0, 0, canvas.width, canvas.height);
			ctx.save(); ctx.translate(canvas.width / 2, canvas.height / 2); ctx.rotate(mediaState.rotation * Math.PI / 180);
			ctx.drawImage(bitmap, sx, sy, sw, sh, -sw / 2, -sh / 2, sw, sh); ctx.restore();
		};
		mediaState.draw = draw; draw();
	};

	const sendWorkbenchImage = async () => {
		if (!mediaState || !uploadInput) { setResult('media', 'Native upload is unavailable for this content type.', 'blocked'); return; }
		const canvas = panel.querySelector('[data-media-canvas]');
		const blob = await new Promise((resolve) => canvas.toBlob(resolve, mediaState.file.type === 'image/png' ? 'image/png' : 'image/jpeg', 0.9));
		if (!blob) return;
		const name = mediaState.file.name.replace(/\.[^.]+$/, '') + '-edited.' + (blob.type === 'image/png' ? 'png' : 'jpg');
		const transformed = new File([blob], name, { type: blob.type, lastModified: Date.now() });
		if (typeof DataTransfer !== 'undefined') {
			const transfer = new DataTransfer(); transfer.items.add(transformed); uploadInput.files = transfer.files; uploadInput.dispatchEvent(new Event('change', { bubbles: true }));
			setResult('media', 'Edited image handed directly to the native upload workflow.', 'ready');
		} else setResult('media', 'This browser cannot hand the edited file to the native uploader.', 'blocked');
	};

	const DB_NAME = 'supc-future-recovery-v1';
	const DB_VERSION = 1;
	const recoveryId = () => String(config.userId || 0) + ':' + String(config.adapter || root.dataset.adapter || 'unknown');
	const openDb = () => new Promise((resolve, reject) => {
		const requestDb = indexedDB.open(DB_NAME, DB_VERSION);
		requestDb.onupgradeneeded = () => {
			const db = requestDb.result;
			if (!db.objectStoreNames.contains('keys')) db.createObjectStore('keys');
			if (!db.objectStoreNames.contains('drafts')) db.createObjectStore('drafts');
		};
		requestDb.onsuccess = () => resolve(requestDb.result);
		requestDb.onerror = () => reject(requestDb.error);
	});
	const dbGet = async (store, key) => { const db = await openDb(); return new Promise((resolve, reject) => { const tx = db.transaction(store, 'readonly'); const req = tx.objectStore(store).get(key); req.onsuccess = () => resolve(req.result); req.onerror = () => reject(req.error); }); };
	const dbPut = async (store, key, value) => { const db = await openDb(); return new Promise((resolve, reject) => { const tx = db.transaction(store, 'readwrite'); tx.objectStore(store).put(value, key); tx.oncomplete = () => resolve(true); tx.onerror = () => reject(tx.error); }); };
	const dbDelete = async (store, key) => { const db = await openDb(); return new Promise((resolve, reject) => { const tx = db.transaction(store, 'readwrite'); tx.objectStore(store).delete(key); tx.oncomplete = () => resolve(true); tx.onerror = () => reject(tx.error); }); };
	const recoverySupported = () => Boolean(config.privacy && config.privacy.localEncryptedRecovery && window.crypto && crypto.subtle && window.indexedDB && !isSensitiveDraft());
	const getRecoveryKey = async () => {
		const id = recoveryId(); let key = await dbGet('keys', id);
		if (!key) { key = await crypto.subtle.generateKey({ name: 'AES-GCM', length: 256 }, false, ['encrypt', 'decrypt']); await dbPut('keys', id, key); }
		return key;
	};
	const persistEncryptedRecovery = async () => {
		if (!recoverySupported()) { await dbDelete('drafts', recoveryId()).catch(() => {}); return; }
		const payload = JSON.stringify({ fields: snapshot(), updated_at: new Date().toISOString() });
		const key = await getRecoveryKey(); const iv = crypto.getRandomValues(new Uint8Array(12));
		const cipher = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode(payload));
		await dbPut('drafts', recoveryId(), { iv: Array.from(iv), cipher: Array.from(new Uint8Array(cipher)), updated_at: new Date().toISOString() });
	};
	const readEncryptedRecovery = async () => {
		if (!recoverySupported()) return null;
		const record = await dbGet('drafts', recoveryId()); if (!record) return null;
		const key = await getRecoveryKey(); const plain = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: new Uint8Array(record.iv) }, key, new Uint8Array(record.cipher));
		return JSON.parse(new TextDecoder().decode(plain));
	};
	const applyRecovery = (recovered) => {
		if (!recovered || !recovered.fields) return;
		form.querySelectorAll('[data-supc-field]').forEach((field) => {
			if (!Object.prototype.hasOwnProperty.call(recovered.fields, field.name)) return;
			const value = recovered.fields[field.name];
			if (field.dataset.fieldType === 'checkbox') field.checked = Boolean(value);
			else if (field.dataset.fieldType === 'multiselect' && Array.isArray(value)) { const selected = new Set(value.map(String)); Array.from(field.options || []).forEach((option) => { option.selected = selected.has(option.value); }); }
			else field.value = value == null ? '' : String(value);
			if (field.matches('[data-supc-rte-source]') && richEditor) richEditor.innerHTML = safeRichHtml(field.value);
		});
		form.dispatchEvent(new Event('input', { bubbles: true }));
	};
	const showRecoveryState = async () => {
		const restore = panel.querySelector('[data-recovery-restore]'); const discard = panel.querySelector('[data-recovery-discard]');
		if (!recoverySupported()) { setResult('offline', isSensitiveDraft() ? 'Encrypted local recovery is disabled for sensitive/patient-shaped drafts.' : 'Encrypted recovery is unavailable in this browser.', 'blocked'); restore.hidden = true; discard.hidden = true; return; }
		try { const record = await dbGet('drafts', recoveryId()); restore.hidden = !record; discard.hidden = !record; setResult('offline', record ? 'Encrypted device-bound recovery is available from ' + record.updated_at : 'No encrypted recovery is currently stored.', 'ready'); } catch (error) { setResult('offline', 'Recovery storage could not be read.', 'error'); }
	};

	panel.querySelector('[data-local-tool="evidence"]').addEventListener('click', () => { const result = evidenceAnalysis(); setResult('evidence', 'Claims analyzed: ' + result.total + '\nCited/linked: ' + result.cited + '\nNeeds evidence review: ' + result.uncited + (result.sample ? '\n\nSample uncited claims:\n' + result.sample : ''), 'ready'); });
	panel.querySelector('[data-local-tool="privacy"]').addEventListener('click', () => { const result = privacyAnalysis(); const total = Object.values(result).reduce((sum, value) => sum + value, 0); setResult('privacy', (total ? 'Potential identifiers found. Review before publication.' : 'No configured identifier patterns were detected.') + '\n' + JSON.stringify(result, null, 2), total ? 'warning' : 'ready'); });
	panel.querySelector('[data-local-tool="voice"]').addEventListener('click', toggleVoice);
	panel.querySelector('[data-local-tool="command"]').addEventListener('click', openPalette);
	panel.querySelector('[data-local-tool="adaptive"]').addEventListener('click', runAdaptiveMode);
	panel.querySelector('[data-local-tool="accessibility"]').addEventListener('click', () => { const issues = accessibilityAnalysis(); setResult('accessibility', issues.length ? issues.map((issue) => '• ' + issue).join('\n') : 'No configured accessibility issues detected.', issues.length ? 'warning' : 'ready'); });
	panel.querySelector('[data-local-tool="readiness"]').addEventListener('click', () => { const result = readiness(); setResult('readiness', 'Readiness: ' + result.score + '/100\n' + JSON.stringify(result, null, 2) + '\nAdvisory only; this is not a ranking or publication permission.', 'ready'); });
	panel.querySelector('[data-local-tool="offline"]').addEventListener('click', showRecoveryState);

	bridgeTool(panel.querySelector('[data-tool="ai"]'), 'ai_copilot', () => ({ task: panel.querySelector('[data-ai-task]').value, title: titleText(), content: plainText() }));
	bridgeTool(panel.querySelector('[data-tool="terminology"]'), 'medical_terminology', () => ({ title: titleText(), content: plainText() }));
	bridgeTool(panel.querySelector('[data-tool="collaboration"]'), 'collaboration', () => ({ action: 'presence', cursor: selectionMetadata() }));
	bridgeTool(panel.querySelector('[data-tool="annotations"]'), 'review_annotations', () => ({ action: 'list', native_reference: snapshot().native_reference || '' }));
	bridgeTool(panel.querySelector('[data-tool="diff"]'), 'semantic_diff', () => ({ action: 'compare_current', current: snapshot() }));
	bridgeTool(panel.querySelector('[data-tool="conflict"]'), 'conflict_merge', () => ({ action: 'inspect', current: snapshot() }));
	bridgeTool(panel.querySelector('[data-tool="templates"]'), 'template_library', () => ({ action: 'list' }));
	bridgeTool(panel.querySelector('[data-tool="derivative"]'), 'cross_format_derivative', () => ({ target: panel.querySelector('[data-derivative-target]').value, title: titleText(), content: plainText() }));
	bridgeTool(panel.querySelector('[data-tool="impact"]'), 'publication_impact', () => ({ action: form.querySelector('[data-supc-publication-action]') ? form.querySelector('[data-supc-publication-action]').value : '', fields: snapshot() }));

	const templateApply = panel.querySelector('[data-template-apply]');
	panel.querySelector('[data-tool="templates"]').addEventListener('click', () => { templateApply.disabled = false; });
	templateApply.addEventListener('click', async () => {
		try {
			const response = await bridgeInvoke('template_library', { action: 'recommended', current: snapshot(), sensitive: isSensitiveDraft() });
			const returned = response && response.result ? response.result : {};
			const fields = returned.template && returned.template.fields ? returned.template.fields : returned.fields;
			if (!fields || typeof fields !== 'object') { setResult('templates', 'Provider did not return an applicable template.', 'warning'); return; }
			form.querySelectorAll('[data-supc-field]').forEach((field) => {
				if (!Object.prototype.hasOwnProperty.call(fields, field.name)) return;
				if (String(field.value || '').trim()) return;
				field.value = String(fields[field.name] == null ? '' : fields[field.name]);
				if (field.matches('[data-supc-rte-source]') && richEditor) richEditor.innerHTML = safeRichHtml(field.value);
			});
			form.dispatchEvent(new Event('input', { bubbles: true }));
			setResult('templates', 'Approved template applied only to empty fields. Review before saving.', 'ready');
		} catch (error) { setResult('templates', error.message || 'Template failed', 'error'); }
	});

	const mediaInput = panel.querySelector('[data-media-workbench-input]');
	mediaInput.addEventListener('change', () => { const file = mediaInput.files && mediaInput.files[0]; if (file) renderImageWorkbench(file).catch(() => setResult('media', 'Image could not be opened.', 'error')); });
	panel.querySelector('[data-media-rotate]').addEventListener('click', () => { if (mediaState) { mediaState.rotation = (mediaState.rotation + 90) % 360; mediaState.draw(); } });
	panel.querySelector('[data-media-crop]').addEventListener('click', () => { if (mediaState) { mediaState.square = !mediaState.square; mediaState.draw(); } });
	panel.querySelector('[data-media-send]').addEventListener('click', () => { sendWorkbenchImage().catch(() => setResult('media', 'Edited image could not be prepared.', 'error')); });

	panel.querySelector('[data-recovery-restore]').addEventListener('click', async () => { try { const recovered = await readEncryptedRecovery(); applyRecovery(recovered); setResult('offline', 'Encrypted recovery restored locally. Review and save to the native owner.', 'ready'); } catch (error) { setResult('offline', 'Encrypted recovery could not be decrypted.', 'error'); } });
	panel.querySelector('[data-recovery-discard]').addEventListener('click', async () => { await dbDelete('drafts', recoveryId()).catch(() => {}); showRecoveryState(); });

	form.addEventListener('input', () => {
		window.clearTimeout(recoveryTimer);
		if (recoverySupported()) recoveryTimer = window.setTimeout(() => persistEncryptedRecovery().catch(() => {}), 2500);
	});
	const autosave = root.querySelector('[data-supc-autosave-state]');
	if (autosave) new MutationObserver(() => { if (/saved|completed/i.test(autosave.textContent || '')) dbDelete('drafts', recoveryId()).catch(() => {}); }).observe(autosave, { childList: true, characterData: true, subtree: true });

	const initializeCapabilities = async () => {
		try {
			const data = await request('/future/capabilities/' + encodeURIComponent(config.adapter || root.dataset.adapter), 'GET');
			(data.local_capabilities || []).forEach((item) => localCapabilities.add(item));
			(data.bridge_capabilities || []).forEach((item) => bridgeCapabilities.add(item));
			const mapping = { ai: 'ai_copilot', terminology: 'medical_terminology', collaboration: 'collaboration', annotations: 'review_annotations', diff: 'semantic_diff', conflict: 'conflict_merge', templates: 'template_library', derivative: 'cross_format_derivative', impact: 'publication_impact' };
			Object.keys(mapping).forEach((tool) => {
				const button = panel.querySelector('[data-tool="' + tool + '"]');
				if (button && !bridgeCapabilities.has(mapping[tool])) { button.disabled = true; button.title = (config.strings && config.strings.providerUnavailable) || 'Provider unavailable'; setResult(tool, 'Bridge coded; authorized provider not currently registered.', 'unavailable'); }
			});
		} catch (error) {
			panel.querySelectorAll('[data-tool]').forEach((button) => { button.disabled = true; });
		}
	};

	const collaborationDetails = panel.querySelector('[data-future-tool="collaboration"]');
	collaborationDetails.addEventListener('toggle', () => {
		window.clearInterval(collaborationTimer);
		if (collaborationDetails.open && bridgeCapabilities.has('collaboration')) {
			collaborationTimer = window.setInterval(() => {
				if (document.visibilityState === 'visible') bridgeInvoke('collaboration', { action: 'presence', cursor: selectionMetadata(), sensitive: isSensitiveDraft() }).then((response) => setResult('collaboration', resultText(response), 'ready')).catch(() => {});
			}, 20000);
		}
	});

	runAdaptiveMode();
	showRecoveryState().catch(() => {});
	initializeCapabilities();
}());
