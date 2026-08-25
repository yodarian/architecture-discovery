# 04 — Render the Module Overview Diagram

**What to build:** `modules.svg` gives a human-readable overview of module candidates, shared candidates, candidate-adjacent unassigned classes, confidence, and aggregated dependencies without showing every class edge.

**Blocked by:** 02 — Report Shared, Unassigned, and Supporting Code

**Status:** ready-for-agent

- [ ] Generate a separate module overview artifact alongside the raw class graph.
- [ ] Render one grouped node or compound cluster for each module candidate.
- [ ] Display strong shared candidates once and connect them to consuming candidates.
- [ ] Aggregate class dependencies into candidate-to-candidate edges with counts and weighted strength.
- [ ] Display candidate confidence as explicit text and visual treatment.
- [ ] Show candidate-adjacent unassigned classes by default.
- [ ] Keep the raw `graph.svg` available for class-level evidence and diagnostics.
- [ ] Add renderer tests for candidate groups, shared candidates, aggregation, confidence, and unassigned-code visibility.
