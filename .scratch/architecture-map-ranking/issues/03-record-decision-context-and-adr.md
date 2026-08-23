# 03 — Record the decision in CONTEXT.md and ADR 0002

**What to build:** This repo's own `CONTEXT.md` and a new ADR accurately describe the Architecture Map's ranked-classes-plus-namespace-tree content and the reasoning behind it.

**Blocked by:** 01, 02

- [ ] Acceptance criterion: `CONTEXT.md`'s "Architecture Map" glossary entry is updated to reflect the ranked per-cluster class listing and the namespace tree section, so it no longer describes only aggregate stats
- [ ] Acceptance criterion: a new ADR at `docs/adr/0002-<slug>.md`, separate from `docs/adr/0001-no-footprint-in-analyzed-repo.md`, records why per-class content was added back after ticket 03 of the architecture-map effort excluded it, why the ranking is degree-centrality rather than PageRank for now, and why clustering stayed connectivity-based rather than switching to namespace grouping — citing `docs/research/agent-codebase-maps.md` for each call
