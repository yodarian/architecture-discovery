# Research: best practices for LLM-facing "codebase map" artifacts

**Question:** How do established tools format/select content for a codebase summary handed to an AI coding agent, under a token budget? Specifically: content granularity, ranking/selection strategy, textual format, and whether clustering is done by dependency-graph connectivity vs. directory/namespace boundaries.

**Scope note:** This repo currently derives `architecture-map.md` from `architecture.json` by clustering classes via connected-components of the dependency graph, then listing per-cluster class counts and coupling metrics. This research is meant to ground a redesign of that map.

---

## 1. Aider's "repo map"

Primary sources: [aider.chat/docs/repomap.html](https://aider.chat/docs/repomap.html), [aider.chat/2023/10/22/repomap.html](https://aider.chat/2023/10/22/repomap.html) (the original design write-up), and the actual source at [github.com/Aider-AI/aider/blob/main/aider/repomap.py](https://github.com/Aider-AI/aider/blob/main/aider/repomap.py).

### Content and granularity
The map is **symbol-level**, not file-level or whole-file. Per the docs: "The repo map contains a list of the files in the repo, along with the key symbols which are defined in each file. It shows how each of these symbols are defined, by including the critical lines of code for each definition." ([repomap.html](https://aider.chat/docs/repomap.html))

Symbols are extracted per-file via **tree-sitter** parsing against per-language `tags.scm` queries (e.g. [`python-tags.scm`](https://github.com/Aider-AI/aider/blob/main/aider/queries/tree-sitter-language-pack/python-tags.scm)) that tag AST nodes as `name.definition.function`, `name.definition.class`, `name.reference.call`, etc. In `repomap.py`, `get_tags_raw()` runs these queries and yields `Tag(rel_fname, fname, line, name, kind)` tuples with `kind` either `"def"` or `"ref"`. If a language's tags file has no reference captures (true for some grammars), aider falls back to a Pygments lexer to backfill plain-text identifier references (`get_tags_raw`, lines ~312–348 of `repomap.py`).

The rendered map shows only the **defining line(s)** of top-ranked symbols (via `TreeContext.add_lines_of_interest` / `render_tree()`), not full function bodies — e.g. a class signature line, a decorated method's signature, but not its body. This is confirmed by the sample map in the docs showing bare `class Coder:` and `def run(self, with_message=None):` lines followed by `⋮...` (elision markers).

### Ranking/selection under a token budget
Confirmed directly from `get_ranked_tags()` in [repomap.py](https://github.com/Aider-AI/aider/blob/main/aider/repomap.py#L367):

1. Build a `networkx.MultiDiGraph` where **nodes are files** and a directed edge `referencer -> definer` exists for each identifier that `referencer` references and `definer` defines, weighted by (roughly) `sqrt(num_refs) * multiplier`.
2. The multiplier boosts identifiers that: are "important-looking" (snake_case/camelCase/kebab-case and ≥8 chars get `mul *= 10`), are explicitly `mentioned_idents` from the chat (`mul *= 10`), or are referenced from a file already in the chat context (`mul *= 50`). It discounts identifiers with a leading underscore (`mul *= 0.1`) or defined in more than 5 places (`mul *= 0.1`).
3. Runs **`networkx.pagerank(G, weight="weight", personalization=..., dangling=...)`** — this is literally the PageRank algorithm, not merely "PageRank-like." `personalization` gives extra initial weight to files already open in the chat (`personalize = 100 / len(fnames)`, boosted for chat/mentioned files) so the ranking is biased toward what's currently relevant, not just globally central.
4. Each file's PageRank mass is redistributed across its out-edges proportional to edge weight, accumulating a rank per `(definer_file, identifier)` pair — i.e. **individual symbols end up ranked**, not just files.
5. `get_ranked_tags_map_uncached()` then does a **binary search over how many top-ranked tags to include** (`lower_bound`/`upper_bound`/`middle`), rendering a candidate tree at each step and counting tokens (`token_count()`), converging on the largest tag set that fits within `max_map_tokens` (within a 15% error tolerance) — this is an explicit budget-fitting search, not a fixed heuristic cutoff.

Token budget defaults to 1024 tokens (`--map-tokens`), auto-expanded when no files are yet in the chat (`map_mul_no_files`, default 8x, capped by the model's context window minus a 4096-token padding) so aider can give a bigger initial overview before the user has focused the conversation ([repomap.html](https://aider.chat/docs/repomap.html) "Optimizing the map" section).

### Rendered format
Plain text, **not JSON/XML** — a per-file grouped listing where each file path is a header line (`aider/coders/base_coder.py:`) followed by indented, elided source excerpts using a `⋮...` marker for skipped regions and `│` prefixes for kept lines (implemented via `grep_ast.TreeContext` in `render_tree()`/`to_tree()`). This format is described as looking "similar to output produced by `ctags --output-format=json`" historically but is now generated by re-rendering source through tree-sitter's line-of-interest context, not literal ctags output ("What about ctags?" section, [2023-10-22 post](https://aider.chat/2023/10/22/repomap.html)).

### Explicit framing vs. RAG/embeddings
Aider's own SWE-bench write-up explicitly contrasts this approach with embedding-based retrieval: "Most coding agents use some combination of RAG, vector search and providing the LLM with tools to interactively explore the code base. Aider instead uses a repository map... created through static analysis of the code's abstract syntax tree and call graph." ([swe-bench-lite.md](https://github.com/Aider-AI/aider/blob/main/aider/website/_posts/2024-05-22-swe-bench-lite.md))

---

## 2. Repomix (repomix.com)

Primary source: [repomix.com/guide/output](https://repomix.com/guide/output).

### Content and granularity
Repomix is a **whole-repo packer**, not a ranked/pruned map: it concatenates full file contents plus a directory-tree listing plus a metadata header ("file_summary") into one document. It also supports optional git log inclusion. There is a separate "Code Compression" feature (linked from the output-formats page) for reducing token count while trying to preserve structure — but the core artifact is file-level, not symbol-ranked.

### Format
Supports four explicit output styles: **XML** (default), Markdown, JSON, and Plain Text. Repomix's own docs justify XML-as-default by citing model vendors' own prompting guidance: Anthropic's docs "explicitly recommend XML tags for structuring prompts, stating that 'Claude was exposed to such prompts during training'" ([Anthropic prompt-engineering docs](https://docs.anthropic.com/en/docs/build-with-claude/prompt-engineering/use-xml-tags)), and Google's Vertex AI docs recommend structured formats including XML for complex tasks. Every format wraps the same three sections: `file_summary` (metadata/instructions), `directory_structure` (a plain indented tree), and `files` (full file bodies).

### Token/relevance management
No ranking algorithm — relies on user-specified include/exclude globs and the optional compression feature; token counting is reported for planning purposes but is not itself the selection mechanism the way it is in Aider.

---

## 3. Gitingest

Primary source: [github.com/coderamp-labs/gitingest](https://github.com/coderamp-labs/gitingest) (README).

Same family as Repomix: turns a repo (local path or GitHub URL) into a single "prompt-friendly text ingest" comprising a summary, a directory tree, and concatenated file content (`summary, tree, content = ingest(...)`). It reports file/directory structure stats, extract size, and **token count** (via `tiktoken`) but, per the README, has no ranking or pruning algorithm — it's an unfiltered (aside from gitignore/include-exclude patterns) full-content dump, same granularity tradeoff as Repomix: file-level, not symbol-level.

---

## 4. Cursor

Primary source: [cursor.com/docs/context/codebase-indexing](https://cursor.com/docs/context/codebase-indexing) (redirects to the current Cursor docs "Search" page).

Cursor does **not** produce a static "map" artifact at all. Instead:
- **Instant Grep**: an in-house search engine (claimed faster than ripgrep on large codebases) used for exact/regex symbol matches — the agent constructs patterns like `import.*PaymentService` to trace references, run automatically without a persistent map file.
- **Embeddings-based semantic index**: chunks of code are embedded; filenames are obfuscated and chunk content encrypted before being sent to Cursor's servers, decrypted client-side on retrieval (privacy-motivated architecture, not naive plaintext indexing).
- **Explore subagent**: for broad/open-ended searches, Cursor can spawn a subagent with its own context window and a faster model that runs many parallel searches and returns only a *summarized* set of relevant findings back to the main conversation — an explicit token-budget-management technique that keeps raw search results out of the primary context.

So Cursor's answer to "how to fit context in budget" is agentic, on-demand, per-query retrieval + subagent summarization, not a single upfront compact map like Aider's.

---

## 5. Sourcegraph Cody

Primary source: [sourcegraph.com/docs/cody/core-concepts/context](https://sourcegraph.com/docs/cody/core-concepts/context).

Cody assembles a prompt from three parts — prefix (task instructions), user input, and **context** — and draws that context from three sources: keyword search (with automatic query rewriting), the Sourcegraph Search API/index, and "Code Graph" analysis ("analyzing the structure of the code, Cody examines how components are interconnected and used, finding context based on code elements' relationships"). Docs are explicit that this is retrieval **per query** via `@`-mention context pickers, not a single static whole-repo summary. Token/context-window size is an admin-configurable limit (see linked [token-limits doc]) rather than an algorithmic ranking budget the docs describe in detail — Cody's docs don't disclose a PageRank-equivalent ranking mechanism the way Aider's do.

---

## Cross-cutting findings

**(a) Content granularity.** There's a clear split between two families:
- **Symbol/signature-level, ranked, elided** (Aider): shows class/function signatures and definition lines only, selected by importance, explicitly designed to answer "what exists and how do I call it" without full bodies.
- **File-level, unranked, full-content** (Repomix, Gitingest): pack complete file contents plus a directory tree; rely on include/exclude filtering and compression rather than symbol-level selection.
- **Query-time chunk retrieval, no persistent map** (Cursor, Cody): granularity is decided per-query via embeddings/keyword/graph search over chunks, returned just-in-time rather than precomputed into one artifact.

No primary source found describes a **directory/namespace/module-level summary** as the primary organizing unit of these artifacts — module/directory structure appears only as a supplementary "directory tree" listing (Repomix, Gitingest) for orientation, never as the unit that content is grouped/ranked by. See point (d) below.

**(b) Ranking/selection strategy for token budget.** Only Aider has a documented, source-verified graph-ranking algorithm: PageRank over a **file-level reference graph** (edges = "file A references a symbol defined in file B", weighted and personalized toward chat-relevant files), with per-symbol rank redistribution and a binary-search token-fitting loop. Repomix/Gitingest don't rank at all — they report token counts but leave selection to the user's include/exclude config or an optional compression pass. Cursor/Cody push the problem to query time (embeddings similarity + keyword/graph search), explicitly avoiding a single fixed-budget artifact; Cursor additionally uses a subagent to *summarize* raw search results before they re-enter the main context, which is itself a token-budget technique, just not a graph-ranking one.

**(c) Textual format.** No universal convention. Aider uses a custom plain-text, per-file-grouped, elided-source format (not JSON/XML) specifically because it's meant to look like abbreviated source code the model already understands from training data. Repomix explicitly tested/chose **XML** as default because model vendors (Anthropic, Google) document that structured/XML-tagged prompts perform better, but also ships Markdown/JSON/plain-text for different downstream uses (JSON for programmatic post-processing, Markdown for human readability). Cursor/Cody don't expose a fixed textual "map" format since context is assembled dynamically per conversation turn.

**(d) Module/directory/namespace grouping vs. dependency-graph connectivity — the question most relevant to this repo's clustering decision.**
None of the primary sources reviewed group or rank content by directory/namespace/package boundaries as their main selection unit:
- Aider ranks by **file-level PageRank over the reference graph** — the graph-connectivity approach this repo currently uses for its clusters, not module boundaries. Aider's node is the file, and edges are actual cross-references, so cohesive-but-differently-packaged code (e.g. two classes in a shared namespace that don't call each other) would *not* be clustered together, and two classes in different namespaces that call each other heavily *would* rank/render adjacently through shared "important identifier" hubs.
- Repomix/Gitingest do surface **directory structure** but purely as an orientation tree alongside full file dumps — it's not used to decide what content gets included or excluded, and there's no "per-directory cluster" summarization step.
- Cursor/Cody don't cluster at all in any documented artifact; retrieval is per-chunk/per-file, not per-module.

No primary source argues explicitly for or against directory/namespace grouping as a superior clustering unit; the strongest applicable signal is Aider's design rationale itself: the goal of the map is to show the LLM "how each of these symbols are defined... [so it] can probably figure out how to use the API exported from a module just based on the details shown," and the ranking is deliberately based on **actual usage relationships** (who calls/references whom) rather than physical code organization, because physical organization (directory/namespace) doesn't always correlate with what's relevant to the current task — that's the entire reason Aider needs a graph-ranking step at all instead of just listing files/namespaces in project order.

**Practical implication for this repo:** the dependency-graph-connectivity approach this project already uses for clustering is closer to established, validated prior art (Aider) than a switch to pure directory/namespace grouping would be. If directory/namespace information is added, primary sources suggest doing so as a **supplementary orientation layer** (a directory tree, as Repomix/Gitingest do) alongside — not instead of — connectivity-based/importance-ranked grouping, rather than replacing the graph-based clustering unit entirely.

---

## Sources consulted
- Aider docs: https://aider.chat/docs/repomap.html
- Aider blog (original design): https://aider.chat/2023/10/22/repomap.html
- Aider source: https://github.com/Aider-AI/aider/blob/main/aider/repomap.py
- Aider SWE-bench write-up: https://github.com/Aider-AI/aider/blob/main/aider/website/_posts/2024-05-22-swe-bench-lite.md
- Repomix docs: https://repomix.com/guide/output
- Gitingest README: https://github.com/coderamp-labs/gitingest
- Cursor docs: https://cursor.com/docs/context/codebase-indexing
- Sourcegraph Cody docs: https://sourcegraph.com/docs/cody/core-concepts/context

**Not found as primary sources / could not access:** Continue.dev's codebase-context docs (404 at the expected paths) and GitHub Copilot Workspace's own technical documentation were not located as accessible first-party pages during this pass; they are omitted rather than represented via secondary sources.
