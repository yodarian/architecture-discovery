# Dockerized development

This project includes a lightweight Docker development environment for contributors who don't want to install PHP locally.

## Quick Start

The easiest way to get started is using the Makefile from the project root:

```bash
make build              # Build the Docker image
make composer-install   # Install PHP dependencies
make start              # Open an interactive shell in the container
make test               # Run the test suite
make stop               # Stop and remove containers
```

See the root `README.md` for more details on the development workflow.

## Docker Commands Reference

If you prefer to use docker-compose directly:

- Build the image:

```bash
docker-compose -f docker/docker-compose.yml build --pull
```

- Open a shell in the container:

```bash
docker-compose -f docker/docker-compose.yml run --rm app bash
```

- Run Composer inside the container:

```bash
docker-compose -f docker/docker-compose.yml run --rm app composer install
```

- Run the test suite:

```bash
docker-compose -f docker/docker-compose.yml run --rm app ./vendor/bin/phpunit
```

## Docker Configuration

- **Dockerfile**: Located at `docker/Dockerfile`, uses PHP 8.2 CLI with required extensions
- **Entrypoint**: `docker/php-entrypoint.sh` - simply executes the passed command
- **Build Context**: Build is configured in `docker/docker-compose.yml`
- **Volume Mount**: Repository mounted to `/app` with `delegated` mode for performance

## Adjustments

- Adjust PHP version in `docker/Dockerfile` if you need a different runtime.
