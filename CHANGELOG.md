# Changelog

All notable changes to `flametrench/audit` are recorded here.
Spec-level changes live in [`spec/CHANGELOG.md`](https://github.com/flametrench/spec/blob/main/CHANGELOG.md).

## [v0.4.0] — 2026-06-07

### Added
- Initial release — `flametrench/audit` v0.4.0 (ADR 0019 / spec v0.4).
- `AuditEvent` value object: `id` (`aud_<32hex>`), `occurredAt`, `recordedAt` (server-set), `actorUsrId`, `auth?`, `onBehalf?`, `action`, `target`, `scope?`, `outcome`, `metadata`, `context?`.
- `Outcome` enum: `success | failure | denied | pending`.
- `AuthContext`, `OnBehalf`, `Target`, `Scope`, `EventContext` value objects.
- `AuditStore` interface: `write()` (durable append) + `get()` (fetch by id).
- `InMemoryAuditStore`: spec-conformant reference implementation for tests and small applications.
- Input validation in `write()` per ADR 0019 §Errors (spec PRs #43 + #46): `InvalidFormatException` (field `auth`, `actor_usr_id`, `target.kind`, `size`).
- `NotFoundException` for unknown event ids.
- 7/7 MUST conformance cases from `audit/write-event-shape.json` pass.
- Requires `flametrench/ids ^0.4.0` (adds the `aud_` prefix to the id registry).
