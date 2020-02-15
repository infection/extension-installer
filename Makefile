.PHONY: ci test prerequisites

# Use any most recent PHP version
PHP=$(shell which php)
PHPDBG=php

# Default parallelism
JOBS=$(shell nproc)

# Default silencer if installed
SILENT=$(shell which chronic)

# PHP CS Fixer
PHP_CS_FIXER=vendor/bin/php-cs-fixer
PHP_CS_FIXER_ARGS=--cache-file=build/cache/.php_cs.cache --verbose
export PHP_CS_FIXER_IGNORE_ENV=1

# PHPUnit
PHPUNIT=vendor/bin/phpunit
PHPUNIT_COVERAGE_CLOVER=--coverage-clover=build/logs/clover.xml
PHPUNIT_ARGS=--coverage-xml=build/logs/coverage-xml --log-junit=build/logs/junit.xml $(PHPUNIT_COVERAGE_CLOVER)

# PHPStan
PHPSTAN=vendor/bin/phpstan
PHPSTAN_ARGS=analyse src tests -c .phpstan.neon

# Psalm
PSALM=vendor/bin/psalm
PSALM_ARGS=--show-info=false

# Composer
COMPOSER=$(PHP) $(shell which composer)

# Infection
INFECTION=vendor/bin/infection
MIN_MSI=97
MIN_COVERED_MSI=100
INFECTION_ARGS=--min-msi=$(MIN_MSI) --min-covered-msi=$(MIN_COVERED_MSI) --threads=$(JOBS) --log-verbosity=default --show-mutations

all: test

##############################################################
# Continuous Integration                                     #
##############################################################

ci-test: SILENT=
ci-test: prerequisites
	$(SILENT) $(PHPDBG) $(PHPUNIT) $(PHPUNIT_COVERAGE_CLOVER)

ci-analyze: SILENT=
ci-analyze: prerequisites ci-cs ci-infection ci-phpstan ci-psalm

ci-phpunit: ci-cs
	$(SILENT) $(PHPDBG) $(PHPUNIT) $(PHPUNIT_ARGS)
	cp build/logs/junit.xml build/logs/phpunit.junit.xml

ci-infection:
	$(SILENT) $(PHP) $(INFECTION) $(INFECTION_ARGS)

ci-phpstan: ci-cs
	$(SILENT) $(PHP) $(PHPSTAN) $(PHPSTAN_ARGS) --no-progress

ci-psalm: ci-cs
	$(SILENT) $(PHP) $(PSALM) $(PSALM_ARGS) --no-cache --shepherd

ci-cs: prerequisites
	$(SILENT) $(PHP) $(PHP_CS_FIXER) $(PHP_CS_FIXER_ARGS) --dry-run --stop-on-violation fix

##############################################################
# Development Workflow                                       #
##############################################################

test: phpunit analyze composer-validate

.PHONY: composer-validate
composer-validate: test-prerequisites
	$(SILENT) $(COMPOSER) validate --strict

test-prerequisites: prerequisites composer.lock

phpunit: cs
	$(SILENT) $(PHP) $(PHPUNIT) $(PHPUNIT_ARGS) --verbose
	cp build/logs/junit.xml build/logs/phpunit.junit.xml
	$(SILENT) $(PHP) $(INFECTION) $(INFECTION_ARGS)

analyze: cs
	$(SILENT) $(PHP) $(PHPSTAN) $(PHPSTAN_ARGS)
	$(SILENT) $(PHP) $(PSALM) $(PSALM_ARGS)

cs: test-prerequisites
	$(SILENT) $(PHP) $(PHP_CS_FIXER) $(PHP_CS_FIXER_ARGS) --diff fix
	LC_ALL=C sort -u .gitignore -o .gitignore

##############################################################
# Prerequisites Setup                                        #
##############################################################

# We need both vendor/autoload.php and composer.lock being up to date
.PHONY: prerequisites
prerequisites: report-php-version build/cache vendor/autoload.php composer.lock infection.json.dist .phpstan.neon

# Do install if there's no 'vendor'
vendor/autoload.php:
	$(SILENT) $(COMPOSER) install --prefer-dist
	test -d vendor/infection/infection/src/StreamWrapper/ && rm -fr vendor/infection/infection/src/StreamWrapper/ && $(SILENT) $(COMPOSER) dump-autoload || true

# If composer.lock is older than `composer.json`, do update,
# and touch composer.lock because composer not always does that
composer.lock: composer.json
	$(SILENT) $(COMPOSER) update && touch composer.lock

build/cache:
	mkdir -p build/cache

.PHONY: report-php-version
report-php-version:
	# Using $(PHP)
