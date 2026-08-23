# 05 — Make Architecture Map clusters identifiable and drop dead metrics

**What to build:** `architecture-map.md` clusters carry a derived, human-readable label (not just an opaque `cluster-N` id) so an agent can tell what a cluster is about without opening `architecture.json` or the source. Structurally-always-zero fields are removed, and singleton/isolated clusters are called out distinctly.

**Blocked by:** None — can start immediately (builds on the existing `ArchitectureMapRenderer` from ticket 03)

- [ ] Acceptance criterion: each cluster's rendered entry includes a label derived from the longest common namespace prefix shared by its member classes (still using only data already on the `Architecture`/`ClassEntity` model, no new analysis pass), alongside its `cluster-N` id
- [ ] Acceptance criterion: the "Incoming dependencies (from other clusters)" and "Outgoing dependencies (to other clusters)" lines are removed from the rendered output, since `ConnectedComponentsClusterer` clusters are connected components and cross-cluster edges are structurally impossible — these fields are always zero and add no signal
- [ ] Acceptance criterion: a cluster with exactly one member class and zero internal dependencies is flagged as isolated in its rendered entry, distinguishing it from a real multi-class boundary
- [ ] Acceptance criterion: `tests/Unit/Reporting/ArchitectureMapRendererTest.php` is extended to cover label derivation (including a cluster with no common namespace prefix beyond the root), the removal of the incoming/outgoing lines, and isolated-cluster flagging
