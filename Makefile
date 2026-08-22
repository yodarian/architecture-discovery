.PHONY: help start stop shell composer-install test analyse

DOCKER_COMPOSE := docker-compose -f docker/docker-compose.yml
DOCKER_COMPOSE_RUN := $(DOCKER_COMPOSE) run --rm app
OUTPUT ?= $(PROJECT)/build/architecture

help:
	@echo "Architecture Discovery - Development Commands"
	@echo ""
	@echo "Available targets:"
	@echo "  make start              Start the development container (runs bash)"
	@echo "  make stop               Stop and remove the development container"
	@echo "  make shell              Open an interactive shell in the running container"
	@echo "  make composer-install   Install PHP dependencies via Composer"
	@echo "  make test               Run PHPUnit test suite"
	@echo "  make analyse PROJECT=/path/to/project  Analyze a host project via Docker"
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

analyse:
	@test -n "$(PROJECT)" || (echo "Usage: make analyse PROJECT=/path/to/project [OUTPUT=/path/to/output]" && exit 2)
	@test -d "$(PROJECT)" || (echo "Project directory does not exist: $(PROJECT)" && exit 2)
	@mkdir -p "$(OUTPUT)"
	$(DOCKER_COMPOSE) run --rm \
		-v "$(PROJECT):/target:ro" \
		-v "$(OUTPUT):/output" \
		app php bin/bootstrap-context analyse /target --output /output
