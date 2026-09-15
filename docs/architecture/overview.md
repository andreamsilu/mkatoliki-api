# Architecture and security

The controllers expose `/api/v1`, Form Requests validate inputs, services implement transactions and domain rules, policies enforce role/permission/token/scope checks, and API Resources control returned fields. `EntityRegistry` is a fixed allowlist of models, parents and access classifications; route or request input cannot select arbitrary tables or columns.

## Relational integrity

All parent references have foreign keys and restricted deletion. Parish-level records carry `parish_id`; composite foreign keys ensure selected families, jumuiyas, zones and outstations belong to that parish even when code bypasses API validation. The hierarchy validator traverses the full ancestry of every selected parent, including nullable intermediate selections, to reject conflicting zones or outstations within the same parish.

A Kanda belongs directly to a parish and may optionally reference a Kigango. Jumuiya requires a Kanda. Families and members can initially reference only the parish. Organizational codes are globally unique per entity, chosen to make references and repeatable imports unambiguous; code values should include sufficient organizational prefixes.

Writes lock the entity and its ancestry, validate the complete proposed record, check the current and destination scope, and commit the record and audit together. Reparenting organizations with dependents is rejected. The dedicated parish transfer transaction records previous and new deanery/diocese and validates both scopes. Future-dated moves are rejected; this API does not schedule transfers.

## Privacy and authentication

Sanctum stores hashed, expiring bearer tokens. Administrative accounts are created through a local privileged command; no public sign-up exists. Roles and permissions are seeded idempotently. Existing tokens lose access immediately when an account is disabled or its permission is removed. Scope is read from the current account on each request.

Families and members have no anonymous API or search access. Public resources omit private contacts, addresses and administrative descriptions. Public directory records and their primary ancestors must be active and verified. Changes to identifying attributes, location, source, or ancestry require verification again; retiring an ancestor hides its public descendants. Status changes are reversible and audited. Verification and source history are retained.

API responses use a consistent JSON envelope and do not expose stack traces or SQL. Request logs include generated UUIDs, route templates, duration, user ID and IP; query strings, bodies, tokens and credentials are not logged. SQL exceptions are reported with SQLSTATE only. Audit values redact personal names, contacts, birth dates and gender. Audit endpoints and raw import batches are national-administrator only. Restrict operational log access and define organizational retention requirements before production.

## Caching and operational boundaries

Only public responses are cached. Keys include a shared cache revision, route and normalized query parameters. Committed writes change the revision, and older entries expire within five minutes by default. Private responses bypass application caching, and all responses use `Cache-Control: no-store, private` to prevent shared HTTP caches retaining credentials or private records. Redis is required for shared cache invalidation and rate limits across production workers.

Maximum page size is 100; imports accept at most 500 rows and Nginx caps bodies at 2 MiB. Structure responses explicitly report truncation and provide pagination links. Search is restricted to seven public entity types and uses bound, escaped LIKE queries. It is appropriate for the initial master dataset; benchmark with representative data before claiming the specification's latency or throughput targets.

Source versions are immutable provenance records. The versioned TEC 2020 seed snapshot supplies provinces, dioceses, explicitly named deaneries, and parishes with documented deanery assignments. New records retain their source and require verification; the historical snapshot does not establish current truth. Reseeding inserts missing codes without overwriting existing records or transfers. Printed page references and coverage gaps are retained in `database/seeders/tec-directory-2020.json`. The import process is synchronous and bounded; future large import orchestration can dispatch batches through the configured queue without changing their review contract.
