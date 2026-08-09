(function () {
	'use strict';

	const root = document.querySelector('[data-supc-workflow]');
	if (!root) return;
	const panel = root.querySelector('.supc-intel');
	const nativeUpload = root.querySelector('[data-supc-upload-input]');
	if (!panel) return;

	const oldInput = panel.querySelector('[data-media-workbench-input]');
	const oldRotate = panel.querySelector('[data-media-rotate]');
	const oldCrop = panel.querySelector('[data-media-crop]');
	const oldSend = panel.querySelector('[data-media-send]');
	const canvas = panel.querySelector('[data-media-canvas]');
	const wrap = panel.querySelector('[data-media-workbench]');
	if (!oldInput || !oldRotate || !oldCrop || !oldSend || !canvas || !wrap) return;

	const input = oldInput.cloneNode(true);
	const rotate = oldRotate.cloneNode(true);
	const crop = oldCrop.cloneNode(true);
	const send = oldSend.cloneNode(true);
	oldInput.replaceWith(input);
	oldRotate.replaceWith(rotate);
	oldCrop.replaceWith(crop);
	oldSend.replaceWith(send);

	const MAX_FILE_BYTES = 25 * 1024 * 1024;
	const MAX_DIMENSION = 12000;
	const MAX_PIXELS = 40000000;
	const ALLOWED = new Set(['image/jpeg', 'image/png', 'image/webp']);
	let state = null;

	const setResult = (message, status) => {
		const node = panel.querySelector('[data-future-result="media"]');
		if (!node) return;
		node.textContent = String(message || '');
		node.dataset.status = status || 'ready';
	};
	const release = () => {
		if (state && state.bitmap && typeof state.bitmap.close === 'function') state.bitmap.close();
		state = null;
		wrap.hidden = true;
	};
	const draw = () => {
		if (!state) return;
		const bitmap = state.bitmap;
		const sourceW = bitmap.width;
		const sourceH = bitmap.height;
		const cropSize = state.square ? Math.min(sourceW, sourceH) : null;
		const sx = cropSize ? Math.floor((sourceW - cropSize) / 2) : 0;
		const sy = cropSize ? Math.floor((sourceH - cropSize) / 2) : 0;
		const sw = cropSize || sourceW;
		const sh = cropSize || sourceH;
		const rotated = state.rotation % 180 !== 0;
		canvas.width = rotated ? sh : sw;
		canvas.height = rotated ? sw : sh;
		const context = canvas.getContext('2d', { alpha: state.file.type !== 'image/jpeg' });
		if (!context) throw new Error('Canvas unavailable');
		context.clearRect(0, 0, canvas.width, canvas.height);
		context.save();
		context.translate(canvas.width / 2, canvas.height / 2);
		context.rotate(state.rotation * Math.PI / 180);
		context.drawImage(bitmap, sx, sy, sw, sh, -sw / 2, -sh / 2, sw, sh);
		context.restore();
	};
	const open = async (file) => {
		release();
		if (!file || !ALLOWED.has(String(file.type || '').toLowerCase())) throw new Error('Only JPEG, PNG and WebP images are accepted by this local workbench.');
		if (file.size <= 0 || file.size > MAX_FILE_BYTES) throw new Error('Image is empty or exceeds the 25 MB local workbench safety limit.');
		if (typeof createImageBitmap !== 'function') throw new Error('This browser cannot safely decode the image workbench preview.');
		const bitmap = await createImageBitmap(file);
		if (!bitmap.width || !bitmap.height || bitmap.width > MAX_DIMENSION || bitmap.height > MAX_DIMENSION || bitmap.width * bitmap.height > MAX_PIXELS) {
			if (typeof bitmap.close === 'function') bitmap.close();
			throw new Error('Decoded image exceeds the 12,000px / 40-megapixel local workbench safety limit.');
		}
		state = { file, bitmap, rotation: 0, square: false };
		wrap.hidden = false;
		draw();
		setResult('Local image decoded within bounded workbench limits. Native owner still performs authoritative MIME, decode, size, scan, metadata and rights validation.', 'ready');
	};
	const sendNative = async () => {
		if (!state || !nativeUpload) throw new Error('Native upload workflow is unavailable for this content type.');
		const mime = state.file.type;
		const blob = await new Promise((resolve) => canvas.toBlob(resolve, mime, 0.9));
		if (!blob || !ALLOWED.has(blob.type)) throw new Error('The edited image could not be encoded safely.');
		if (blob.size > MAX_FILE_BYTES) throw new Error('Edited image exceeds the local handoff safety limit.');
		const extension = blob.type === 'image/png' ? 'png' : (blob.type === 'image/webp' ? 'webp' : 'jpg');
		const base = String(state.file.name || 'image').replace(/\.[^.]+$/, '').replace(/[^A-Za-z0-9._-]+/g, '-').slice(0, 120) || 'image';
		const file = new File([blob], base + '-edited.' + extension, { type: blob.type, lastModified: Date.now() });
		if (typeof DataTransfer === 'undefined') throw new Error('This browser cannot hand the edited image to the native uploader.');
		const transfer = new DataTransfer();
		transfer.items.add(file);
		nativeUpload.files = transfer.files;
		nativeUpload.dispatchEvent(new Event('change', { bubbles: true }));
		setResult('Edited image handed directly to the authoritative native upload workflow; File 22 did not persist media bytes.', 'ready');
		release();
	};

	input.addEventListener('change', () => {
		const file = input.files && input.files[0];
		if (!file) { release(); return; }
		open(file).catch((error) => { release(); setResult(error.message || 'Image could not be opened.', 'error'); });
	});
	rotate.addEventListener('click', () => {
		if (!state) return;
		state.rotation = (state.rotation + 90) % 360;
		try { draw(); } catch (error) { release(); setResult('Image rendering failed closed.', 'error'); }
	});
	crop.addEventListener('click', () => {
		if (!state) return;
		state.square = !state.square;
		try { draw(); } catch (error) { release(); setResult('Image rendering failed closed.', 'error'); }
	});
	send.addEventListener('click', () => sendNative().catch((error) => setResult(error.message || 'Edited image could not be prepared.', 'error')));
	window.addEventListener('pagehide', release, { once: true });
}());
