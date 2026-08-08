(function () {
	'use strict';

	// Deliberately do not replace window.fetch. File 22 shares the create page
	// with native modules and the global application shell; monkey-patching the
	// browser fetch primitive would create an unnecessary cross-module side
	// effect. Capability discovery is read-only, authenticated, nonce-protected,
	// no-store, and excluded from the mutation rate budget, so each Composer
	// layer may perform its own bounded discovery request without changing the
	// networking semantics of unrelated code on the page.
	window.SUPCFutureCapabilityBroker = Object.freeze({
		mode: 'native-fetch',
		globalFetchPatched: false
	});
}());
