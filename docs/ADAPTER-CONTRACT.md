# Adapter Contract — API 1.0.0

Every content type is owned by a native module. File 22 coordinates discovery and creation without taking permanent ownership.

## Base Adapter

The base adapter supplies:

- exact adapter API version;
- canonical machine key;
- label and description;
- group, icon, and deterministic priority;
- native module identifier and minimum version;
- central required capability;
- privacy classification;
- native availability;
- adapter-specific authorization restriction;
- safe start URL.

The canonical key must match `^[a-z][a-z0-9_]{2,63}$`. Invalid keys are rejected, never silently rewritten.

## Workflow Adapter

A full workflow adapter additionally supplies:

- schema version;
- native-draft support declaration;
- versioned schema;
- create or resume draft with an explicit native reference;
- side-effect-free validation;
- private preview;
- idempotent submission;
- native status mapping;
- canonical URL.

## Diagnostic Adapter

A diagnostic adapter may expose a privacy-safe health report. Reports must never contain full unpublished bodies, identity evidence, consent evidence, patient narratives, secrets, or encryption keys.

## Idempotency

Final submission uses an immutable idempotency key bound to the composer session and submission attempt. Repeating the same request returns the same native result.

Recommended logical key:

`composer_session_uuid + submission_attempt_uuid`

If a native object exists but the File 22 response was lost, the adapter reconciles the existing mapping instead of creating another object.

## Fail-soft behavior

File 22 isolates `Throwable` failures per adapter. One broken adapter is disabled for the request and recorded in privacy-safe diagnostics; healthy adapters remain available.

## Prohibited behavior

An adapter must not grant permissions independently, duplicate native records, expose unsafe content, store protected evidence in generic File 22 storage, claim publication before a durable native result, or convert retries into duplicates.
