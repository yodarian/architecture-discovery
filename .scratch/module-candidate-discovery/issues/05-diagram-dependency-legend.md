# 05 — Make Dependency Semantics Legible in Diagrams

**What to build:** Humans can interpret raw and module diagrams without external documentation because both diagrams include a legend and distinguish abstraction targets from relationship types, confidence, shared candidates, and namespace drift.

**Blocked by:** 04 — Render the Module Overview Diagram

**Status:** ready-for-agent

- [ ] Render interface-target edges in green.
- [ ] Render concrete-target edges in red.
- [ ] Render abstract-target edges with a distinct amber treatment.
- [ ] Render framework and external dependencies with a subdued distinct style.
- [ ] Distinguish dependency types through labels, line styles, or arrow treatment.
- [ ] Explain candidate confidence, shared candidates, namespace drift, and unassigned code in a legend.
- [ ] Include the legend in both Graphviz-derived visualizations.
- [ ] Ensure the fallback SVG communicates the essential visual meanings when Graphviz is unavailable.
- [ ] Ensure color is not the only semantic signal.
- [ ] Add focused tests for edge styling, labels, legend contents, and fallback output.
