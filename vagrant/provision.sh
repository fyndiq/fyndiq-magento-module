#!/usr/bin/env bash

MAGE_VERSION="1.7.0.2"
DATA_VERSION="1.6.1.0"

apt-get update
apt-get install -y build-essential vim-nox git
apt-get install -y unzip

## Setup locales
locale-gen en_GB.UTF-8
dpkg-reconfigure locales

## Install MySQL and PHP
echo "mysql-server-5.5 mysql-server/root_password password 123" | sudo debconf-set-selections
echo "mysql-server-5.5 mysql-server/root_password_again password 123" | sudo debconf-set-selections
apt-get install -y mysql-server
apt-get install -y apache2 php5 php5-mysql php5-gd php5-mcrypt php5-curl

php5enmod mcrypt

# Install scss
sudo gem install sass

###########################################################
# COMPOSER
###########################################################

if [ ! -e '/usr/local/bin/composer' ]; then
    curl -sS https://getcomposer.org/installer | php
    mv composer.phar /usr/local/bin/composer
fi

composer self-update

## PHP error_log
if [ ! -f '/etc/php5/apache2/conf.d/30-error_log.ini' ]; then
    echo 'error_log=/tmp/php_error.log' > /etc/php5/apache2/conf.d/30-error_log.ini
fi


if [[ ! -f "/var/www/html/magento/index.php" ]]; then
    cd /tmp
    echo "Downloading..."
    wget --quiet http://www.magentocommerce.com/downloads/assets/${MAGE_VERSION}/magento-${MAGE_VERSION}.tar.gz
    wget --quiet http://www.magentocommerce.com/downloads/assets/${DATA_VERSION}/magento-sample-data-${DATA_VERSION}.tar.gz
    tar -zxvf magento-${MAGE_VERSION}.tar.gz
    tar -zxvf magento-sample-data-${DATA_VERSION}.tar.gz
    cp -R magento-sample-data-${DATA_VERSION}/* magento

    ## Create database
    mysql -uroot -p123 -e 'create database magento'

    mysql -u root -p123 magento < magento/magento_sample_data_for_${DATA_VERSION}.sql
    mv magento /var/www/html/

    ## Fix magento bug
    sed "s#<pdo_mysql/>#<pdo_mysql>1</pdo_mysql>#" /var/www/html/magento/app/code/core/Mage/Install/etc/config.xml > /tmp/config.xml
    mv /tmp/config.xml /var/www/html/magento/app/code/core/Mage/Install/etc/config.xml

    ## Fix ownership and permissions
    chown -R vagrant:www-data /var/www/html/magento/
    chmod -R 775 /var/www/html/magento/

    ## Link module
    cd /opt/fyndiq-magento-module/ && make dev MAGENTO_ROOT=/var/www/html/magento

    # Clean up downloaded file and extracted dir
    rm -rf /tmp/magento*
fi

# Run installer
if [ ! -f "/var/www/html/magento/app/etc/local.xml" ]; then
    cd /var/www/html/magento
    sudo /usr/bin/php -f install.php -- --license_agreement_accepted yes \
    --locale en_US --timezone "Europe/Stockholm" --default_currency SEK \
    --db_host localhost --db_name magento --db_user root --db_pass 123 \
    --url "http://magento.local/" --use_rewrites yes \
    --use_secure no --secure_base_url "http://magento.local/" --use_secure_admin no \
    --skip_url_validation yes \
    --admin_lastname Owner --admin_firstname Store --admin_email "admin@example.com" \
    --admin_username admin --admin_password password123123
    /usr/bin/php -f shell/indexer.php reindexall
fi
