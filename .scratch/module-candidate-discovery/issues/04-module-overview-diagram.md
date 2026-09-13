# 04 — Render the Module Overview Diagram

**What to build:** `modules.svg` gives a human-readable overview of module candidates, shared candidates, candidate-adjacent unassigned classes, confidence, and aggregated dependencies without showing every class edge.

**Blocked by:** 02 — Report Shared, Unassigned, and Supporting Code

**Status:** ready-for-agent

- [x] Generate a separate module overview artifact alongside the raw class graph.
- [x] Render one grouped node or compound cluster for each module candidate.
- [x] Display strong shared candidates once and connect them to consuming candidates.
- [x] Aggregate class dependencies into candidate-to-candidate edges with counts and weighted strength.
- [x] Display candidate confidence as explicit text and visual treatment.
- [x] Show candidate-adjacent unassigned classes by default.
- [x] Keep the raw `graph.svg` available for class-level evidence and diagnostics.
- [x] Add renderer tests for candidate groups, shared candidates, aggregation, confidence, and unassigned-code visibility.
