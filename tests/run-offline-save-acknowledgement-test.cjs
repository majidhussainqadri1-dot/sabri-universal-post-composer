'use strict';

/**
 * Exercised source: the actual save() helper from assets/js/workflow-composer.js.
 * No browser globals, user data, HTTP requests or real IndexedDB are involved.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const js = fs.readFileSync(path.resolve(__dirname, '../assets/js/workflow-composer.js'), 'utf8');
const start = js.indexOf('\tconst save = async (silent) => {');
const end = js.indexOf('\n\tconst setStep =', start);
assert.ok(start !== -1 && end > start, 'save() source boundary must be present');
const functionSource = js.slice(start, end);

const strings = {
    offlineStored: 'OFFLINE_SAVED',
    offlineRecoveryUnavailable: 'OFFLINE_UNAVAILABLE',
    notSaved: 'NOT_SAVED',
    saving: 'SAVING',
    saved: 'SAVED',
    syncing: 'SYNCING',
    conflict: 'CONFLICT',
};
const config = {strings};

function harness({online = false, supported = true, stored = false, dirty = true, session = null, persistenceAnnouncement = null} = {}) {
    const messages = [];
    const statuses = [];
    const operations = [];
    const deps = {
        navigator: {onLine: online},
        session,
        dirty,
        strings,
        config,
        recoverySupported: supported,
        setAutosaveState: state => { statuses.push(state); },
        setConnection: state => { operations.push('connection:' + state); },
        announce: message => { messages.push(message); },
        async persistOfflineRecovery() {
            operations.push('persist');
            if (persistenceAnnouncement) messages.push(strings[persistenceAnnouncement]);
            return stored;
        },
        async run(operation) {
            operations.push('run:' + operation);
            return {session: {native_reference: 'draft-1'}};
        },
        async purgeRecovery() { operations.push('purge'); },
        updateDraftPreview() { operations.push('preview'); },
        evaluateConnection() { operations.push('evaluate'); },
    };
    const names = Object.keys(deps);
    const save = new Function(...names, functionSource + '\nreturn save;')(...Object.values(deps));
    return {save, statuses, operations, messages};
}

async function caseUnsupportedStorageMustNeverClaimSaved() {
    const h = harness({supported: false, stored: false});
    assert.equal(await h.save(true), false);
    assert.deepEqual(h.statuses, ['Offline', 'Not saved']);
    assert.deepEqual(h.messages, ['OFFLINE_UNAVAILABLE']);
    assert.deepEqual(h.operations, ['persist']);
}

async function caseStorageFailureReportsOnlyFailure() {
    const h = harness({supported: true, stored: false, persistenceAnnouncement: 'offlineRecoveryUnavailable'});
    assert.equal(await h.save(true), false);
    assert.deepEqual(h.statuses, ['Offline', 'Not saved']);
    assert.deepEqual(h.messages, ['OFFLINE_UNAVAILABLE']);
    assert.equal(h.messages.includes('OFFLINE_SAVED'), false);
}

async function caseCommittedLocalSaveAnnouncesExactlyOnce() {
    const h = harness({supported: true, stored: true, persistenceAnnouncement: 'offlineStored'});
    assert.equal(await h.save(true), false); // server remains offline; local recovery alone is not native save
    assert.deepEqual(h.messages, ['OFFLINE_SAVED']);
    assert.deepEqual(h.statuses, ['Offline']);
}

async function caseCleanNativeDraftDoesNotResaveOffline() {
    const h = harness({session: {native_reference: 'draft-2'}, dirty: false});
    assert.equal(await h.save(true), true);
    assert.deepEqual(h.operations, []);
    assert.deepEqual(h.messages, []);
}

async function caseOnlineNativeSaveStillAcknowledgesServerSuccess() {
    const h = harness({online: true});
    assert.equal(await h.save(true), true);
    assert.deepEqual(h.operations, ['connection:syncing', 'run:autosave', 'purge', 'preview', 'evaluate']);
    assert.deepEqual(h.messages, ['SAVED']);
    assert.ok(h.statuses.includes('Saved'));
}

(async () => {
    await caseUnsupportedStorageMustNeverClaimSaved();
    await caseStorageFailureReportsOnlyFailure();
    await caseCommittedLocalSaveAnnouncesExactlyOnce();
    await caseCleanNativeDraftDoesNotResaveOffline();
    await caseOnlineNativeSaveStillAcknowledgesServerSuccess();
    console.log('PASS: 5 offline/online save acknowledgement cases');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
