# 02 — Stop `analyse` from writing output into the analyzed project

**What to build:** Running `analyse` without `--output` never writes any artifact into the project being analyzed. Default output lands inside this tool's own repo, keyed by the analyzed project's name, while `--output` still allows overriding the location explicitly.

**Blocked by:** None — can start immediately

**Status:** done

- [x] Acceptance criterion: omitting `--output` writes `architecture.json`/`graph.svg`/`report.html` into `out/<project-name>/` resolved relative to this repo's root, not the analyzed project's path
- [x] Acceptance criterion: passing `--output` still writes to the given path exactly as before, taking precedence over the new default
- [x] Acceptance criterion: `AnalyseCommandTest` covers both the new default location and the `--output` override, and asserts nothing is written into the analyzed fixture project's directory
- [x] Acceptance criterion: `Makefile`'s `analyse` target defaults `OUTPUT` to a path inside this repo (e.g. `out/$(notdir $(PROJECT))`) instead of `$(PROJECT)/build/architecture`, while the existing `OUTPUT=` override keeps working
- [x] Acceptance criterion: `README.md` and `docker/README.md` examples reflect the corrected default output location

## Comments

Implemented in commit `c888e30`. `docker/README.md` had no output-location references to begin with. `/out/` added to `.gitignore` since it's now a default artifact location inside this repo.
