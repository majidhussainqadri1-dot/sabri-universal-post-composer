'use strict';

/**
 * Dependency-free behavioural regression for the real IndexedDB helper.
 * This evaluates ONLY the extracted helper from the checked-out JS source.
 * No DOM, browser credentials, HTTP, or user records are used.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const file = path.resolve(__dirname, '../assets/js/workflow-composer.js');
const source = fs.readFileSync(file, 'utf8');
const startMarker = '\tconst recoveryStore = async (storeName, mode, operation) => {';
const nextMarker = '\n\tconst recoveryKey = async () => {';
const start = source.indexOf(startMarker);
const end = source.indexOf(nextMarker, start);
assert.ok(start !== -1 && end > start, 'Real recoveryStore helper must remain extractable');
const helperSource = source.slice(start, end);
const flush = () => new Promise((resolve) => setImmediate(resolve));

function eventTarget() {
    const listeners = new Map();
    return {
        addEventListener(type, callback) {
            if (!listeners.has(type)) listeners.set(type, []);
            listeners.get(type).push(callback);
        },
        emit(type) {
            for (const fn of listeners.get(type) || []) fn();
        }
    };
}

function harness() {
    const request = Object.assign(eventTarget(), {result: {saved: true}, error: null});
    const transaction = Object.assign(eventTarget(), {
        error: null,
        abortCalls: 0,
        objectStore() {
            return {put: () => request, get: () => request, delete: () => request};
        },
        abort() {
            this.abortCalls += 1;
            this.emit('abort');
        }
    });
    const db = {
        closeCalls: 0,
        transaction(name, mode) {
            assert.equal(name, 'drafts');
            assert.ok(['readonly', 'readwrite'].includes(mode));
            return transaction;
        },
        close() { this.closeCalls += 1; }
    };
    const recoveryStore = new Function('openRecoveryDb', helperSource + '\nreturn recoveryStore;')(
        async () => db
    );
    return {request, transaction, db, recoveryStore};
}

async function testRequestSuccessIsNotDurableSave() {
    const h = harness();
    let settled = false;
    const pending = h.recoveryStore('drafts', 'readwrite', store => store.put({cipher: 'encrypted'}));
    pending.finally(() => { settled = true; });
    await flush();
    h.request.emit('success');
    await flush();
    assert.equal(settled, false, 'request success must NOT report durable save');
    assert.equal(h.db.closeCalls, 0, 'database must remain open until commit');
    h.transaction.emit('complete');
    assert.deepEqual(await pending, {saved: true});
    assert.equal(h.db.closeCalls, 1);
}

async function testLateAbortOverridesRequestSuccess() {
    const h = harness();
    const pending = h.recoveryStore('drafts', 'readwrite', store => store.put({cipher: 'encrypted'}));
    await flush();
    h.request.emit('success');
    h.transaction.error = new Error('quota_or_disk_abort');
    h.transaction.emit('abort');
    await assert.rejects(pending, /quota_or_disk_abort/);
    assert.equal(h.db.closeCalls, 1, 'close after failed transaction');
}

async function testRequestErrorCannotBecomeCommitSuccess() {
    const h = harness();
    const pending = h.recoveryStore('drafts', 'readwrite', store => store.put({cipher: 'encrypted'}));
    await flush();
    h.request.error = new Error('request_failed');
    h.request.emit('error');
    h.transaction.emit('complete');
    await assert.rejects(pending, /request_failed/);
}

async function testOperationFailureAbortsTransaction() {
    const h = harness();
    await assert.rejects(
        h.recoveryStore('drafts', 'readwrite', () => { throw new Error('operation_failed'); }),
        /operation_failed/
    );
    assert.equal(h.transaction.abortCalls, 1);
    assert.equal(h.db.closeCalls, 1);
}

async function testRequestlessOperationWaitsForCommit() {
    const h = harness();
    let settled = false;
    const pending = h.recoveryStore('drafts', 'readwrite', () => 'requestless');
    pending.finally(() => { settled = true; });
    await flush();
    assert.equal(settled, false);
    h.transaction.emit('complete');
    assert.equal(await pending, 'requestless');
}

(async () => {
    await testRequestSuccessIsNotDurableSave();
    await testLateAbortOverridesRequestSuccess();
    await testRequestErrorCannotBecomeCommitSuccess();
    await testOperationFailureAbortsTransaction();
    await testRequestlessOperationWaitsForCommit();
    console.log('PASS: 5 IndexedDB transaction-completion recovery behaviours');
})().catch(error => {
    console.error(error);
    process.exitCode = 1;
});
