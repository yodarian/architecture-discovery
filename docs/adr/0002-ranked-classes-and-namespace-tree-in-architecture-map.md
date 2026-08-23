# Record ranked classes and namespace tree in the Architecture Map

- Status: Accepted
- Date: 2026-08-23

## Context

The original Architecture Map decision deliberately excluded per-class listings. Ticket 03 of the architecture-map effort described the artifact as a compact cluster-level summary and stated that per-class detail already belonged in `architecture.json`. That made the map concise, but it left an agent without concrete class names to search for inside a cluster. The map also lacked a project-wide view of physical namespace organization.

Research into existing codebase maps found that Aider's repository map uses symbol-level content and graph-based importance ranking. The research is recorded in `docs/research/agent-codebase-maps.md`.

## Decisions

### Restore targeted per-class content

Each cluster now lists at most five member classes by fully-qualified name. The list is ranked by incoming dependency edge count, then total degree (incoming plus outgoing), then fully-qualified name as a deterministic tie-break. This reverses the original blanket exclusion of per-class content without turning the Architecture Map into a second `architecture.json`: it exposes only a small set of concrete search targets.

This choice follows the research's finding that Aider's map is symbol-level and selects important symbols rather than presenting only file or module aggregates. See `docs/research/agent-codebase-maps.md`, especially the sections on content granularity and ranking/selection.

### Use degree centrality for now

The initial ranking uses degree centrality because incoming and outgoing edge counts are already available in the architecture model, are inexpensive to compute during rendering, and are straightforward to explain and test. Real PageRank is a valid future refinement if this proxy proves too coarse, but Aider's PageRank implementation includes a weighted, personalized reference graph and a token-fitting selection loop. That is a larger policy and implementation surface than this artifact currently needs.

The research documents Aider's PageRank approach and identifies it as the strongest primary-source precedent for importance ranking. See `docs/research/agent-codebase-maps.md`, section “Ranking/selection under a token budget”.

### Keep connectivity-based clustering

Clusters remain derived from dependency-graph connectivity rather than namespace boundaries. The namespace tree is a separate, project-wide orientation section, so physical organization is visible without changing the meaning or stability of clusters. Dependency connectivity better represents relationships that cross namespace boundaries and preserves the existing clustering model.

The research found that Aider ranks a file reference graph, while Repomix and Gitingest present directory structure only as supplementary orientation and do not use it as the primary selection unit. It found no primary-source case for replacing dependency relationships with namespace grouping. See `docs/research/agent-codebase-maps.md`, section “Module/directory/namespace grouping vs. dependency-graph connectivity”.

## Consequences

- Agents get concrete, ranked class names as search targets while the map remains bounded.
- The namespace tree gives global structural orientation independently of cluster formation.
- Degree centrality can miss importance that a weighted or personalized PageRank would capture; that tradeoff is explicit and revisitable.
- Namespace layout and dependency cohesion remain separate signals, avoiding an assumption that package structure defines architecture.
