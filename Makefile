.PHONY: help start stop shell composer-install test

DOCKER_COMPOSE := docker-compose -f docker/docker-compose.yml
DOCKER_COMPOSE_RUN := $(DOCKER_COMPOSE) run --rm app

help:
	@echo "Architecture Discovery - Development Commands"
	@echo ""
	@echo "Available targets:"
	@echo "  make start              Start the development container (runs bash)"
	@echo "  make stop               Stop and remove the development container"
	@echo "  make shell              Open an interactive shell in the running container"
	@echo "  make composer-install   Install PHP dependencies via Composer"
	@echo "  make test               Run PHPUnit test suite"
	@echo "  make build              Build the Docker image"
	@echo ""

build:
	$(DOCKER_COMPOSE) build --pull

start:
	$(DOCKER_COMPOSE) run --rm app bash

stop:
	$(DOCKER_COMPOSE) down

shell:
	$(DOCKER_COMPOSE) run --rm app bash

composer-install:
	$(DOCKER_COMPOSE_RUN) composer install --prefer-dist

test:
	$(DOCKER_COMPOSE_RUN) ./vendor/bin/phpunit
