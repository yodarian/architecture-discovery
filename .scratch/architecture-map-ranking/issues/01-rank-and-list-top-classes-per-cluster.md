# 01 — Rank and list top classes per cluster

**What to build:** Each cluster in `architecture-map.md` lists up to 5 of its most-important member classes by fully-qualified name, ranked by a degree-centrality proxy, so an agent has concrete search targets instead of just a class count.

**Blocked by:** None — can start immediately

- [ ] Acceptance criterion: for each cluster, the rendered entry lists member classes ranked by incoming dependency edge count (descending), then total degree — incoming + outgoing (descending), then fully-qualified class name (ascending) as the final deterministic tie-break
- [ ] Acceptance criterion: the listing is capped at the top 5 classes and never exceeds the cluster's actual member count (a 2-class cluster lists both)
- [ ] Acceptance criterion: the ranking method carries a code comment noting that real PageRank (as Aider implements over a reference graph, see `docs/research/agent-codebase-maps.md`) is a valid future refinement if degree centrality proves too coarse, and that a size-scaled cap (`min(5, ceil(classCount * 0.3))`) is a plausible future refinement to the flat top-5 cap
- [ ] Acceptance criterion: `tests/Unit/Reporting/ArchitectureMapRendererTest.php` covers ranking order by incoming-edge count, the total-degree tie-break, the alphabetical-FQN tie-break, and the cap not exceeding a small cluster's actual membership — asserted only through `ArchitectureMapRenderer::render()`'s output against a hand-computed expected literal
