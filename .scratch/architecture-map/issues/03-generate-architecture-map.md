# 03 — Generate the Architecture Map artifact

**What to build:** `analyse` produces a Markdown `architecture-map.md` by default, summarizing the architecture model at module/cluster granularity so an agent or human can orient in a legacy codebase without reading the full `architecture.json` or the source itself.

**Blocked by:** None — can start immediately

**Status:** done

- [x] Acceptance criterion: a new `Reporting`-namespace renderer (parallel to `GraphvizRenderer`/`HtmlReportGenerator`) produces `architecture-map.md` content from an `Architecture` instance, using only data already present on the model (`getClusters()`, dependency metadata) with no additional analysis of its own
- [x] Acceptance criterion: the generated content includes, per cluster: cluster id, member class count, internal/external dependency counts, inter-cluster dependency direction, and framework-tagged relation counts
- [x] Acceptance criterion: the generated content excludes per-class listings and any ubiquitous-language/terminology content
- [x] Acceptance criterion: `architecture-map` is a recognized `--format` value and is included in the default format set produced when `--format` is omitted
- [x] Acceptance criterion: passing `--format` without `architecture-map` excludes it from the output
- [x] Acceptance criterion: a unit test in `tests/Unit/Reporting/` builds an `Architecture` fixture with known classes/dependencies/clusters and asserts on the rendered Markdown's structure and content

## Comments

Implemented in commit `36a960c`. Note: `ConnectedComponentsClusterer` builds clusters as connected components of the dependency graph, so in practice inter-cluster incoming/outgoing edge counts are always zero for real analyses (an existing property of that algorithm, unrelated to this ticket) — the renderer still formats those fields whenever a cluster reports nonzero values, verified with a hand-built fixture in the unit test.
