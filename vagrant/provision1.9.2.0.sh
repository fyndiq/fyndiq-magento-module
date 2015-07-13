#!/usr/bin/env bash

MAGE_VERSION="1.9.2.0"
DATA_VERSION="1.9.1.0"

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

apt-get remove -y puppet chef

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
    echo "Downloading Magento ${MAGE_VERSION} ..."
    wget --quiet http://mirror.gunah.eu/magento/magento-${MAGE_VERSION}.tar.gz
    echo "Downloading Magento Sample files ${DATA_VERSION} ..."
    wget --quiet http://mirror.gunah.eu/magento/sample-data/magento-sample-data-${DATA_VERSION}.tar.gz
    tar -zxvf magento-${MAGE_VERSION}.tar.gz
    tar -zxvf magento-sample-data-${DATA_VERSION}.tar.gz
    cp -R magento-sample-data-${DATA_VERSION}/* magento

    ## Setup virtual host
    ln -s /vagrant/assets/001-magento.conf /etc/apache2/sites-enabled/001-magento.conf
    a2enmod rewrite
    service apache2 restart

    ## Create database
    mysql -uroot -p123 -e 'create database magento'

    mysql -u root -p123 magento < magento/magento_sample_data_for_${DATA_VERSION}.sql
    mv magento /var/www/html/

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
    sudo /usr/bin/php -f install.php -- \
    --license_agreement_accepted yes \
    --locale en_US \
    --timezone "Europe/Stockholm" \
    --default_currency SEK \
    --db_host localhost \
    --db_name magento \
    --db_user root \
    --db_pass 123 \
    --url "http://magento.local/" \
    --use_rewrites yes \
    --use_secure no \
    --secure_base_url "http://magento.local/" \
    --use_secure_admin no \
    --skip_url_validation yes \
    --admin_lastname Owner \
    --admin_firstname Store \
    --admin_email "admin@example.com" \
    --admin_username admin \
    --admin_password password123123
    /usr/bin/php -f shell/indexer.php reindexall

    ## Add hosts to file
    echo "192.168.44.44  fyndiq.local" >> /etc/hosts
    echo "127.0.0.1  magento.local" >> /etc/hosts

    ## Enable template sym-links
    mysql -u root -p123 -e "UPDATE magento.core_config_data SET value = '1' WHERE path = 'dev/template/allow_symlink'"
fi

echo "Done. Happy hacking!"
