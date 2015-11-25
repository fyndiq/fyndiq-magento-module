<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 28/08/14
 * Time: 17:12
 */
require_once(dirname(dirname(__FILE__)) . '/Model/Order.php');
require_once(dirname(dirname(__FILE__)) . '/Model/OrderFetch.php');
require_once(dirname(dirname(__FILE__)) . '/Model/Category.php');
require_once(dirname(dirname(__FILE__)) . '/Model/Product_info.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/init.php');

class Fyndiq_Fyndiq_ServiceController extends Mage_Adminhtml_Controller_Action
{
    const ALL_PRODUCTS_CATEGORY_ID = -1;


    public function preDispatch()
    {
        Mage::getSingleton('core/session', array('name'=>'adminhtml'));
        if (!Mage::getSingleton('admin/session')->isLoggedIn()) {
            $this->redirect($this->getUrl('adminhtml/index/login'));
            // Magento calls preDispatch twice and breaks the json, because of that die();
            die();
        }
        parent::preDispatch();
    }

    protected function _construct()
    {
        parent::_construct();
        $this->observer = Mage::getModel('fyndiq/observer');
        FyndiqTranslation::init(Mage::app()->getLocale()->getLocaleCode());
    }

    /**
     * Structure the response back to the client
     *
     * @param string $data
     */
    public function redirect($url = '')
    {
        $response = array(
            'fm-service-status' => 'redirect',
            'data' => $url
        );
        $json = json_encode($response);
        $response = $this->getResponse();
        $response->clearHeaders()->setHeader('Content-type', 'application/json', true);
        $response->setBody($json);
        $response->sendResponse();
        return true;
    }

    /**
     * Structure the response back to the client
     *
     * @param string $data
     */
    public function response($data = '')
    {
        $response = array(
            'fm-service-status' => 'success',
            'data' => $data
        );
        $json = json_encode($response);
        if (json_last_error() != JSON_ERROR_NONE) {
            return self::responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message')
            );
        }
        $this->getResponse()->clearHeaders()->setHeader('Content-type', 'application/json', true);
        $this->getResponse()->setBody($json);
        return true;
    }


    /**
     * create a error to be send back to client.
     *
     * @param string $title
     * @param string $message
     */
    private function responseError($title, $message)
    {
        $response = array(
            'fm-service-status' => 'error',
            'title' => $title,
            'message' => $message,
        );
        $json = json_encode($response);
        $this->getResponse()->clearHeaders()->setHeader('Content-type', 'application/json', true);
        $this->getResponse()->setBody($json);
        return true;
    }

    /**
     * handle incoming ajax request
     */
    public function indexAction()
    {
        $action = $this->getRequest()->getPost('action');
        $args = $this->getRequest()->getPost('args');
        $args = is_array($args) ? $args : array();

        # call static function on self with name of the value provided in $action
        if (method_exists($this, $action)) {
            return $this->$action($args);
        }
        return $this->responseError('Method not found', sprintf('Method `%s` not found.', $action));
    }

    /**
     * Get the categories.
     *
     * @param array $args
     */
    public function get_categories($args)
    {
        try {
            $storeId = $this->observer->getStoreId();
            $categories = FmCategory::getSubCategories(intval($args['category_id']), $storeId);
            return $this->response($categories);
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    private function getProductQty($product)
    {
        $qtyStock = 0;
        if ($product->getTypeId() != 'simple') {
            foreach ($product->getTypeInstance(true)->getUsedProducts(null, $product) as $simple) {
                $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($simple)->getQty();
                $qtyStock += $stock;
            }

            return $qtyStock;
        }

        return Mage::getModel('cataloginventory/stock_item')->loadByProduct($product)->getQty();
    }

    /**
     * Get products in category for page
     *
     * @param int $storeId
     * @param Category $category
     * @param int $page
     * @return array
     */
    private function getAllProducts($storeId, $category, $page)
    {
        $data = array();
        $groupedModel = Mage::getModel('catalog/product_type_grouped');
        $configurableModel = Mage::getModel('catalog/product_type_configurable');
        $productModel = Mage::getModel('catalog/product');

        $currency = Mage::app()->getStore($storeId)->getCurrentCurrencyCode();

        $fyndiqProductModel = Mage::getModel('fyndiq/product');
        $products = $fyndiqProductModel->getMagentoProducts($storeId, true, $category, $page);

        $products->load();
        $products = $products->getItems();
        $fyndiqPercentage = FmConfig::get('price_percentage', $this->getRequest()->getParam('store'));
        $directoryCurrency = Mage::getModel('directory/currency');

        // get all the products
        foreach ($products as $prod) {
            $fyndiqData = Mage::getModel('fyndiq/product')->getProductExportData($prod->getId());
            $fyndiq = !empty($fyndiqData);
            $fyndiqState = $fyndiqData['state'];

            if ($prod->getTypeId() == 'simple') {
                //Get parent
                $parentIds = $groupedModel->getParentIdsByChild($prod->getId());
                if (!$parentIds) {
                    //Couldn't get parent, try configurable model instead
                    $parentIds = $configurableModel->getParentIdsByChild($prod->getId());
                }
                // set parent id if exist
                if (isset($parentIds[0])) {
                    $parent = $parentIds[0];
                }
            }
            $tags = array();
            if (isset($parent)) {
                $parentProd = $productModel->load($parent);
                if ($parentProd) {
                    $parentType = $parentProd->getTypeInstance();
                    if (method_exists($parentType, 'getConfigurableAttributes')) {
                        $productAttrOptions = $parentType->getConfigurableAttributes();
                        foreach ($productAttrOptions as $productAttribute) {
                            $attrValue = $parentProd->getResource()->getAttribute(
                                $productAttribute->getProductAttribute()->getAttributeCode()
                            )->getFrontend();
                            $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
                            $value = $attrValue->getValue($prod);

                            $tags[] = $attrCode . ': ' . $value[0];
                        }
                    }
                }
            }

            $fyndiqStatus = 'noton';

            if ($fyndiq) {
                switch ($fyndiqState) {
                    case 'FOR_SALE':
                        $fyndiqStatus = 'on';
                        break;
                    default:
                        $fyndiqStatus = 'pending';
                };
            }

            //title length checks
            $name = $prod->getName();
            $name_short = '';
            if (FyndiqFeedWriter::isColumnTooLong("product-title", $name)) {
                $name_short = FyndiqFeedWriter::sanitizeColumn("product-title", $name);
            }

            $magPrice = $this->observer->getProductPrice($prod);
            $producturl = Mage::helper('adminhtml')->getUrl('adminhtml/catalog_product/edit', array('id' => $prod->getId()));
            $taxRate = $this->getTaxRate($prod, $storeId);
            $fyndiqPercentage = $fyndiq ? $fyndiqData['exported_price_percentage'] : $fyndiqPercentage;

            //trying to get image, if not image will be false
            $image = false;
            try {
                $image = $prod->getImageUrl();
            } catch (Exception $e) {
            }

            $prodData = array(
                'id' => $prod->getId(),
                'url' => $prod->getUrl(),
                'name' => $name,
                'name_short' => $name_short,
                'quantity' => intval($this->getProductQty($prod)),
                'price' => $directoryCurrency->formatTxt($magPrice, array('display' => Zend_Currency::NO_SYMBOL)),
                'fyndiq_percentage' => $fyndiqPercentage,
                'fyndiq_exported' => $fyndiq,
                'fyndiq_state' => $fyndiqState,
                'producturl' => $producturl,
                'description' => $prod->getDescription(),
                'reference' => $prod->getSKU(),
                'properties' => implode(', ', $tags),
                'isActive' => $prod->getIsActive(),
                'fyndiq_status' => $fyndiqStatus,
                'fyndiq_check_on' => ($fyndiq && $fyndiqState == 'FOR_SALE'),
                'currency' => $currency,
                'fyndiq_check_pending' => ($fyndiq && $fyndiqState === null),
                'vat_percent_zero' => ($taxRate == 0),
                'image' => $image,
            );

            //Count expected price to Fyndiq
            $prodData['expected_price'] = $directoryCurrency->formatTxt(
                FyndiqUtils::getFyndiqPrice($magPrice, $prodData['fyndiq_percentage']),
                array('display' => Zend_Currency::NO_SYMBOL)
            );

            $data[] = $prodData;
        }

        return $data;
    }

    /**
     * Get total products in category
     *
     * @param Category $category
     * @return int
     */
    private function getTotalProducts($storeId, $category)
    {
        $fyndiqProductModel = Mage::getModel('fyndiq/product');
        $collection = $fyndiqProductModel->getMagentoProducts($storeId, false, $category);

        if ($collection == 'null') {
            return 0;
        }

        return $collection ? $collection->getSize() : 0;
    }


    /**
     * Get the products.
     *
     * @param $args
     */
    public function get_products($args)
    {
        try {
            $page = (isset($args['page']) && is_numeric($args['page']) && $args['page'] != -1) ? intval($args['page']) : 1;
            $response = array(
                'products' => array(),
                'pagination' => ''
            );
            if (!empty($args['category'])) {
                $category = null;
                if (intval($args['category']) != self::ALL_PRODUCTS_CATEGORY_ID) {
                    $category = Mage::getModel('catalog/category')->load($args['category']);
                }
                $storeId = $this->observer->getStoreId();
                $total = $this->getTotalProducts($storeId, $category);
                $response['products'] = $this->getAllProducts($storeId, $category, $page);
                $response['pagination'] = FyndiqUtils::getPaginationHTML(
                    $total,
                    $page,
                    FyndiqUtils::PAGINATION_ITEMS_PER_PAGE,
                    FyndiqUtils::PAGINATION_PAGE_FRAME
                );
            }
            return $this->response($response);
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }


    public function update_product($args)
    {
        try {
            $productModel = Mage::getModel('fyndiq/product');
            $status = $productModel->updateProduct(
                $args['product'],
                array(
                    'exported_price_percentage' => $args['percentage'],
                )
            );
            return $this->response($status);
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    /**
     * Exporting the products from Magento
     *
     * @param $args
     */
    public function export_products($args)
    {
        try {
            // Getting all data
            $productModel = Mage::getModel('fyndiq/product');
            $result = array();
            $storeId = $this->observer->getStoreId();
            foreach ($args['products'] as $v) {
                $product = $v['product'];
                $fyndiqPercentage = $product['fyndiq_percentage'];
                $fyndiqPercentage = $fyndiqPercentage > 100 ? 100 : $fyndiqPercentage;
                $fyndiqPercentage = $fyndiqPercentage < 0 ? 0 : $fyndiqPercentage;
                $data = array(
                    'exported_price_percentage' => $fyndiqPercentage,
                    'store_id' => $storeId,
                );

                if ($productModel->getProductExportData($product['id']) != false) {
                    $result[] = $productModel->updateProduct($product['id'], $data);
                    continue;
                }
                $data['product_id'] = $product['id'];
                $result[] = $productModel->addProduct($data);
            }
            return $this->response($result);
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    public function delete_exported_products($args)
    {
        try {
            foreach ($args['products'] as $v) {
                $product = $v['product'];
                $productModel = Mage::getModel('fyndiq/product')->getCollection()->addFieldToFilter(
                    'product_id',
                    $product['id']
                )->getFirstItem();
                $productModel->delete();
            }
            return $this->response();
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    /**
     * Loading imported orders
     *
     * @param array $args
     */
    public function load_orders($args)
    {
        try {
            $total = 0;
            $collection = Mage::getModel('fyndiq/order')->getCollection();
            if ($collection != 'null') {
                $total = $collection->count();
            }
            $page = (isset($args['page']) && is_numeric($args['page']) && $args['page'] != -1) ? intval($args['page']) : 1;

            $object = new stdClass();
            $object->orders = Mage::getModel('fyndiq/order')->getImportedOrders(
                $page,
                FyndiqUtils::PAGINATION_ITEMS_PER_PAGE
            );
            $object->pagination = FyndiqUtils::getPaginationHTML(
                $total,
                $page,
                FyndiqUtils::PAGINATION_ITEMS_PER_PAGE,
                FyndiqUtils::PAGINATION_PAGE_FRAME
            );
            return $this->response($object);
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    /**
     * Getting the orders to be saved in Magento.
     *
     * @param $args
     */
    public function import_orders()
    {
        try {
            $storeId = $this->observer->getStoreId();
            if (FmConfig::get('import_orders_disabled', $storeId) == FmHelpers::ORDERS_DISABLED) {
                throw new Exception('Orders are disabled');
            }
            $newTime = time();
            $this->observer->importOrdersForStore($storeId, $newTime);
            $time = date('G:i:s', $newTime);
            return $this->response($time);
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    /**
     * Getting a pdf of orders.
     *
     * @param $args
     */
    public function get_delivery_notes($args)
    {
        try {
            $orders = array(
                'orders' => array()
            );
            if (!isset($args['orders'])) {
                throw new Exception('Pick at least one order');
            }
            foreach ($args['orders'] as $order) {
                $orders['orders'][] = array('order' => intval($order));
            }
            $storeId = $this->observer->getStoreId();
            $ret = FmHelpers::callApi($storeId, 'POST', 'delivery_notes/', $orders, true);

            if ($ret['status'] == 200) {
                $fileName = 'delivery_notes-' . implode('-', $args['orders']) . '.pdf';

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
                header('Content-Transfer-Encoding: binary');
                header('Content-Length: ' . strlen($ret['data']));
                header('Expires: 0');
                $handler = fopen('php://temp', 'wb+');
                // Saving data to file
                fputs($handler, $ret['data']);
                rewind($handler);
                fpassthru($handler);
                fclose($handler);
                die();
            }

            return $this->response(true);
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    public function disconnect_account()
    {
        try {
            $config = new Mage_Core_Model_Config();
            $config->saveConfig('fyndiq/fyndiq_group/apikey', '', 'default', '');
            $config->saveConfig('fyndiq/fyndiq_group/username', '', 'default', '');
            return $this->response(true);
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    public function update_order_status($args)
    {
        try {
            if (isset($args['orders']) && is_array($args['orders'])) {
                $success = true;
                $newStatusId = FmConfig::get('done_state', $this->getRequest()->getParam('store'));
                $orderModel = Mage::getModel('fyndiq/order');
                foreach ($args['orders'] as $orderId) {
                    if (is_numeric($orderId)) {
                        $success &= $orderModel->updateOrderStatuses($orderId, $newStatusId);
                    }
                }
                if ($success) {
                    $status = $orderModel->getStatusName($newStatusId);
                    return $this->response($status);
                }
            }
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }

    }

    public function update_product_status()
    {
        try {
            $storeId = $this->observer->getStoreId();
            $pi = new FmProductInfo($storeId);
            $result = $pi->getAll();
            return $this->response($result);
        } catch (Exception $e) {
            return $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    private function getTaxRate($product, $storeId)
    {
        $taxCalculationModel = Mage::getModel('tax/calculation');
        $taxClassId = $product->getTaxClassId();
        $store = Mage::app()->getStore($storeId);
        $request = $taxCalculationModel->getRateRequest(null, null, null, $store);
        return $taxCalculationModel->getRate($request->setProductClassId($taxClassId));
    }


    protected function probe_file_permissions()
    {
        $messages = array();
        $storeId = $this->observer->getStoreId();
        $testMessage = time();
        try {
            $fileName = FmConfig::getFeedPath($storeId);
            $exists =  file_exists($fileName) ?
                FyndiqTranslation::get('exists') :
                FyndiqTranslation::get('does not exist');
            $messages[] = sprintf(FyndiqTranslation::get('Feed file name: `%s` (%s)'), $fileName, $exists);
            $tempFileName = FyndiqUtils::getTempFilename(dirname($fileName));
            if (dirname($tempFileName) !== dirname($fileName)) {
                throw new Exception(sprintf(
                    FyndiqTranslation::get('Cannot create file. Please make sure that the server can create new files in `%s`'),
                    dirname($fileName)
                ));
            }
            $messages[] = sprintf(FyndiqTranslation::get('Trying to create temporary file: `%s`'), $tempFileName);
            $file = fopen($tempFileName, 'w+');
            if (!$file) {
                throw new Exception(sprintf(FyndiqTranslation::get('Cannot create file: `%s`'), $tempFileName));
            }
            fwrite($file, $testMessage);
            fclose($file);
            $content = file_get_contents($tempFileName);
            if ($testMessage == file_get_contents($tempFileName)) {
                $messages[] = sprintf(FyndiqTranslation::get('File `%s` successfully read.'), $tempFileName);
            }
            FyndiqUtils::deleteFile($tempFileName);
            $messages[] = sprintf(FyndiqTranslation::get('Successfully deleted temp file `%s`'), $tempFileName);
            return $this->response(implode('<br />', $messages));
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            return $this->responseError(
                '',
                implode('<br />', $messages)
            );
        }
    }

    protected function probe_database()
    {
        $messages = array();
        try {
            $tables = array(
                'fyndiq/product',
                'fyndiq/order',
                'fyndiq/setting',
            );

            $missing = array();

            $coreResource = Mage::getSingleton('core/resource');
            foreach ($tables as $table) {
                $tableName = $coreResource->getTableName($table);
                $exists = (boolean) $coreResource->getConnection('core_write')
                    ->showTableStatus($tableName);
                if (!$exists) {
                    $missing[] = $tableName;
                    continue;
                }
                $messages[] = sprintf(FyndiqTranslation::get('Table `%s` is present.'), $tableName);
            }

            if ($missing) {
                throw new Exception(sprintf(
                    FyndiqTranslation::get('Required tables `%s` are missing.'),
                    implode(', ', $missing)
                ));
            }
            return $this->response(implode('<br />', $messages));
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            return $this->responseError(
                '',
                implode('<br />', $messages)
            );
        }
    }

    protected function probe_module_integrity()
    {
        $messages = array();
        $missing = array();
        $checkClasses = array(
            'FyndiqAPI',
            'FyndiqAPICall',
            'FyndiqCSVFeedWriter',
            'FyndiqFeedWriter',
            'FyndiqOutput',
            'FyndiqPaginatedFetch',
            'FyndiqTranslation',
            'FyndiqUtils',
        );
        try {
            foreach ($checkClasses as $className) {
                if (class_exists($className)) {
                    $messages[] = sprintf(FyndiqTranslation::get('Class `%s` is found.'), $className);
                    continue;
                }
                $messages[] = sprintf(FyndiqTranslation::get('Class `%s` is NOT found.'), $className);
            }
            if ($missing) {
                throw new Exception(sprintf(
                    FyndiqTranslation::get('Required classes `%s` are missing.', implode(',', $missing))
                ));
            }
            return $this->response(implode('<br />', $messages));
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            return $this->responseError(
                '',
                implode('<br />', $messages)
            );
        }
    }

    protected function probe_connection()
    {
        $messages = array();
        try {
            try {
                $storeId = $this->observer->getStoreId();
                FmHelpers::callApi($storeId, 'GET', 'settings/');
            } catch (Exception $e) {
                if ($e instanceof FyndiqAPIAuthorizationFailed) {
                    throw new Exception(FyndiqTranslation::get('Module is not authorized.'));
                }
            }
            $messages[] = FyndiqTranslation::get('Connection to Fyndiq successfully tested');
            return $this->response(implode('<br />', $messages));
        } catch (Exception $e) {
            $messages[] = $e->getMessage();
            return $this->responseError(
                '',
                implode('<br />', $messages)
            );
        }
    }

    protected function action_reinstall_module()
    {
        $moduleName = 'fyndiqmodule_setup';
        $sql = 'DELETE FROM core_resource WHERE code = "' . $moduleName . '";';
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        try {
            $connection->query($sql);
            return $this->response(FyndiqTranslation::get('Please flush the Magento Cache on System -> Cache management to reinstall the module'));
        } catch (Exception $e) {
            return$this->responseError(
                '',
                $e->getMessage()
            );
        }
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('system/fyndiq');
    }
}
