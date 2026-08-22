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

The CLI runs inside the container against paths visible inside that container. The
repository itself is mounted from the host into `/app`, so the repository must be
addressed as `/app` from inside the container. A project does not need to be
copied into the Docker image, but any external project must be made visible with
an additional bind mount.

To analyze the mounted repository:

```bash
docker compose -f docker/docker-compose.yml run --rm app \
	php bin/bootstrap-context analyse /app \
	--output /app/build/architecture
```

To analyze another project on the host, mount it at a container path and use
that container path in the command. For example, if the project is located at
`/home/fkas/projects/my-app` on the host:

```bash
docker compose -f docker/docker-compose.yml run --rm \
	-v /home/fkas/projects/my-app:/projects/my-app:ro \
	app php bin/bootstrap-context analyse /projects/my-app \
	--output /app/build/my-app
```

The host path and container path are different namespaces:

```text
Host:      /home/fkas/projects/my-app
Container: /projects/my-app
```

The command must use `/projects/my-app`. The `:ro` flag mounts the target
project read-only, while output is written to `/app/build/my-app`, which is
inside the repository bind mount and therefore appears on the host.

The same workflow is available through Make. Use `PROJECT` for the host path;
the target project is mounted read-only inside Docker automatically:

```bash
make analyse PROJECT=/home/fkas/projects/my-app
```

By default, artifacts are written to
`/home/fkas/projects/my-app/build/architecture`. Override the host output
directory with `OUTPUT`:

```bash
make analyse \
	PROJECT=/home/fkas/projects/my-app \
	OUTPUT=/home/fkas/architecture-output/my-app
```

Make does not reliably accept a host path as a positional argument after a
target, so use the `PROJECT=/path` form rather than
`make analyse --/path/to/project`.

If PHP and Composer are installed locally, the CLI can also be run directly on
the host with the host project path. Docker is provided for a consistent PHP
runtime and does not require projects to be copied into the image.

To generate a `CONTEXT.md` file instead of running analysis, use the existing
bootstrap command:

```bash
docker compose -f docker/docker-compose.yml run --rm \
	-v /home/fkas/projects/my-app:/projects/my-app:ro \
	app php bin/bootstrap-context app:bootstrap-context /projects/my-app
```

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
