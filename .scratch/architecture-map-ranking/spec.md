Status: ready-for-agent

# Rank and Cap Architecture Map Content, Add a Namespace Tree

## Problem Statement

The Architecture Map (`architecture-map.md`, added by the architecture-map effort) currently lists only aggregate per-cluster statistics — class count, internal dependency count, isolated flag, framework-tagged relation count — with no class names at all. Running it against a real (if small) hexagonal-style CakePHP app showed the practical failure mode: the one cluster that actually matters (14 classes, 21 internal dependencies) renders as an anonymous blob labeled only by its common namespace prefix (`App`), giving an agent zero actionable information about what's inside it. Smaller, genuinely uninteresting clusters (isolated test fixtures, tooling classes) get exactly the same treatment as the one cluster doing all the work.

Research into how established tools (Aider, Repomix, Gitingest, Cursor, Sourcegraph Cody) build LLM-facing codebase summaries (`docs/research/agent-codebase-maps.md`) confirms two things: (1) the project's existing connectivity-based clustering (`ConnectedComponentsClusterer`) is closer to validated prior art than switching to namespace/directory-based grouping would be — no primary source clusters by physical code organization — but (2) the project's map is missing the two things that make Aider's repo map actually useful: an **importance ranking** to decide what to surface, and a **budget/cap** to keep output from growing unbounded as clusters get large. The research also confirms that a supplementary directory/namespace tree (as Repomix and Gitingest include) is validated practice for global orientation, used alongside — not instead of — the ranked/clustered content.

## Solution

Keep `ConnectedComponentsClusterer` as the clustering unit. Extend `ArchitectureMapRenderer` to additionally list, per cluster, the top 5 most "important" member classes (or fewer, if the cluster has fewer members), ranked by a degree-centrality proxy: incoming dependency edge count first, total degree (incoming + outgoing) as tie-break, and alphabetical fully-qualified class name as the final deterministic tie-break. This is a simpler stand-in for Aider's PageRank-based ranking; the code and this spec both document that real PageRank (via power iteration over the class-level dependency graph) is a valid future refinement if degree centrality proves too coarse in practice, along with a size-scaled cap (`min(5, ceil(classCount * 0.3))`) as a plausible future refinement to the flat top-5 cap.

Add a new, separate top-level section to the rendered map: a namespace tree built from `ClassEntity::getNamespace()` across the whole `Architecture`, showing the nested namespace hierarchy with a class count at each node, for global orientation independent of clustering.

Record this decision in a new ADR (`docs/adr/0002-...`), separate from ADR 0001, citing `docs/research/agent-codebase-maps.md`.

## User Stories

1. As an agent reading `architecture-map.md`, I want each cluster to list its most important member classes by name, so that I have concrete search targets instead of just a class count.
2. As an agent reading `architecture-map.md`, I want the listed classes to be ranked by how much other code depends on them, so that I see core abstractions before leaf consumers.
3. As an agent reading a map for a large cluster, I want the class list capped at a small number, so that one dominant cluster doesn't dump dozens of names and defeat the token-saving purpose of the map.
4. As an agent reading `architecture-map.md`, I want a project-wide namespace tree, so that I can orient on the overall code layout independent of how clusters happen to be formed.
5. As a maintainer, I want the ranking to be fully deterministic (stable tie-breaks), so that re-running `analyse` on unchanged code produces byte-identical output.
6. As a maintainer, I want the code and spec to document that real PageRank and a size-scaled cap are valid future refinements, so that a future contributor doesn't have to rediscover the research to justify revisiting these choices.
7. As a maintainer, I want an ADR recording why the map now includes ranked per-class content (partially reversing ticket 03's "no per-class listing" rule) and why clustering stayed connectivity-based rather than switching to namespace grouping, citing the research that grounds both calls.

## Implementation Decisions

- No change to `ConnectedComponentsClusterer` or the clustering unit — clusters remain connected components of the dependency graph, per the research findings in `docs/research/agent-codebase-maps.md`.
- `ArchitectureMapRenderer` gains a ranked, capped per-cluster class listing, implemented as a private method (mirroring the existing `deriveLabel()`/`countFrameworkTaggedRelations()` pattern) rather than a new class — the single seam under test remains `ArchitectureMapRenderer::render()`.
- Ranking signal: for each class in a cluster, compute incoming edge count (dependencies where the class is `to`) and total degree (incoming + outgoing) from `Architecture::getDependencies()`. Sort by incoming edge count descending, then total degree descending, then fully-qualified class name ascending. Take the top 5.
- Never list more classes than the cluster actually has (a 2-class cluster lists both, no padding).
- Add a code comment on the ranking method noting that real PageRank (as implemented by Aider via `networkx.pagerank` over a file/class-level reference graph, see `docs/research/agent-codebase-maps.md`) is a valid future refinement if degree centrality proves too coarse, and that a size-scaled cap (`min(5, ceil(classCount * 0.3))`) is a plausible future refinement to the flat top-5 cap.
- New rendered section: a namespace tree, built once per `render()` call from all classes on the `Architecture` (not per-cluster), showing nested namespace segments with a count of classes directly and transitively under each node. Rendered as a separate top-level Markdown section, positioned before the per-cluster listings.
- New ADR at `docs/adr/0002-<slug>.md`, separate from `docs/adr/0001-no-footprint-in-analyzed-repo.md`, recording: why per-class content was added back after ticket 03 excluded it, why the ranking is degree-centrality rather than PageRank (for now), and why clustering stayed connectivity-based despite the "one blob" symptom seen in practice — citing `docs/research/agent-codebase-maps.md` for each call.
- `CONTEXT.md`'s existing "Architecture Map" glossary entry is updated to reflect the new content (ranked classes, namespace tree) so the glossary doesn't describe a stale version of the artifact.

## Testing Decisions

- Tests continue to live in `tests/Unit/Reporting/ArchitectureMapRendererTest.php`, exercising only the public seam `ArchitectureMapRenderer::render()` against hand-built `Architecture` fixtures — no new seam is introduced for the ranking or namespace-tree logic specifically, consistent with how `deriveLabel()` and `countFrameworkTaggedRelations()` were tested in tickets 03/05.
- Test cases needed: ranking order when incoming-edge counts differ; the total-degree tie-break when incoming counts are equal; the alphabetical-FQN tie-break when both are equal; the cap not exceeding actual cluster membership for small clusters; the namespace tree rendering nested namespaces with correct per-node class counts, including a namespace with only global-namespace (empty-string) classes.
- Expected values in each test must come from a hand-computed literal (the full expected Markdown string), not recomputed via the same ranking logic under test — consistent with the existing tests in this file.

## Out of Scope

- Replacing `ConnectedComponentsClusterer` with namespace/directory-based clustering — explicitly rejected by this spec's research.
- Implementing real PageRank ranking or a size-scaled cap now — both are documented as future refinements, not built in this pass.
- A token-budget-fitting mechanism (binary search against an actual token count, as Aider does) — deferred; this pass uses a fixed flat cap instead.
- Any change to `architecture.json`'s schema, `ConnectedComponentsClusterer`'s metrics computation, or the LLM context-building pipeline.
- Fixing the pre-existing parser bug (seen during manual review) where PHP keywords/built-in function calls are captured as fake `typeDependencies` (e.g. `App\parent`, `App\true`) — unrelated to this spec.

## Further Notes

This spec corrects a design mistake made earlier in this same effort: after reviewing the map against a real (if small) project, the assistant initially recommended replacing connectivity-based clustering with namespace-based clustering. Research into primary sources (Aider, Repomix, Gitingest, Cursor, Sourcegraph Cody) showed no primary source validates that approach, and that the actual gap was missing ranking and a selection budget, not the wrong clustering unit. `docs/research/agent-codebase-maps.md` documents this in full and should be treated as the source of truth for why this spec is shaped the way it is.
