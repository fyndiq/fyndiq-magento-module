# fyndiq-magento-module
Fyndiq's official Magento module


### Requirements
Magento 1.8-



### INSTALL
You can just drag the app directory to your magento directory to make the files get added to right place.

### manual production installation
For this you need to have a terminal and git installed.

1. run `git clone git://git@github.com:fyndiq/fyndiq-magento-module.git`
2. Cd to your module directory (`cd /path/to/your/module/repo/`)
3. run `git submodule update --init --recursive`
4. run in repo directory `./fyndman.sh deploy /path/to/your/magento/`
5. login to magento admin
6. Now empty cache (System > cache mangement > flush all.)
7. Now go to Fyndiq Page in admin (`System > Fyndiq import/export`)
8. click on settings. (The settings page can get blank the first time, try to logout and login then)
9. type in api-key and username and all the other information you wanna setup.
10. Make the fyndiq directory in magento root readable and writable. it is here the feed files will be.
11. Go back to fyndiq page.
12. It will now work!

#### Development installation
For this you need to have a terminal and git installed.

1. run `git clone git://git@github.com:fyndiq/fyndiq-magento-module.git`
2. Cd to your module directory (`cd /path/to/your/module/repo/`)
3. run `git submodule update --init --recursive`
4. run in repo directory `./fyndman.sh build /path/to/your/magento/`
5. login to magento admin
6. go to `System > Configurations > Advanced: Developers > Set Symlink Allowed to True`
7. Now empty cache (System > cache mangement > flush all.)
8. Now go to Fyndiq Page in admin (`System > Fyndiq import/export`)
9. click on settings. (The settings page can get blank the first time, try to logout and login then)
10. type in api-key and username and all the other information you wanna setup.
11. Make the fyndiq directory in magento root readable and writable. it is here the feed files will be.
12. Go back to fyndiq page.
13. It will now work!

### Good to know
 * If you have problem after installing the module, like SQL or other problems. Test to clear cache first in admin. Install might not start because of cache and this can cause problems.
 * Don't remove or change SKU on a product until you are sure you won't have any new orders for that product. This can cause problem when you import orders right now.

#### Products
Fyndiq is trusting the product structure in Magento. Fyndiq just show configurable/parent products in module but will add all associated products to that parent product to the feed. If you don't see any products in the module, then you don't have any configurable/parent products. Add a configurable product and it will be shown.