BASE = $(realpath ./)
SRC_DIR = $(BASE)/src
TESTS_DIR = $(BASE)/tests
BUILD_DIR = $(BASE)/build
DOCS_DIR = $(BASE)/docs
COVERAGE_DIR = $(BASE)/coverage
BIN_DIR = $(BASE)/vendor/bin

COMMIT = $(shell git rev-parse --short HEAD)
MODULE_VERSION = $(shell grep -Po "VERSION = '\K[^']*" src/backoffice/FmUtils.php)

build: clean
	mkdir $(BUILD_DIR)
	rsync -a --exclude='.*' $(SRC_DIR) $(BUILD_DIR)
	mv $(BUILD_DIR)/src $(BUILD_DIR)/fyndiqmerchant
	cp $(DOCS_DIR)/* $(BUILD_DIR)/fyndiqmerchant
	cd $(BUILD_DIR); zip -r -X fyndiq-prestashop-module-v$(MODULE_VERSION)-$(COMMIT).zip fyndiqmerchant/
	rm -r $(BUILD_DIR)/fyndiqmerchant

clean:
	rm -r $(BUILD_DIR)

css:
	cd $(SRC_DIR)/backoffice/frontend/css; scss -C --sourcemap=none main.scss:main.css

test:
	$(BIN_DIR)/phpunit

scss-lint:
	scss-lint $(SRC_DIR)/admin/fyndiq/frontend/css/*.scss

php-lint:
	find $(SRC_DIR) -name "*.php" -print0 | xargs -0 -n1 -P8 php -l

phpmd:
	$(BIN_DIR)/phpmd $(SRC_DIR) --exclude /includes/ text cleancode,codesize,controversial,design,naming,unusedcode

coverage: clear_coverage
	$(BIN_DIR)/phpunit --coverage-html $(COVERAGE_DIR)

clear_coverage:
	rm -rf $(COVERAGE_DIR)

sniff:
	$(BIN_DIR)/phpcs --standard=PSR2 --extensions=php --ignore=shared,templates,api --colors $(SRC_DIR)

sniff-fix:
	$(BIN_DIR)/phpcbf --standard=PSR2 --extensions=php --ignore=shared,templates,api $(SRC_DIR)
	$(BIN_DIR)/phpcbf --standard=PSR2 --extensions=php $(TESTS_DIR)

compatinfo:
	$(BIN_DIR)/phpcompatinfo analyser:run $(SRC_DIR)
