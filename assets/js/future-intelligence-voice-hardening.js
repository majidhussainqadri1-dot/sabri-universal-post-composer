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

	const oldButton = panel.querySelector('[data-local-tool="voice"]');
	if (!oldButton) return;
	const button = oldButton.cloneNode(true);
	oldButton.replaceWith(button);
	let recognition = null;
	let target = null;

	const sensitive = () => Boolean(root.querySelector('[data-supc-field][data-privacy="sensitive"], .supc-workflow__field[data-privacy="sensitive"], [data-field-key*="patient"], [data-field-key*="consent"], [data-field-key*="clinical_case"], [data-field-key*="successful_case"], [data-field-key*="guardian"], [data-field-key*="credential"]'));
	const sensitiveVoiceAllowed = () => Boolean(config.privacy && config.privacy.sensitiveVoiceAllowed);
	const setResult = (message, state) => {
		const node = panel.querySelector('[data-future-result="voice"]');
		if (!node) return;
		node.textContent = String(message || '');
		node.dataset.status = state || 'ready';
	};
	const isEditableField = (node) => node && node.matches && node.matches('[data-supc-field]:not([type="hidden"]):not([type="checkbox"]):not(select), [data-supc-rte]');
	root.addEventListener('focusin', (event) => {
		if (isEditableField(event.target)) target = event.target;
	});

	const defaultTarget = () => {
		if (isEditableField(target)) return target;
		const title = form.querySelector('[name="title"]');
		if (isEditableField(title)) return title;
		if (editor) return editor;
		return form.querySelector('textarea[data-supc-field], input[type="text"][data-supc-field]');
	};
	const insert = (text) => {
		const current = defaultTarget();
		if (!current || !text) return false;
		if (current.matches('[data-supc-rte]')) {
			current.focus();
			document.execCommand('insertText', false, text);
			current.dispatchEvent(new Event('input', { bubbles: true }));
			return true;
		}
		if ('value' in current) {
			const value = String(current.value || '');
			const start = typeof current.selectionStart === 'number' ? current.selectionStart : value.length;
			const end = typeof current.selectionEnd === 'number' ? current.selectionEnd : start;
			current.value = value.slice(0, start) + text + value.slice(end);
			const caret = start + text.length;
			if (typeof current.setSelectionRange === 'function') current.setSelectionRange(caret, caret);
			current.dispatchEvent(new Event('input', { bubbles: true }));
			return true;
		}
		return false;
	};

	const stop = (message, state) => {
		if (recognition) {
			try { recognition.stop(); } catch (error) { /* Browser may already have stopped it. */ }
		}
		recognition = null;
		button.setAttribute('aria-pressed', 'false');
		setResult(message || 'Dictation stopped. No transcript is retained by File 22. Review inserted text before saving.', state || 'ready');
	};
	button.setAttribute('aria-pressed', 'false');
	button.addEventListener('click', () => {
		if (recognition) { stop(); return; }
		if (sensitive() && !sensitiveVoiceAllowed()) {
			setResult('Voice dictation is disabled for sensitive/patient-shaped drafts unless the governing privacy owner explicitly authorizes the browser speech service.', 'blocked');
			return;
		}
		const Voice = window.SpeechRecognition || window.webkitSpeechRecognition;
		if (!Voice) {
			setResult('Speech recognition is not available in this browser.', 'blocked');
			return;
		}
		target = defaultTarget();
		if (!target) {
			setResult('Focus a writable Composer field before starting dictation.', 'warning');
			return;
		}
		recognition = new Voice();
		recognition.continuous = true;
		recognition.interimResults = false;
		recognition.lang = String(config.locale || document.documentElement.lang || 'en-US').replace('_', '-');
		recognition.onresult = (event) => {
			// Revalidate immediately before every transcript insertion. The native
			// workflow can become sensitive after dictation starts, and an earlier
			// browser decision must never become a stale privacy authorization.
			if (sensitive() && !sensitiveVoiceAllowed()) {
				stop('Dictation stopped before transcript insertion because this workflow is now sensitive and the speech-service privacy owner has not opted in.', 'blocked');
				return;
			}
			let text = '';
			for (let index = event.resultIndex; index < event.results.length; index += 1) {
				if (event.results[index].isFinal) text += event.results[index][0].transcript + ' ';
			}
			if (text && !insert(text)) setResult('The transcript could not be inserted into the selected Composer field.', 'error');
		};
		recognition.onerror = (event) => setResult('Dictation error: ' + String(event.error || 'unknown'), 'error');
		recognition.onend = () => {
			recognition = null;
			button.setAttribute('aria-pressed', 'false');
		};
		try {
			recognition.start();
			button.setAttribute('aria-pressed', 'true');
			setResult('Listening into the currently selected Composer field. Your browser or speech vendor may process audio; File 22 does not store the audio/transcript as a separate record.', 'working');
		} catch (error) {
			recognition = null;
			button.setAttribute('aria-pressed', 'false');
			setResult('Dictation could not be started.', 'error');
		}
	});

	window.addEventListener('pagehide', () => stop('Dictation stopped because the Composer page is leaving.', 'ready'), { once: true });
	if (source && editor) {
		// Keep the stable source authoritative when dictating into rich text.
		editor.addEventListener('input', () => { if (recognition) source.dispatchEvent(new Event('input', { bubbles: true })); });
	}
}());