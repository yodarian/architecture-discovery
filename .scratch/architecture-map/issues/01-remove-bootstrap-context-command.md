# 01 — Remove BootstrapContextCommand and rename the bin entrypoint

**What to build:** `bin/architecture-discovery analyse ...` works end-to-end as the tool's sole entrypoint. The `app:bootstrap-context` command, its CONTEXT.md template, and all references to `bootstrap-context` are gone from the codebase and documentation.

**Blocked by:** None — can start immediately

- [ ] Acceptance criterion: `src/Application/Command/BootstrapContextCommand.php` and `resources/templates/CONTEXT.md` are deleted
- [ ] Acceptance criterion: `bin/bootstrap-context` is renamed to `bin/architecture-discovery`, and only registers `AnalyseCommand`
- [ ] Acceptance criterion: `composer.json`'s `bin` entry points at `bin/architecture-discovery`
- [ ] Acceptance criterion: `Makefile`, `README.md`, and `docker/README.md` no longer reference `bootstrap-context` or `app:bootstrap-context`, and all example invocations use `bin/architecture-discovery`
- [ ] Acceptance criterion: the test suite passes with no reference to `BootstrapContextCommand` anywhere
