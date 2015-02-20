# fyndiq-magento-module
Fyndiq's official Magento module


### Requirements
Magento 1.8-



### INSTALL
You can just drag the app directory to your magento directory to make the files get added to right place.

We recommend modgit or magento composer installer though, which make it easy with just one command to install the module.
read more about modgit here: http://www.bubblecode.net/en/2012/02/06/install-magento-modules-with-modgit/

#### manual installation
For this you need to have a terminal and git installed.

1. Cd to your magento directory (`cd /path/to/your/magento/`)
2. run `git init`
3. run `git remote add origin git@github.com:fyndiq/fyndiq-magento-module.git`
4. run `git fetch`
5. run `git checkout -t origin/master`
6. run `git submodule update --init --recursive`
7. login to magento admin
8. go to `System > Configurations > Advanced: Developers > Set Symlink Allowed to True`
9. Now empty cache (System > cache mangement > flush all.)
10. Now go to Fyndiq Page in admin (`System > Fyndiq import/export`)
11. click on settings. (The settings page can get blank the first time, try to logout and login then)
12. type in api-key and username and all the other information you wanna setup.
13. Go back to fyndiq page.
14. It will now work!

### Good to know
 * If you have problem after installing the module, like SQL or other problems. Test to clear cache first in admin. Install might not start because of cache and this can cause problems.
 * Don't remove or change SKU on a product until you are sure you won't have any new orders for that product. This can cause problem when you import orders right now.
#### Products
Fyndiq is trusting the product structure in Magento. Fyndiq just show configurable/parent products in module but will add all associated products to that parent product to the feed. If you don't see any products in the module, then you don't have any configurable/parent products. Add a configurable product and it will be shown.