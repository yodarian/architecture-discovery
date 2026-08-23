# 02 — Add a namespace tree section

**What to build:** `architecture-map.md` gains a new top-level section, positioned before the per-cluster listings, showing a nested namespace tree built from every class in the project with a class count at each node — global orientation independent of how clusters happen to be formed.

**Blocked by:** None — can start immediately

- [x] Acceptance criterion: the rendered map includes a namespace tree section built from `ClassEntity::getNamespace()` across all classes on the `Architecture`, not scoped to individual clusters
- [x] Acceptance criterion: each namespace node in the tree shows a count of classes at or under that node
- [x] Acceptance criterion: classes in the global namespace (empty string) are rendered without producing a malformed or blank tree node
- [x] Acceptance criterion: the namespace tree section appears before the per-cluster listings in the rendered output
- [x] Acceptance criterion: `tests/Unit/Reporting/ArchitectureMapRendererTest.php` covers a namespace tree with nested namespaces and correct per-node counts, including a case with a global-namespace class, asserted only through `ArchitectureMapRenderer::render()`'s output against a hand-computed expected literal

**Implemented:** `ArchitectureMapRenderer` now renders a deterministic, project-wide namespace tree before cluster listings. Namespace nodes show cumulative class counts, and global-namespace classes are rendered under `(global)`.

**Validation:** PHPUnit passes with 59 tests and 217 assertions. The namespace-tree test uses a hand-computed `render()` output literal covering nested namespaces, cumulative counts, global namespace handling, and ordering.
