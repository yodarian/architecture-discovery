# 03 — Generate the Architecture Map artifact

**What to build:** `analyse` produces a Markdown `architecture-map.md` by default, summarizing the architecture model at module/cluster granularity so an agent or human can orient in a legacy codebase without reading the full `architecture.json` or the source itself.

**Blocked by:** None — can start immediately

- [ ] Acceptance criterion: a new `Reporting`-namespace renderer (parallel to `GraphvizRenderer`/`HtmlReportGenerator`) produces `architecture-map.md` content from an `Architecture` instance, using only data already present on the model (`getClusters()`, dependency metadata) with no additional analysis of its own
- [ ] Acceptance criterion: the generated content includes, per cluster: cluster id, member class count, internal/external dependency counts, inter-cluster dependency direction, and framework-tagged relation counts
- [ ] Acceptance criterion: the generated content excludes per-class listings and any ubiquitous-language/terminology content
- [ ] Acceptance criterion: `architecture-map` is a recognized `--format` value and is included in the default format set produced when `--format` is omitted
- [ ] Acceptance criterion: passing `--format` without `architecture-map` excludes it from the output
- [ ] Acceptance criterion: a unit test in `tests/Unit/Reporting/` builds an `Architecture` fixture with known classes/dependencies/clusters and asserts on the rendered Markdown's structure and content
