#!/usr/bin/env bash

# MAGE_TYPE="magento"
MAGE_TYPE="magento-with-samples"
MAGE_VERSION="2.0.0"

##We're not doing any installs interactively
export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y git
apt-get install -y curl
apt-get install -y build-essential vim-nox
apt-get install -y unzip

## Setup locales
export LANGUAGE=en_GB.UTF-8
export LANG=en_GB.UTF-8
export LC_ALL=en_GB.UTF-8
locale-gen en_GB.UTF-8
dpkg-reconfigure locales


## Install MySQL and PHP
echo "mysql-server-5.5 mysql-server/root_password password 123" | sudo debconf-set-selections
echo "mysql-server-5.5 mysql-server/root_password_again password 123" | sudo debconf-set-selections
apt-get install -y mysql-server-5.6
apt-get install -y apache2 php5 php5-mysql php5-gd php5-mcrypt php5-curl php5-intl php5-xsl

echo 'ServerName localhost' >> /etc/apache2/apache2.conf

php5enmod mcrypt

apt-get remove -y puppet chef

## Install SCSS
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
    if [[ ! -f "/opt/fyndiq-magento-module/assets/${MAGE_TYPE}-${MAGE_VERSION}.tar.gz" ]]; then
        wget --quiet http://pubfiles.nexcess.net/magento/ce-packages/${MAGE_TYPE}-${MAGE_VERSION}.tar.gz
    else
        echo "Using local copy"
        cp /opt/fyndiq-magento-module/assets/${MAGE_TYPE}-${MAGE_VERSION}.tar.gz .
    fi
    mkdir /tmp/magento
    tar -zxvf ${MAGE_TYPE}-${MAGE_VERSION}.tar.gz -C /tmp/magento

    ## Setup virtual host
    echo 'xdebug.remote_enable=on
    xdebug.remote_connect_back=on
    xdebug.idekey="PHPSTORM"
    xdebug.extended_info=1' >> /etc/php5/mods-available/xdebug.ini
    ln -s /vagrant/assets/001-magento.conf /etc/apache2/sites-enabled/001-magento.conf
    a2enmod rewrite
    service apache2 restart

    ## Create database
    mysql -uroot -p123 -e 'create database magento'

    # mysql -u root -p123 magento < magento/magento_sample_data_for_${DATA_VERSION}.sql
    mv magento /var/www/html/

    ## Fix ownership and permissions
    chown -R vagrant:www-data /var/www/html/magento/
    chmod -R 775 /var/www/html/magento/

    ## Link module
    #cd /opt/fyndiq-magento-module/ && make dev MAGENTO_ROOT=/var/www/html/magento

    # Clean up downloaded file and extracted dir
    rm -rf /tmp/magento*
fi

# Run installer
if [ ! -f "/var/www/html/magento/app/etc/local.xml" ]; then
    cd /var/www/html/magento
    sudo /var/www/html/magento/bin/magento setup:install \
        --backend-frontname="admin" \
        --db-name="magento" \
        --db-password="123" \
        --skip-db-validation \
        --base-url="http://magento.local/" \
        --language="en_US" \
        --timezone="Europe/Stockholm" \
        --currency="SEK" \
        --use-rewrites=1 \
        --admin-user="admin" \
        --admin-password="password123123" \
        --admin-email="admin@example.com" \
        --admin-firstname="Owner" \
        --admin-lastname="Store" \
        --use-sample-data \
        --magento-init-params="MAGE_MODE=developer"

    ## Fix ownership and permissions
    chown -R vagrant:www-data /var/www/html/magento/


    ## Add hosts to file
    echo "192.168.44.44  fyndiq.local" >> /etc/hosts
    echo "127.0.0.1  magento.local" >> /etc/hosts

    ## Enable template sym-links
    mysql -u root -p123 -e "UPDATE magento.core_config_data SET value = '1' WHERE path = 'dev/template/allow_symlink'"
fi

echo "Done. Happy hacking!"
