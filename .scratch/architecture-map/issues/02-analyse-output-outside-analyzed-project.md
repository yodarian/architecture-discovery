# 02 — Stop `analyse` from writing output into the analyzed project

**What to build:** Running `analyse` without `--output` never writes any artifact into the project being analyzed. Default output lands inside this tool's own repo, keyed by the analyzed project's name, while `--output` still allows overriding the location explicitly.

**Blocked by:** None — can start immediately

- [ ] Acceptance criterion: omitting `--output` writes `architecture.json`/`graph.svg`/`report.html` into `out/<project-name>/` resolved relative to this repo's root, not the analyzed project's path
- [ ] Acceptance criterion: passing `--output` still writes to the given path exactly as before, taking precedence over the new default
- [ ] Acceptance criterion: `AnalyseCommandTest` covers both the new default location and the `--output` override, and asserts nothing is written into the analyzed fixture project's directory
- [ ] Acceptance criterion: `Makefile`'s `analyse` target defaults `OUTPUT` to a path inside this repo (e.g. `out/$(notdir $(PROJECT))`) instead of `$(PROJECT)/build/architecture`, while the existing `OUTPUT=` override keeps working
- [ ] Acceptance criterion: `README.md` and `docker/README.md` examples reflect the corrected default output location
