# Adapter Contract

Every content type is owned by a native module. File 22 only coordinates the creation experience.

## Required adapter behavior

An adapter must provide:

1. A stable machine key.
2. An American English display label.
3. Native module and minimum-version detection.
4. A capability-based authorization decision for the current user.
5. A versioned field schema.
6. Native draft creation or an explicit declaration that native drafts are unsupported.
7. Side-effect-free validation.
8. Idempotent submission.
9. A canonical native destination.
10. Native ownership, cleanup, privacy, and rollback callbacks in later contract versions.

## Prohibited adapter behavior

An adapter must not:

- grant permissions independently of Sabri Membership Core;
- duplicate a native post type, custom table, moderation queue, or media vault;
- expose pending, private, rejected, or unsafe content publicly;
- store PDF bytes, consent evidence, identity evidence, or clinical records in a generic File 22 upload area;
- claim successful publication before the native owner confirms a durable result;
- convert a retry into a duplicate native object.

## Idempotency

Final submission must use an immutable idempotency key bound to the composer session and submission attempt. Repeating the same request must return the same native result.

Recommended logical key:

`composer_session_uuid + submission_attempt_uuid`

The adapter must persist or resolve:

- idempotency key;
- native object reference;
- result state;
- canonical URL when available;
- failure classification;
- retry eligibility.

## Partial-failure recovery

If the native object is created but File 22 fails before storing the response, the next retry must reconcile against the existing native mapping instead of creating another object.

Notifications and indexing events must be emitted through an outbox or other retry-safe mechanism after the native record exists.

## Capability resolution

Role names alone are insufficient. Authorization must consider:

- account role;
- verification state;
- trusted-publisher state;
- suspension state;
- content-type capability;
- native module policy.

## Native examples

| Adapter | Native owner |
|---|---|
| `publication` | File 21 |
| `learning_lesson` | File 05 |
| `encyclopedia_entry` | File 06 |
| `video` | File 10 |
| `reel` | File 11 through File 10 video ownership |
| `pdf_document` | File 12 |
| `marketplace_listing` | File 18 |

## Fail-soft rule

An unavailable adapter is omitted from the user's Create choices. Its failure must not disable unrelated healthy adapters.
