# 01 — Dockerized project bootstrap

**What to build:** A working containerized development environment for the architecture discovery project, including PHP, Composer, the test runner, and the CLI entrypoint, so contributors can run the project without installing PHP locally.

**Blocked by:** None — can start immediately.

**Status:** completed

- [x] Acceptance criterion: a Docker setup exists for local development and test execution
  - ✅ Dockerfile with PHP 8.2, Composer, and required extensions
  - ✅ docker-compose.yml configured for local development with volume mounts
  - ✅ PHPUnit 11.5.56 installed as dev dependency for test execution
- [x] Acceptance criterion: the project can be bootstrapped and run inside the container without a host PHP installation
  - ✅ docker/php-entrypoint.sh automatically runs `composer install` on container start
  - ✅ CLI entrypoint (./bin/bootstrap-context) works correctly inside container
  - ✅ All required tools (PHP 8.2, Composer 2, Symfony Console) available without host installation
- [x] Acceptance criterion: the development workflow is documented for contributors and CI use
  - ✅ README-docker.md with comprehensive usage documentation
  - ✅ Commands provided for building image, running shell, installing dependencies, and executing tests

## Implementation Details

- **Dockerfile**: Multi-stage build with PHP 8.2 CLI, Composer 2, Git, and Zip extension
- **docker-compose.yml**: Service configuration with delegated volume mount for performance
- **PHP Entrypoint**: Automatic composer install with error handling
- **CLI Tool**: Symfony Console application that generates CONTEXT.md files for target projects
- **Test Runner**: PHPUnit configured for test execution in container environment

## Verification

All components tested and working:
- Docker image builds successfully
- PHP 8.2.33 available in container
- Composer 2 available for dependency management  
- PHPUnit 11.5.56 available for test execution
- CLI tool (bootstrap-context) executes correctly
- No host PHP installation required
