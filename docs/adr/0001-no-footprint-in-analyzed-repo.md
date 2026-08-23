# Stop templating CONTEXT.md into analyzed projects; never write into the analyzed repo

Early on, the tool shipped `app:bootstrap-context`, which templated a `CONTEXT.md` glossary into an analyzed project, and `analyse` defaulted to writing `architecture.json`/`graph.svg`/`report.html` into the analyzed project's own directory (or a `build/` folder inside it). Both conflated this tool's job — deterministic static analysis — with glossary authoring, a human/domain judgment call, and left a footprint inside repos the tool only has read access to.

We removed `app:bootstrap-context` entirely (along with its template and bin wiring) and changed `analyse`'s default output to `out/<project-name>/` inside this tool's own repo, never inside the analyzed project; `--output` still allows an explicit override. In its place, `analyse` now generates an `architecture-map.md` artifact — a Markdown summary of the architecture model at cluster granularity — so an agent or human can still get oriented in an unfamiliar codebase quickly, without this tool authoring content inside someone else's repo.

`CONTEXT.md` authoring for analyzed projects is now entirely a manual or Agent-Skill task, outside this tool's scope.
