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

build-connect:
	cd vagrant && vagrant ssh -c 'mkdir -p /var/www/html/magento/var/connect && sudo chown vagrant:www-data /var/www/html/magento/var/connect && sudo chmod -R 775 /var/www/html/magento/var/connect'
	cd vagrant && vagrant ssh -c 'cp /opt/fyndiq-magento-module/deploys/Fyndiq_Fyndiq.xml /var/www/html/magento/var/connect/Fyndiq_Fyndiq.xml && cd /var/www/html/magento/ && echo "Setup correct version number and notes in Magento connect and click Save Data and Create Package. Then click Enter to continue here.." && read && mv /var/www/html/magento/var/connect/Fyndiq-*.tgz /opt/fyndiq-magento-module/build/Fyndiq-$(MODULE_VERSION).tgz && echo "You find the package in build directory now."'

clean:
	rm -r $(BUILD_DIR)/*

dev:
	#cp -svr --remove-destination $(SRC_DIR)/* $(MAGENTO_ROOT)/
	ln -s $(SRC_DIR)/app/code/community/Fyndiq $(MAGENTO_ROOT)/app/code/community/Fyndiq
	ln -s $(SRC_DIR)/app/etc/modules/Fyndiq_Fyndiq.xml $(MAGENTO_ROOT)/app/etc/modules/Fyndiq_Fyndiq.xml

css:
	cd $(SRC_DIR)/fyndiq/frontend/css; scss -C --sourcemap=none main.scss:main.css

test:
	$(BIN_DIR)/phpunit

scss-lint:
	scss-lint $(SRC_DIR)/fyndiq/frontend/css/*.scss

php-lint:
	find $(SRC_DIR) -name "*.php" -print0 | xargs -0 -n1 -P8 php -l

phpmd:
	$(BIN_DIR)/phpmd $(SRC_DIR) --exclude /api,/shared text cleancode,codesize,controversial,design,naming,unusedcode

coverage: clear_coverage
	$(BIN_DIR)/phpunit --coverage-html $(COVERAGE_DIR)

clear_coverage:
	rm -rf $(COVERAGE_DIR)

sniff:
	$(BIN_DIR)/phpcs --standard=PSR2 --extensions=php --ignore=shared,templates,api --colors $(SRC_DIR)

sniff-fix:
	$(BIN_DIR)/phpcbf --standard=PSR2 --extensions=php --ignore=shared,templates,api $(SRC_DIR)
	$(BIN_DIR)/phpcbf --standard=PSR2 --extensions=php $(TESTS_DIR)
	$(BIN_DIR)/phpcbf --standard=PSR2 --extensions=php $(TOOLS_DIR)

phpcpd:
	$(BIN_DIR)/phpcpd --exclude=app/code/community/Fyndiq/Fyndiq/lib $(SRC_DIR)

compatinfo:
	$(BIN_DIR)/phpcompatinfo analyser:run $(SRC_DIR)
