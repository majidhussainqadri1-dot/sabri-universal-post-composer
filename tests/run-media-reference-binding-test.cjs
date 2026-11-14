'use strict';

/**
 * Execute the exact native upload-reference binding helpers, not copies.
 * No DOM, network, files, patient records or authenticated site required.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.resolve(__dirname, '../assets/js/workflow-composer.js'), 'utf8');
const startMarker = '\tconst compatibleMediaReferenceField = (field, mimeType) => {';
const endMarker = '\n\tconst uploadFile = async (file) => {';
const start = source.indexOf(startMarker);
const end = source.indexOf(endMarker, start);
assert.ok(start >= 0 && end > start, 'Native binding helper is present in the Composer JS');
const helper = source.slice(start, end);

function harness(fields) {
  const form = {querySelectorAll(selector) {
    assert.equal(selector, '[data-supc-opaque-reference]');
    return fields;
  }};
  const get = new Function('form', 'let dirty = false;\n' + helper + '\nreturn {compatibleMediaReferenceField, bindOpaqueReference, getDirty:() => dirty};');
  return get(form);
}
function field(name, value = '', type = 'opaque_reference') {
  return {
    name, value, dataset: {fieldType:type}, inputEvents: 0,
    dispatchEvent(event) {
      assert.equal(event.type, 'input');
      assert.equal(event.bubbles, true);
      this.inputEvents += 1;
    }
  };
}
const consent = field('patient_consent_reference');
const rights = field('media_rights_reference');
const profile = field('profile_reference');
const alt = field('image_alt_text', '', 'text');
const image = field('image_reference');
const audio = field('audio_reference');
const generic = field('media_reference');
const h = harness([consent, rights, profile, alt, image, audio, generic]);

assert.equal(h.bindOpaqueReference('upload:image:1', 'image/png'), true);
assert.equal(image.value, 'upload:image:1');
assert.equal(consent.value, '', 'Never replace patient consent reference');
assert.equal(rights.value, '', 'Never replace media rights evidence reference');
assert.equal(profile.value, '', 'profile_reference must not be mistaken for file_reference');
assert.equal(alt.value, '', 'Non-opaque alt text is never overwritten');
assert.equal(h.bindOpaqueReference('upload:audio:1', 'audio/mpeg'), true);
assert.equal(audio.value, 'upload:audio:1');
assert.equal(h.bindOpaqueReference('upload:other:1', 'image/jpeg'), true, 'Generic media field is allowed');
assert.equal(generic.value, 'upload:other:1');
assert.equal(h.getDirty(), true);
assert.equal(image.inputEvents, 1, 'Binding media triggers ordinary input/autosave scheduling');
assert.equal(audio.inputEvents, 1);
assert.equal(generic.inputEvents, 1);
assert.equal(consent.inputEvents, 0);
assert.equal(rights.inputEvents, 0);

const h2fields = [field('patient_consent_reference'), field('profile_reference'), field('source_pdf_reference')];
const h2 = harness(h2fields);
assert.equal(h2.bindOpaqueReference('upload:only:1', 'application/pdf'), false);
assert.ok(h2fields.every(x => x.value === ''), 'No unrelated field may be silently mutated');
assert.equal(h2.getDirty(), false);
assert.equal(h2.bindOpaqueReference('bad<script>', 'image/png'), false);
assert.equal(h2.bindOpaqueReference({upload_reference:1}, 'image/png'), false);
assert.equal(h2.bindOpaqueReference('', 'image/png'), false);

const h3fields = [field('image_reference')];
const h3 = harness(h3fields);
assert.equal(h3.bindOpaqueReference('upload:audio:2', 'audio/ogg'), false, 'Audio must not go into an image field');
assert.equal(h3fields[0].value, '');
assert.equal(h3.bindOpaqueReference('upload:image:2', 'image/webp'), true);
assert.equal(h3.bindOpaqueReference('upload:image:3', 'image/jpeg'), false, 'Existing native reference must not be overwritten');
assert.equal(h3fields[0].value, 'upload:image:2');

const h4fields = [field('media_reference')];
const h4 = harness(h4fields);
assert.equal(h4.bindOpaqueReference('upload:unknown:1', 'application/octet-stream'), false, 'Unknown MIME must not be auto-bound');
assert.equal(h4.bindOpaqueReference('upload:unknown:2', ''), false, 'Empty MIME must not be auto-bound');
assert.equal(h4fields[0].value, '');
assert.equal(h4.getDirty(), false);

console.log('File22 V12.3 native media reference binding: PASS.');
