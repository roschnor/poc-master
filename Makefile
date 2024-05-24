.DEFAULT_GOAL := help

appName = server

.PHONY: up
up: ## start docker application
	docker compose up

.PHONY: ub
ub: ## Build and run docker application with docker compose
	docker compose up --build --remove-orphans

.PHONY: enter
enter: ## Enter docker application called "app"
	docker compose exec -u root $(appName) bash -l

.PHONY: test
test: ## Run unit tests
	docker compose exec $(appName) ./vendor/bin/phpunit --testdox --colors="always" $(OPTIONS_PHPUNIT)

.PHONY: phpstan
phpstan: ## Run PHPStan
	docker compose exec $(appName) ./vendor/bin/phpstan analyse -c phpstan.neon $(OPTIONS_PHPSTAN)

.PHONY: phpstan-all
phpstan-all: ## Run PHPStan without the baseline, i.e. show all errors
	docker compose exec $(appName) ./vendor/bin/phpstan analyse -c phpstan-without-baseline.neon $(OPTIONS_PHPSTAN)

.PHONY: phpstan-base
phpstan-base: ## Regenerate PHPStan baseline (e.g. after fixing a bunch of warnings / errors)
	docker compose exec $(appName) ./vendor/bin/phpstan analyse -c phpstan.neon --generate-baseline $(OPTIONS_PHPSTAN)

.PHONY: fix
fix: ## Run php-cs-fixer dry-run
	docker compose exec $(appName) ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php --dry-run --diff $(OPTIONS_PHPCSFIXER)

.PHONY: fixit
fixit: ## Run php-cs-fixer
	docker compose exec $(appName) ./vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php $(OPTIONS_PHPCSFIXER)

.PHONY: rector
rector: ## Run rector dry-run
	docker compose exec $(appName) ./vendor/bin/rector --dry-run $(OPTIONS_RECTOR)

.PHONY: rectorit
rectorit: ## Run rector
	docker compose exec $(appName) ./vendor/bin/rector $(OPTIONS_RECTOR)

.PHONY: lint
lint: ## Run lint tools
	docker compose exec $(appName) bin/console lint:container
	docker compose exec $(appName) bin/console lint:yaml --parse-tags config

.PHONY: clear
clear: ## Clear cache
	docker compose exec $(appName) bin/console cache:clear

.PHONY: refresh-db
refresh-db: ## Refresh database with dev fixtures
	docker compose exec $(appName) bin/console doctrine:database:drop --force --if-exists
	docker compose exec $(appName) bin/console doctrine:database:create
	docker compose exec $(appName) bin/console doctrine:migrations:migrate --no-interaction
	docker compose exec $(appName) bin/console doctrine:fixtures:load --group=dev --no-interaction -v


.PHONY: prepare
prepare: ## Prepare and check (e.g. for commit) to see if any errors are present
	docker compose exec $(appName) bin/console doctrine:schema:validate
	$(MAKE) lint
	$(MAKE) phpstan
	$(MAKE) fix
	$(MAKE) test
	$(MAKE) rector
	$(MAKE) files-exist

.PHONY: cleanup
cleanup: ## php-cs-fixer (non-dry), then check
	$(MAKE) fixit
	$(MAKE) phpstan
	docker compose exec $(appName) bin/console doctrine:schema:validate
	$(MAKE) lint

.PHONY: openapi-validate
openapi-validate: ## Validate OpenAPI schema (alpha), needs openapi-generator-cli
	# npm install @openapitools/openapi-generator-cli -g
	docker compose exec $(appName) bin/console api:openapi:export --output=openapi-test/openapi.yaml
	openapi-generator-cli validate -i openapi-test/openapi.yaml

.PHONY: jwt
jwt: ## Generate JWT keys (localhost:8101)
	curl -X POST -H "Content-Type: application/json" http://localhost:8101/api/login_check -d '{"username":"admin@r9.test","password":"admin_test"}'

# Files to check for existence
FILES = composer.json composer.lock symfony.lock .gitignore

.PHONY: files-exist
files-exist: ## Check that a list of files exist, show all that do not exist, return error if at least one does not exist
	@for file in $(FILES); do \
		if [ ! -f "$$file" ]; then \
			echo "File $$file does not exist"; \
		fi; \
	done
	@for file in $(FILES); do \
		if [ ! -f "$$file" ]; then \
			exit 1; \
		fi; \
	done

help: ## Print out  this help
	@echo ""
	@echo "===================== Make Commands =========================="
	@grep -E '^[0-9a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) | sort | awk 'BEGIN {FS = ":.*?## "}; {printf "\033[36m%-15s\033[0m %s\n", $$1, $$2}'
	@echo "=============================================================="
	@echo ""
