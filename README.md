# Architecture Discovery

This project provides a small PHP CLI tool for generating project context and supporting architecture discovery workflows. The repository includes a Docker-based development environment so you can work without installing PHP and Composer locally.

## Prerequisites

Before you start, make sure you have the following installed on your machine:

- Git
- Docker
- Docker Compose (or Docker Desktop with Compose support)
- Make (for convenient commands)

See [docker/README.md](docker/README.md) for Docker-specific configuration details.

## Clone the repository

```bash
git clone <repository-url>
cd architecture-discovery
```

## Start the development environment

From the project root, build the container image:

```bash
make build
```

Then start an interactive shell inside the container:

```bash
make start
```

This starts the container defined in `docker-compose.yml`, mounts the repository into `/app`, and opens a bash shell in the project directory.

## Install dependencies

Once inside the container, or from your host machine, install PHP dependencies:

```bash
make composer-install
```

This runs `composer install` inside the container with optimized settings.

## Working inside the container

You can use any of the following commands:

```bash
make start           # Start an interactive shell
make shell           # Alias for make start
make composer-install # Install PHP dependencies
make test            # Run the PHPUnit test suite
make stop            # Stop and remove containers
```

Or, if you prefer to use docker-compose directly:

```bash
docker-compose run --rm app bash
docker-compose run --rm app composer install
docker-compose run --rm app ./vendor/bin/phpunit
docker-compose down
```

## Run the app

The CLI entry point is:

```bash
make start
php bin/bootstrap-context /path/to/project
```

Or, from outside the container:

```bash
docker-compose run --rm app php bin/bootstrap-context /path/to/project
```

This generates a `CONTEXT.md` file for the target project based on the bundled template.

## Run tests

Execute the PHPUnit test suite:

```bash
make test
```

Or run tests with specific options:

```bash
docker-compose run --rm app ./vendor/bin/phpunit --filter=TestName
```

## Stop and clean up

When you are finished, exit the container:

```bash
exit
```

You can also stop and remove containers created by Compose:

```bash
make stop
```

or with docker-compose directly:

```bash
docker-compose down
```

## Notes

- The project container uses PHP 8.2 CLI.
- The application code is mounted into `/app` from the host project directory.
- Use `make composer-install` to install PHP dependencies (not automatic on startup).
- PHPUnit is available as a dev dependency via `make test`.
- If you need to change the runtime version or dependencies, update the `Dockerfile` and `docker-compose.yml` accordingly.

## Troubleshooting

If dependencies are missing, ensure you have run:

```bash
make composer-install
```

If Docker cannot find the service, confirm that Docker is running and that the project root contains the `docker-compose.yml` and `Makefile` files.
