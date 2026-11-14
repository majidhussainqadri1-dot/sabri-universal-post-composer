'use strict';

/**
 * Dynamic dependency-free test of the exact validation-grouping function used
 * by the Composer browser. Do not substitute a copied function implementation.
 */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');

const source = fs.readFileSync(path.join(__dirname, '../assets/js/workflow-composer.js'), 'utf8');
const startMarker = '\tconst validationGroupForCode = (code) => {';
const nextMarker = '\n\tconst renderValidationSummary = (codes) => {';
const start = source.indexOf(startMarker);
const end = source.indexOf(nextMarker, start);
assert.ok(start >= 0 && end > start, 'Expected actual validation-grouping function in Composer JS');
const validationGroupForCode = new Function(source.slice(start, end) + '\nreturn validationGroupForCode;')();

const cases = [
  ['patient_consent_reference_required', 'privacy'],
  ['patient_privacy_required', 'privacy'],
  ['anonymization_missing', 'privacy'],
  ['audio_rights_required', 'copyright'],
  ['media_copyright_required', 'copyright'],
  ['medical_references_required', 'references'],
  ['unsafe_citation_source', 'references'],
  ['medical_safety_acknowledgement_required', 'medical'],
  ['audio_transcript_missing', 'media'],
  ['image_alt_text_required', 'media'],
  ['media_upload_expired', 'media'],
  ['role_permission_missing', 'permission'],
  ['required_title', 'required'],
  ['invalid_native_url', 'technical']
];
for (const [code, expected] of cases) {
  assert.equal(validationGroupForCode(code), expected, code);
}
console.log('File 22 V12.3 validation category regression: PASS (' + cases.length + ' cases).');
