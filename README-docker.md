# Dockerized development

This project includes a lightweight Docker development environment for contributors who don't want to install PHP locally.

Basic commands

- Build the image:

```bash
docker-compose build --pull
```

- Open a shell in the container:

```bash
docker-compose run --rm app bash
```

- Run Composer inside the container:

```bash
docker-compose run --rm app composer install
```

- Run the test suite (if present):

```bash
docker-compose run --rm app ./vendor/bin/phpunit
```

Notes
- The container copies the repository into `/app` and the `docker/php-entrypoint.sh` script will attempt to run `composer install` if a `composer.json` file is present.
- Adjust PHP version in the `Dockerfile` if you need a different runtime.
