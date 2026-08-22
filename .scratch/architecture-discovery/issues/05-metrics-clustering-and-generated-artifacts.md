# 05 — Metrics, clustering, and generated artifacts

**What to build:** Dependency metrics, module candidates, rendering output, and a human-readable report derived from the same architecture model.

**Blocked by:** 02 — Architecture model and CLI contract, 03 — Static PHP discovery and dependency extraction, 04 — CakePHP association analysis

**Status:** ready-for-human

- [x] Acceptance criterion: graph and metrics output is generated from the architecture model
- [x] Acceptance criterion: cluster or module candidates are suggested from dependency structure
- [x] Acceptance criterion: Graphviz/SVG and an HTML report are produced without duplicating the source-of-truth model

## Implementation Notes

- Added graph metrics for class counts, dependency counts, CakePHP dependency counts, and per-class incoming/outgoing coupling.
- Added connected-component clustering that produces deterministic cluster candidates with class membership, internal edges, cohesion, and coupling metrics.
- Added model-driven Graphviz DOT, SVG, and static HTML renderers. SVG generation uses Graphviz when available and a valid deterministic fallback otherwise.
- The `analyse` command now enriches `architecture.json` with metrics and clusters and generates `graph.dot`, `graph.svg`, and `index.html` by default.
- Added validation for requested output formats while retaining `architecture.json` as the mandatory canonical artifact.

## Validation

- PHPUnit: 38 tests and 135 assertions passing.
- CLI-level coverage verifies metrics, cluster candidates, Graphviz/SVG output, and HTML report generation.
