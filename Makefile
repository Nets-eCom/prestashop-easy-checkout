PATHS =
-include Makefile.local
# Misc
.DEFAULT_GOAL = help
.PHONY        : help

## —— Makefile   ——————————————————————————————————————————————————————————————
help: ## Outputs this help screen
	@grep -E '(^[a-zA-Z0-9\./_ -]+:.*?##.*$$)|(^##)' $(MAKEFILE_LIST) | awk 'BEGIN {FS = ":.*?## "}{printf "\033[32m%-30s\033[0m %s\n", $$1, $$2}' | sed -e 's/\[32m##/[33m/'

check-all: rector-check cs-check phpunit license-check phpstan
fix-all: cs-fix rector-fix

license-check: ## Check license header in files
	vendor/bin/header-stamp --license=vendor/prestashop/header-stamp/assets/afl.txt --exclude=vendor,tests,_dev,frontend,rector.php,composer.json,views/js/app.js --dry-run

license-apply: ## Apply header block to files
	vendor/bin/header-stamp --license=vendor/prestashop/header-stamp/assets/afl.txt --exclude=vendor,tests,_dev,frontend,rector.php,composer.json,views/js/app.js

cs-check: ## php-cs-fixer check
	vendor/bin/php-cs-fixer check

cs-fix: ## php-cs-fixer fix
	vendor/bin/php-cs-fixer fix

rector-check: ## Rector analysis
	vendor/bin/rector process --dry-run

rector-fix: ## Rector Fix
	vendor/bin/rector process

phpunit: ## PHPUnit test
	vendor/bin/phpunit ./tests

phpstan: ## Run PHPStan analysis on specified paths for different presta versions (separated by ';')
	@LIST=$$(echo "$(PATHS)" | tr ';' ' ' | xargs); \
	if [ -z "$$LIST" ]; then \
		echo "\033[0;31mError: PATHS variable is empty!\033[0m"; \
		echo "Define it in Makefile.local or run: make phpstan PATHS=\"path\""; \
		exit 1; \
	fi; \
	for path in $$LIST; do \
		echo "=== Starting up PHPStan for: $$path ==="; \
		_PS_ROOT_DIR_=$$path vendor/bin/phpstan analyse -c tests/phpstan/phpstan.neon.dist; \
	done