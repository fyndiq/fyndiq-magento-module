# fyndiq-magento-module
Fyndiq's official Magento module


### Requirements
* Magento 1.7-
* PHP >5.2

### Documentation

You can find the latest version of this module and up to date documentation on the following page: http://developers.fyndiq.com/fyndiq-built-integrations/#magento

### INSTALL
You can just drag the app directory to your magento directory to make the files get added to right place.

#### Manual production installation
For this you need to have a terminal and git installed.

##### Default packaging

1. Run `git clone https://github.com/fyndiq/fyndiq-magento-module.git`
2. Cd to your module directory (`cd /path/to/your/module/repo/`)
3. Run `git submodule update --init --recursive`
4. Run in repo directory `./fyndman.sh deploy /path/to/your/magento/`
5. Login to magento admin
6. Now empty cache (System > cache mangement > flush all.)
7. Now go to Fyndiq Page in admin (`System > Fyndiq import/export`)
8. Click on settings. (The settings page can get blank the first time, try to logout and login then)
9. Type in api-key and username and all the other information you wanna setup.
10. Make the fyndiq directory in magento root readable and writable. it is here the feed files will be.
11. Go back to fyndiq page.
12. It will now work!

##### Magento Connect Packaging
Use this to create a package for Magento Connect

1. run make build-connect
2. Go to admin and system > Magento Connect > Package Extensions when the command ask for it.
3. Go to Load Local Package
4. Double click on Fyndiq_Fyndiq to load default settings for our module
5. Set correct version and note under Release Info
6. Click on Save Data and Create Package
7. Click enter to continue in terminal.
8. you now have the package under build directory

#### Development installation
For this you need to have a terminal and git installed.

1. Run `git clone https://github.com/fyndiq/fyndiq-magento-module.git`
2. Cd to your module directory (`cd /path/to/your/module/repo/`)
3. Run `git submodule update --init --recursive`
4. Run in repo directory `./fyndman.sh build /path/to/your/magento/`
5. Login to magento admin
6. Go to `System > Configurations > Advanced: Developers > Set Symlink Allowed to True`
7. Now empty cache (System > cache mangement > flush all.)
8. Now go to Fyndiq Page in admin (`System > Fyndiq import/export`)
9. Click on settings. (The settings page can get blank the first time, try to logout and login then)
10. Type in api-key and username and all the other information you wanna setup.
11. Make the fyndiq directory in magento root readable and writable. it is here the feed files will be.
12. Go back to fyndiq page.
13. It will now work!

### Good to know
 * If you have problem after installing the module, like SQL or other problems. Test to clear cache first in admin. Install might not start because of cache and this can cause problems.
 * Don't remove or change SKU on a product until you are sure you won't have any new orders for that product. This can cause problem when you import orders right now.

#### Products
Fyndiq is trusting the product structure in Magento. Fyndiq just show configurable/parent products in module but will add all associated products to that parent product to the feed. If you don't see any products in the module, then you don't have any configurable/parent products. Add a configurable product and it will be shown.
