# Architecture Discovery

This project provides a small PHP CLI tool for generating project context and supporting architecture discovery workflows. The repository includes a Docker-based development environment so you can work without installing PHP and Composer locally.

## Prerequisites

Before you start, make sure you have the following installed on your machine:

- Git
- Docker
- Docker Compose (or Docker Desktop with Compose support)

## Clone the repository

```bash
git clone <repository-url>
cd architecture-discovery
```

## Start the development environment

From the project root, build the container image:

```bash
docker-compose build --pull
```

If you are using Docker Compose v2, the equivalent command is:

```bash
docker compose build --pull
```

Then start an interactive shell inside the container:

```bash
docker-compose run --rm app bash
```

or:

```bash
docker compose run --rm app bash
```

This starts the container defined in `docker-compose.yml`, mounts the repository into `/app`, and opens a bash shell in the project directory.

## Working inside the container

Once inside the container, you can run commands such as:

```bash
composer install
php bin/bootstrap-context /path/to/project
```

The container entrypoint automatically runs `composer install` when a `composer.json` file is present, so dependency installation is usually handled for you when the container starts.

## Run the app

The CLI entry point is:

```bash
php bin/bootstrap-context /path/to/project
```

This generates a `CONTEXT.md` file for the target project based on the bundled template.

## Stop and clean up

When you are finished, exit the container:

```bash
exit
```

You can also stop and remove containers created by Compose:

```bash
docker-compose down
```

or:

```bash
docker compose down
```

## Notes

- The project container uses PHP 8.2 CLI.
- The application code is mounted into `/app` from the host project directory.
- If you need to change the runtime version or dependencies, update the `Dockerfile` and `docker-compose.yml` accordingly.

## Troubleshooting

If `composer install` fails or dependencies are missing, run:

```bash
docker-compose run --rm app composer install
```

If Docker cannot find the service, confirm that Docker is running and that the project root contains the `docker-compose.yml` file.
