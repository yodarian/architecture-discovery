# 03 — Static PHP discovery and dependency extraction

**What to build:** A deterministic static analysis pipeline that recursively finds PHP files, parses classes and dependencies, and produces a reproducible architecture overview for generic PHP projects.

**Blocked by:** 02 — Architecture model and CLI contract

**Status:** ready-for-human

- [x] Acceptance criterion: PHP source files are discovered recursively from the project root
- [x] Acceptance criterion: classes, interfaces, traits, namespaces, inheritance, and type-based dependencies are extracted
- [x] Acceptance criterion: the output is emitted as a versioned architecture model and is reproducible for the same input

## Implementation Notes

- `FileScanner` recursively discovers PHP files, applies default and custom exclusions, and returns files in stable relative-path order.
- `PhpClassExtractor` extracts namespaces, classes, interfaces, traits, inheritance, interface implementation, trait usage, and fully qualified type references from AST nodes.
- The `analyse` command emits internal structural and type-based dependency edges in the versioned `architecture.json` model.
- Architecture classes and dependency edges are sorted by canonical names before serialization for reproducible output ordering.

## Validation

- PHPUnit: 33 tests and 97 assertions passing.
- CLI fixture analysis verified a constructor type reference produces a dependency edge in `architecture.json`.
