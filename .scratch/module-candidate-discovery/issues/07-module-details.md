# 07 — Add Module Details for Human Review

**What to build:** A developer can move from the module overview to a detailed candidate view and inspect core members, related/shared collaborators, candidate-adjacent unassigned classes, dependency evidence, roles, file locations, and namespace drift.

**Blocked by:** 03 — Expose Namespace Drift in Module Evidence; 05 — Make Dependency Semantics Legible in Diagrams

**Status:** ready-for-agent

- [ ] Provide a candidate-focused detail representation or filtered class graph.
- [ ] Separate core, related, shared, and unassigned sections.
- [ ] Show the underlying class dependencies behind aggregated module edges.
- [ ] Show actual namespaces, source locations, roles, confidence, and evidence.
- [ ] Highlight namespace drift and informational suggested locations.
- [ ] Support candidate-focused review without modifying the analyzed project.
- [ ] Add tests for detail completeness, membership categories, evidence, drift, and filtering.
