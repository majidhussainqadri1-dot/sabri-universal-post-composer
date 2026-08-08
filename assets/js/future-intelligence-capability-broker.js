(function () {
	'use strict';

	// Base and advanced Composer layers both need the same read-only capability
	// discovery during bootstrap. Coalesce only that exact GET for five seconds.
	// Mutating /future/invoke requests are never cached, and every invocation is
	// still re-authorized by the server/native owner.
	if (typeof window.fetch !== 'function' || typeof window.Response !== 'function') return;
	const nativeFetch = window.fetch.bind(window);
	const cache = new Map();
	const TTL_MS = 5000;

	const capabilityKey = (input, init) => {
		const method = String(init && init.method || (input && input.method) || 'GET').toUpperCase();
		if (method !== 'GET') return '';
		const raw = typeof input === 'string' ? input : (input && input.url ? input.url : '');
		if (!raw) return '';
		try {
			const url = new URL(raw, window.location.href);
			if (url.origin !== window.location.origin || !/\/future\/capabilities\/[A-Za-z0-9._-]+\/?$/.test(url.pathname)) return '';
			return url.href;
		} catch (error) {
			return '';
		}
	};

	window.fetch = async function (input, init) {
		const key = capabilityKey(input, init);
		if (!key) return nativeFetch(input, init);
		const now = Date.now();
		const cached = cache.get(key);
		if (cached && now - cached.created < TTL_MS) {
			const value = await cached.promise;
			return new Response(value.body, { status: value.status, statusText: value.statusText, headers: value.headers });
		}
		const promise = nativeFetch(input, init).then(async (response) => ({
			body: await response.clone().text(),
			status: response.status,
			statusText: response.statusText,
			headers: Array.from(response.headers.entries())
		})).catch((error) => {
			cache.delete(key);
			throw error;
		});
		cache.set(key, { created: now, promise });
		const value = await promise;
		return new Response(value.body, { status: value.status, statusText: value.statusText, headers: value.headers });
	};
}());
