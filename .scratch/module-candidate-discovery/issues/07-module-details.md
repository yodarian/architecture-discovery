# 07 — Add Module Details for Human Review

**What to build:** A developer can move from the module overview to a detailed candidate view and inspect core members, related/shared collaborators, candidate-adjacent unassigned classes, dependency evidence, roles, file locations, and namespace drift.

**Blocked by:** 03 — Expose Namespace Drift in Module Evidence; 05 — Make Dependency Semantics Legible in Diagrams

**Status:** ready-for-agent

- [x] Provide a candidate-focused detail representation or filtered class graph.
- [x] Separate core, related, shared, and unassigned sections.
- [x] Show the underlying class dependencies behind aggregated module edges.
- [x] Show actual namespaces, source locations, roles, confidence, and evidence.
- [x] Highlight namespace drift and informational suggested locations.
- [x] Support candidate-focused review without modifying the analyzed project.
- [x] Add tests for detail completeness, membership categories, evidence, drift, and filtering.
