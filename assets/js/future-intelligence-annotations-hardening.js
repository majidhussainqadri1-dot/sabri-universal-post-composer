(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	const config = window.SUPCFuture;
	if (!root || !config) return;
	const panel = root.querySelector('.supc-intel');
	if (!panel) return;

	const restRoot = String(config.restRoot || '').replace(/\/+$/, '');
	const sessionUuid = () => new URL(window.location.href).searchParams.get('session') || '';
	const adapterKey = () => String(config.adapter || root.dataset.adapter || '');
	const setResult = (message, state) => {
		const node = panel.querySelector('[data-future-result="annotations"]');
		if (!node) return;
		node.textContent = String(message || '');
		node.dataset.status = state || 'ready';
	};
	const resolutionConfirmed = (data, annotationId) => {
		const result = data && data.result && typeof data.result === 'object' && !Array.isArray(data.result) ? data.result : null;
		if (!result) return false;
		const confirmed = result.resolved === true || String(result.status || '').toLowerCase() === 'resolved';
		if (!confirmed) return false;
		if (Object.prototype.hasOwnProperty.call(result, 'annotation_id')) {
			return String(result.annotation_id || '') === String(annotationId);
		}
		return true;
	};
	const resolveAnnotation = async (annotationId) => {
		const response = await fetch(restRoot + '/future/invoke', {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
			body: JSON.stringify({
				adapter_key: adapterKey(),
				capability: 'review_annotations',
				session_uuid: sessionUuid(),
				payload: { action: 'resolve', annotation_id: annotationId }
			})
		});
		const data = await response.json().catch(() => ({}));
		if (!response.ok) throw new Error(data.message || 'Annotation could not be resolved.');
		if (!resolutionConfirmed(data, annotationId)) throw new Error('The native review owner did not confirm that this annotation is resolved.');
		return data;
	};

	const enhanceMarker = (marker) => {
		if (!marker || marker.dataset.resolveReady === '1') return;
		marker.dataset.resolveReady = '1';
		const annotationId = String(marker.dataset.annotationId || '').trim();
		if (!annotationId || !/^[A-Za-z0-9._:-]{1,128}$/.test(annotationId)) return;
		const button = document.createElement('button');
		button.type = 'button';
		button.className = 'button button-small supc-intel-annotation-marker__resolve';
		button.textContent = 'Mark resolved';
		button.setAttribute('aria-label', 'Mark this reviewer annotation resolved');
		button.addEventListener('click', async () => {
			button.disabled = true;
			button.setAttribute('aria-busy', 'true');
			try {
				await resolveAnnotation(annotationId);
				const parent = marker.parentElement;
				marker.remove();
				if (parent && !parent.querySelector('.supc-intel-annotation-marker')) parent.classList.remove('has-supc-intel-annotation');
				setResult('Reviewer annotation resolution was confirmed by the native review owner.', 'ready');
			} catch (error) {
				setResult(error.message || 'Annotation could not be resolved.', 'error');
				button.disabled = false;
				button.setAttribute('aria-busy', 'false');
			}
		});
		marker.appendChild(button);
	};

	root.querySelectorAll('.supc-intel-annotation-marker').forEach(enhanceMarker);
	new MutationObserver((mutations) => {
		mutations.forEach((mutation) => mutation.addedNodes.forEach((node) => {
			if (!(node instanceof Element)) return;
			if (node.matches('.supc-intel-annotation-marker')) enhanceMarker(node);
			node.querySelectorAll && node.querySelectorAll('.supc-intel-annotation-marker').forEach(enhanceMarker);
		}));
	}).observe(root, { childList: true, subtree: true });
}());