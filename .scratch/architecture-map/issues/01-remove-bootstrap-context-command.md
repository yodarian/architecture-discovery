# 01 — Remove BootstrapContextCommand and rename the bin entrypoint

**What to build:** `bin/architecture-discovery analyse ...` works end-to-end as the tool's sole entrypoint. The `app:bootstrap-context` command, its CONTEXT.md template, and all references to `bootstrap-context` are gone from the codebase and documentation.

**Blocked by:** None — can start immediately

**Status:** done

- [x] Acceptance criterion: `src/Application/Command/BootstrapContextCommand.php` and `resources/templates/CONTEXT.md` are deleted
- [x] Acceptance criterion: `bin/bootstrap-context` is renamed to `bin/architecture-discovery`, and only registers `AnalyseCommand`
- [x] Acceptance criterion: `composer.json`'s `bin` entry points at `bin/architecture-discovery`
- [x] Acceptance criterion: `Makefile`, `README.md`, and `docker/README.md` no longer reference `bootstrap-context` or `app:bootstrap-context`, and all example invocations use `bin/architecture-discovery`
- [x] Acceptance criterion: the test suite passes with no reference to `BootstrapContextCommand` anywhere

## Comments

Implemented in commit `890f2a1`. Full suite (54 tests) passes; vendor autoload maps were hand-edited to drop only the removed class entry rather than a full `composer dump-autoload` regeneration, to keep the diff minimal.
