.PHONY: build test coverage

BASE = $(realpath ./)
SRC_DIR = $(BASE)/src
TESTS_DIR = $(BASE)/tests
TOOLS_DIR = $(BASE)/tools
BUILD_DIR = $(BASE)/build
DOCS_DIR = $(BASE)/docs
COVERAGE_DIR = $(BASE)/coverage
BIN_DIR = $(BASE)/vendor/bin

COMMIT = $(shell git rev-parse --short HEAD)
MODULE_VERSION=$(shell perl -nle"print $$& if m{(?<=<version>)[^<]+}" src/app/code/community/Fyndiq/Fyndiq/etc/config.xml)

build: clean
	rsync -a --exclude='.*' $(SRC_DIR) $(BUILD_DIR)
	# replace COMMIT hash
	sed -i'' 's/XXXXXX/$(COMMIT)/g' $(BUILD_DIR)/src/app/code/community/Fyndiq/Fyndiq/Model/Config.php;
	# mkdir -p $(BUILD_DIR)/src/docs
	# cp $(DOCS_DIR)/* $(BUILD_DIR)/src/docs
	cp $(BASE)/LICENSE $(BUILD_DIR)/src/app/code/community/Fyndiq/Fyndiq/
	cd $(BUILD_DIR)/src; zip -r -X ../fyndiq-magento-module-v$(MODULE_VERSION)-$(COMMIT).zip .
	rm -r $(BUILD_DIR)/src

build-connect: clean
	rsync -a --exclude='.*' $(SRC_DIR) $(BUILD_DIR)
	# replace COMMIT hash
	sed -i'' 's/XXXXXX/$(COMMIT)/g' $(BUILD_DIR)/src/app/code/community/Fyndiq/Fyndiq/Model/Config.php;
	cp $(BASE)/LICENSE $(BUILD_DIR)/src/app/code/community/Fyndiq/Fyndiq/
	php $(TOOLS_DIR)/package.php $(BUILD_DIR)/src $(MODULE_VERSION) ${BASE}/CHANGELOG > $(BUILD_DIR)/src/package.xml
	cd $(BUILD_DIR)/src; tar -cvzf ../fyndiq-magento-module-v$(MODULE_VERSION)-$(COMMIT)-connect.tgz *
	rm -r $(BUILD_DIR)/src

clean:
	rm -r $(BUILD_DIR)/*

dev:
	#cp -svr --remove-destination $(SRC_DIR)/* $(MAGENTO_ROOT)/
	ln -s $(SRC_DIR)/app/code/community/Fyndiq $(MAGENTO_ROOT)/app/code/community/Fyndiq
	ln -s $(SRC_DIR)/app/etc/modules/Fyndiq_Fyndiq.xml $(MAGENTO_ROOT)/app/etc/modules/Fyndiq_Fyndiq.xml
	ln -sf $(SRC_DIR)/app/design/adminhtml/default/default/layout/Fyndiq_Fyndiq.xml $(MAGENTO_ROOT)/app/design/adminhtml/default/default/layout/Fyndiq_Fyndiq.xml
	ln -s $(SRC_DIR)/skin/adminhtml/base $(MAGENTO_ROOT)/skin/adminhtml/base

dev-clean:
	rm $(MAGENTO_ROOT)/app/code/community/Fyndiq
	rm $(MAGENTO_ROOT)/app/etc/modules/Fyndiq_Fyndiq.xml
	rm $(MAGENTO_ROOT)/app/design/adminhtml/default/default/layout/Fyndiq_Fyndiq.xml
	rm $(MAGENTO_ROOT)/skin/adminhtml/base

css:
	cd $(SRC_DIR)/fyndiq/frontend/css; scss -C --sourcemap=none main.scss:main.css

test:
	$(BIN_DIR)/phpunit --exclude-group ignore

scss-lint:
	scss-lint $(SRC_DIR)/fyndiq/frontend/css/*.scss

php-lint:
	find $(SRC_DIR) -name "*.php" -print0 | xargs -0 -n1 -P8 php -l
	find $(TESTS_DIR) -name "*.php" -print0 | xargs -0 -n1 -P8 php -l
	find $(TOOLS_DIR) -name "*.php" -print0 | xargs -0 -n1 -P8 php -l

phpmd:
	$(BIN_DIR)/phpmd $(SRC_DIR) --exclude /api,/shared text cleancode,codesize,controversial,design,naming,unusedcode

coverage: clear_coverage
	$(BIN_DIR)/phpunit --exclude-group ignore --coverage-html $(COVERAGE_DIR)

clear_coverage:
	rm -rf $(COVERAGE_DIR)

sniff:
	$(BIN_DIR)/phpcs --standard=PSR2 --extensions=php --ignore=shared,templates,api --colors $(SRC_DIR)
	$(BIN_DIR)/phpcs --standard=PSR2 --extensions=php $(TESTS_DIR)
	$(BIN_DIR)/phpcs --standard=PSR2 --extensions=php $(TOOLS_DIR)

sniff-fix:
	$(BIN_DIR)/phpcbf --standard=PSR2 --extensions=php --ignore=shared,templates,api $(SRC_DIR)
	$(BIN_DIR)/phpcbf --standard=PSR2 --extensions=php $(TESTS_DIR)
	$(BIN_DIR)/phpcbf --standard=PSR2 --extensions=php $(TOOLS_DIR)

sniff-fixer:
	php -n $(BIN_DIR)/php-cs-fixer fix --config-file=$(BASE)/.php_cs.php
	php -n $(BIN_DIR)/php-cs-fixer fix $(TESTS_DIR) --level=psr2
	php -n $(BIN_DIR)/php-cs-fixer fix $(TOOLS_DIR) --level=psr2

phpcpd:
	$(BIN_DIR)/phpcpd --exclude=app/code/community/Fyndiq/Fyndiq/lib $(SRC_DIR)

compatinfo:
	$(BIN_DIR)/phpcompatinfo analyser:run $(SRC_DIR)

translations_push:
	tx push -s

translations_pull:
	tx pull -a

update_tree:
	curl -s https://api.fyndiq.com/v2/categories/ -u ${FUSER}:${TOKEN} | php ${TOOLS_DIR}/tree2csv.php > ${SRC_DIR}/app/code/community/Fyndiq/Fyndiq/data/fyndiqmodule_setup/tree.csv
	wc -l ${SRC_DIR}/app/code/community/Fyndiq/Fyndiq/data/fyndiqmodule_setup/tree.csv
