(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	const config = window.SUPCFuture;
	if (!root || !config) return;
	const form = root.querySelector('[data-supc-form]');
	const panel = root.querySelector('.supc-intel');
	const editor = root.querySelector('[data-supc-rte]');
	const source = root.querySelector('[data-supc-rte-source]');
	const oldButton = panel ? panel.querySelector('[data-local-tool="voice"]') : null;
	if (!form || !panel || !oldButton) return;

	const button = oldButton.cloneNode(true);
	oldButton.replaceWith(button);
	const MAX_FIELD_LENGTH = 131072;
	const protectedName = /(?:native_reference|publication_action|consent|privacy_confirm|medical_disclaimer_confirm|copyright_declaration|rights_declaration|verification|capability|moderation|status|guardian|credential|identity_evidence|author_id|effective_author|patient_id|medical_record)/i;
	let recognition = null;
	let target = null;

	const setResult = (message, state) => {
		const node = panel.querySelector('[data-future-result="voice"]');
		if (!node) return;
		node.textContent = String(message || '');
		node.dataset.status = state || 'ready';
	};
	const sourceIsProtected = () => !source || !source.name || source.dataset.privacy === 'sensitive' || source.dataset.fieldType === 'opaque_reference' || protectedName.test(String(source.name));
	const isPermittedTarget = (node) => {
		if (!node || !node.matches) return false;
		if (node.matches('[data-supc-rte]')) return node === editor && !sourceIsProtected();
		if (!node.matches('textarea[data-supc-field], input[type="text"][data-supc-field], input[type="search"][data-supc-field]')) return false;
		return Boolean(node.name) && node.dataset.privacy !== 'sensitive' && node.dataset.fieldType !== 'opaque_reference' && !protectedName.test(String(node.name));
	};
	const sensitiveWorkflow = () => String(config.adapterPrivacyClassification || '').toLowerCase() === 'sensitive' || Boolean(root.querySelector('[data-supc-field][data-privacy="sensitive"], [data-field-key*="patient"], [data-field-key*="consent"], [data-field-key*="clinical_case"], [data-field-key*="successful_case"], [data-field-key*="guardian"], [data-field-key*="credential"]'));
	const allowed = () => !sensitiveWorkflow() || Boolean(config.privacy && config.privacy.sensitiveVoiceAllowed);
	const stop = (message, state) => {
		if (recognition) {
			try { recognition.stop(); } catch (error) { /* Browser already stopped. */ }
		}
		recognition = null;
		button.setAttribute('aria-pressed', 'false');
		if (message) setResult(message, state || 'ready');
	};

	root.addEventListener('focusin', (event) => {
		if (isPermittedTarget(event.target)) target = event.target;
	});
	button.setAttribute('aria-pressed', 'false');
	button.addEventListener('click', () => {
		if (recognition) return stop('Dictation stopped. File 22 retained no separate audio/transcript record.', 'ready');
		if (!allowed()) return setResult('Voice dictation is blocked for this sensitive workflow unless the governing privacy owner explicitly opts in.', 'blocked');
		const Voice = window.SpeechRecognition || window.webkitSpeechRecognition;
		if (!Voice) return setResult('Speech recognition is not available in this browser.', 'blocked');
		const current = isPermittedTarget(target) ? target : (isPermittedTarget(editor) ? editor : form.querySelector('textarea[data-supc-field], input[type="text"][data-supc-field]'));
		if (!isPermittedTarget(current)) return setResult('Focus a permitted text field before starting dictation.', 'warning');
		target = current;
		recognition = new Voice();
		recognition.continuous = true;
		recognition.interimResults = false;
		recognition.lang = String(config.locale || document.documentElement.lang || 'en-US').replace('_', '-');
		recognition.onresult = (event) => {
			if (!allowed() || !isPermittedTarget(target)) return stop('Dictation stopped before insertion because the workflow/target is no longer permitted.', 'blocked');
			let text = '';
			for (let index = event.resultIndex; index < event.results.length; index += 1) if (event.results[index].isFinal) text += event.results[index][0].transcript + ' ';
			if (!text) return;
			if (target === editor) {
				const max = source.maxLength > 0 ? Math.min(source.maxLength, MAX_FIELD_LENGTH) : MAX_FIELD_LENGTH;
				if (String(editor.textContent || '').length + text.length > max) return stop('Dictation stopped because the complete transcript would exceed this field limit; no partial transcript was inserted.', 'warning');
				editor.focus();
				document.execCommand('insertText', false, text);
				editor.dispatchEvent(new Event('input', { bubbles: true }));
				return;
			}
			const value = String(target.value || '');
			const start = typeof target.selectionStart === 'number' ? target.selectionStart : value.length;
			const end = typeof target.selectionEnd === 'number' ? target.selectionEnd : start;
			const next = value.slice(0, start) + text + value.slice(end);
			const max = target.maxLength > 0 ? Math.min(target.maxLength, MAX_FIELD_LENGTH) : MAX_FIELD_LENGTH;
			if (next.length > max) return stop('Dictation stopped because the complete transcript would exceed this field limit; no partial transcript was inserted.', 'warning');
			target.value = next;
			target.dispatchEvent(new Event('input', { bubbles: true }));
		};
		recognition.onerror = (event) => setResult('Dictation error: ' + String(event.error || 'unknown'), 'error');
		recognition.onend = () => { recognition = null; button.setAttribute('aria-pressed', 'false'); };
		try {
			recognition.start();
			button.setAttribute('aria-pressed', 'true');
			setResult('Listening into the selected permitted text field. Your browser/speech vendor may process audio; File 22 creates no separate audio/transcript record.', 'working');
		} catch (error) { stop('Dictation could not be started.', 'error'); }
	});
	window.addEventListener('pagehide', () => stop('', 'ready'), { once: true });
}());
