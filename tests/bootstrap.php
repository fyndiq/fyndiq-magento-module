<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(-1);
date_default_timezone_set('Europe/Stockholm');

define("MAGENTO_ROOT", dirname(dirname(__FILE__)) . '/src');

require_once(dirname(__FILE__) . '/dep/mage_adminhtml_controller.php');
require_once(dirname(__FILE__) . '/dep/mage_front_controller.php');
require_once(dirname(__FILE__) . '/dep/core_model_abstract.php');
require_once(dirname(__FILE__) . '/dep/Mage.php');
require_once(dirname(__FILE__) . '/dep/product_model.php');
require_once(dirname(__FILE__) . '/dep/catalog_stock.php');
require_once(dirname(__FILE__) . '/dep/Mage_Core_Helper_Abstract.php');
require_once(dirname(__FILE__) . '/dep/FyndiqPaginatedFetchDummy.php');


// require_once MAGENTO_ROOT . '/src/app/code/community/Fyndiq/Fyndiq/controllers/AdminController.php';
// require_once MAGENTO_ROOT . '/src/app/code/community/Fyndiq/Fyndiq/controllers/FileController.php';
// require_once MAGENTO_ROOT . '/src/app/code/community/Fyndiq/Fyndiq/controllers/NotificationController.php';
// require_once MAGENTO_ROOT . '/src/app/code/community/Fyndiq/Fyndiq/controllers/ServiceController.php';

// require_once MAGENTO_ROOT . '/src/app/code/community/Fyndiq/Fyndiq/Model/Product.php';
// require_once MAGENTO_ROOT . '/src/app/code/community/Fyndiq/Fyndiq/Model/Observer.php';

require_once MAGENTO_ROOT .  '/app/code/community/Fyndiq/Fyndiq/Helper/Region.php';
require_once MAGENTO_ROOT .  '/app/code/community/Fyndiq/Fyndiq/Helper/Export.php';
require_once MAGENTO_ROOT .  '/app/code/community/Fyndiq/Fyndiq/Model/OrderFetch.php';
