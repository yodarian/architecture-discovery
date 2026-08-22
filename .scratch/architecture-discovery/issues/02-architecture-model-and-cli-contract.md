# 02 — Architecture model and CLI contract

**What to build:** A stable architecture model and command interface so the rest of the platform builds around one canonical representation rather than ad hoc formats.

**Blocked by:** 01 — Dockerized project bootstrap

**Status:** ready-for-human

- [x] Acceptance criterion: the CLI exposes the intended analysis entrypoints and arguments
- [x] Acceptance criterion: architecture.json is the canonical machine-readable output format
- [x] Acceptance criterion: the model covers project metadata, class entities, dependency edges, and versioning expectations

## Implementation Notes

- Added the `analyse` command with project path, output directory, exclusion, format, and model-version options.
- Added the versioned architecture model for project metadata, PHP class entities, and typed dependency edges.
- Added deterministic PHP AST extraction and structural dependency generation for inheritance, interfaces, and traits.
- Added `resources/schemas/architecture.json.schema` as the contract for `architecture.json`.
- Added PHPUnit coverage for the model, scanner, parser, and serialization.

## Validation

- PHPUnit: 31 tests and 92 assertions passing.
- CLI fixture analysis verified generation of `architecture.json` with version, classes, dependencies, and ISO 8601 timestamp fields.
