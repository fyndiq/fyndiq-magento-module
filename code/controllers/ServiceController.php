<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 28/08/14
 * Time: 17:12
 */
require_once(dirname(dirname(__FILE__)) . '/Model/Order.php');
require_once(dirname(dirname(__FILE__)) . '/Model/Category.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/init.php');

class Fyndiq_Fyndiq_ServiceController extends Mage_Adminhtml_Controller_Action
{

    protected function _construct()
    {
        FyndiqTranslation::init(Mage::app()->getLocale()->getLocaleCode());
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
            $this->$action($args);
        }
    }


    /**
     * Get the categories.
     *
     * @param array $args
     */
    public function get_categories($args)
    {
        $storeId = $this->getRequest()->getParam('store');
        $categories = FmCategory::getSubCategories(intval($args['category_id']), $storeId);
        $this->response($categories);
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
        $products = $productModel->getCollection()
            ->addStoreFilter($storeId)
            ->addAttributeToFilter(
                array(
                    array('attribute' => 'type_id', 'eq' => 'configurable'),
                    array('attribute' => 'type_id', 'eq' => 'simple'),
                )
            )
            ->addCategoryFilter($category)
            ->addAttributeToSelect('*');

        $products->load();
        $products = $products->getItems();
        $fyndiqPercentage = FmConfig::get('price_percentage', $this->getRequest()->getParam('store'));

        // get all the products
        $id = -1;
        foreach ($products as $prod) {
            if ($prod->getTypeId() == 'simple') {
                $children = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($prod->getId());
                if (isset($children) && count($children) > 0) {
                    continue;
                }
            }

            $id++;
            if ($id < (($page-1) * FyndiqUtils::PAGINATION_ITEMS_PER_PAGE) || $id > ($page * FyndiqUtils::PAGINATION_ITEMS_PER_PAGE)){
                continue;
            }

            $fyndiqData = Mage::getModel('fyndiq/product')->getProductExportData($prod->getId());
            $fyndiq = !empty($fyndiqData);
            $fyndiqState = null;

            if ($prod->getTypeId() == 'simple') {
                //Get parent
                $parentIds = $groupedModel->getParentIdsByChild($prod->getId());
                if (!$parentIds) //Couldn't get parent, try configurable model instead
                {
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
                    case 'FOR_SALE' :
                        $fyndiqStatus = 'on';
                        break;
                    default:
                        $fyndiqStatus = 'pending';
                };
            }
            
            $prodData = array(
                'id' => $prod->getId(),
                'url' => $prod->getUrl(),
                'name' => $prod->getName(),
                'quantity' => intval($this->getProductQty($prod)),
                'price' => number_format((float)$prod->getPrice(), 2, '.', ''),
                'fyndiq_percentage' => $fyndiqPercentage,
                'fyndiq_exported' => $fyndiq,
                'fyndiq_state' => $fyndiqState,
                'description' => $prod->getDescription(),
                'reference' => $prod->getSKU(),
                'properties' => implode(', ', $tags),
                'isActive' => $prod->getIsActive(),
                'fyndiq_status' => $fyndiqStatus,
                'fyndiq_check_on' => ($fyndiq && $fyndiqState == 'FOR_SALE'),
                'currency' => $currency,
                'fyndiq_check_pending' => ($fyndiq && $fyndiqState === null)
            );

            //trying to get image, if not image will be false
            try {
                $prodData['image'] = $prod->getImageUrl();
            } catch (Exception $e) {
                $prodData['image'] = false;
            }

            // If added to fyndiq export table, get the settings from that table
            if ($fyndiq) {
                $prodData['fyndiq_percentage'] = $fyndiqData['exported_price_percentage'];
                $prodData['$fyndiqState'] = $fyndiqData['state'];
            }

            //Count expected price to Fyndiq
            $prodData['expected_price'] = number_format(
                FyndiqUtils::getFyndiqPrice($prodData['price'], $prodData['fyndiq_percentage']),
                2,
                '.',
                ''
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
        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addStoreFilter($storeId)
            ->addAttributeToFilter(
                array(
                    array('attribute' => 'type_id', 'eq' => 'configurable'),
                    array('attribute' => 'type_id', 'eq' => 'simple'),
                )
            )
            ->addCategoryFilter($category)
            ->addAttributeToSelect('*');
        if ($collection == 'null') {
            return 0;
        }

        $collection = $collection->getItems();

        foreach ($collection as $key => $prod) {
            if ($prod->getTypeId() == 'simple') {
                $parent = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($prod->getId());
                if (isset($parent) && count($parent) > 0) {
                    unset($collection[$key]);
                }
            }
        }

        return count($collection);
    }


    /**
     * Get the products.
     *
     * @param $args
     */
    public function get_products($args)
    {
        $page = (isset($args['page']) && is_numeric($args['page']) && $args['page'] != -1) ? intval($args['page']) : 1;
        $response = array(
            'products' => array(),
            'pagination' => ''
        );
        if (!empty($args['category'])) {
            $category = Mage::getModel('catalog/category')->load($args['category']);
            $storeId = $this->getRequest()->getParam('store');
            $total = $this->getTotalProducts($storeId, $category);
            $response['products'] = $this->getAllProducts($storeId, $category, $page);
            $response['pagination'] = FyndiqUtils::getPaginationHTML($total, $page,
                FyndiqUtils::PAGINATION_ITEMS_PER_PAGE, FyndiqUtils::PAGINATION_PAGE_FRAME);
        }
        $this->response($response);
    }


    public function update_product($args)
    {
        $productModel = Mage::getModel('fyndiq/product');
        $status = $productModel->updateProduct($args['product'], array(
            'exported_price_percentage' => $args['percentage']
        ));
        $this->response($status);
    }

    /**
     * Exporting the products from Magento
     *
     * @param $args
     */
    public function export_products($args)
    {
        // Getting all data
        $productModel = Mage::getModel('fyndiq/product');
        $result = array();
        foreach ($args['products'] as $v) {
            $product = $v['product'];
            $fyndiqPercentage = $product['fyndiq_percentage'];
            $fyndiqPercentage = $fyndiqPercentage > 100 ? 100 : $fyndiqPercentage;
            $fyndiqPercentage = $fyndiqPercentage < 0 ? 0 : $fyndiqPercentage;
            $data = array(
                'exported_price_percentage' => $fyndiqPercentage
            );

            if ($productModel->getProductExportData($product['id']) != false) {
                $result[] = $productModel->updateProduct($product['id'], $data);
                continue;
            }
            $data['product_id'] = $product['id'];
            $result[] = $productModel->addProduct($data);

        }
        return $this->response($result);
    }

    public function delete_exported_products($args)
    {
        foreach ($args['products'] as $v) {
            $product = $v['product'];
            $productModel = Mage::getModel('fyndiq/product')->getCollection()->addFieldToFilter(
                'product_id',
                $product['id']
            )->getFirstItem();
            $productModel->delete();
        }
        $this->response();
    }

    /**
     * Loading imported orders
     *
     * @param array $args
     */
    public function load_orders($args)
    {
        $total = 0;
        $collection = Mage::getModel('fyndiq/order')->getCollection();
        if ($collection != 'null') {
            $total = $collection->count();
        }
        $page = (isset($args['page']) && is_numeric($args['page']) && $args['page'] != -1) ? intval($args['page']) : 1;

        $object = new stdClass();
        $object->orders = Mage::getModel('fyndiq/order')->getImportedOrders($page,
            FyndiqUtils::PAGINATION_ITEMS_PER_PAGE);
        $object->pagination = FyndiqUtils::getPaginationHTML($total, $page,
            FyndiqUtils::PAGINATION_ITEMS_PER_PAGE, FyndiqUtils::PAGINATION_PAGE_FRAME);
        $this->response($object);
    }

    /**
     * Getting the orders to be saved in Magento.
     *
     * @param $args
     */
    public function import_orders(/*$args*/)
    {
        $observer = Mage::getModel('fyndiq/observer');
        $storeId = $this->getRequest()->getParam('store');
        try {
            $newTime = time();
            $observer->importOrdersForStore($storeId, $newTime);
            $time = date('G:i:s', $newTime);
            self::response($time);
        } catch (Exception $e) {
            self::responseError(
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
            $storeId = $this->getRequest()->getParam('store');
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

            $this->response(true);
        } catch (Exception $e) {
            $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    public function disconnect_account(/*$args*/)
    {
        $config = new Mage_Core_Model_Config();
        $config->saveConfig('fyndiq/fyndiq_group/apikey', '', 'default', '');
        $config->saveConfig('fyndiq/fyndiq_group/username', '', 'default', '');
        $this->response(true);
    }

    public function update_order_status($args)
    {
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
                $this->response($status);
                return;
            }
        }
        self::responseError(
            FyndiqTranslation::get('unhandled-error-title'),
            FyndiqTranslation::get('unhandled-error-message')
        );
    }

    public function update_product_status()
    {
        try {
            $storeId = $this->getRequest()->getParam('store');
            $ret = FmHelpers::callApi($storeId, 'GET', 'product_info/');
            $result = true;
            $productModel = Mage::getModel('fyndiq/product');
            foreach ($ret['data'] as $statusRow) {
                $result &= $productModel->updateProductState(intval($statusRow->product_id), array(
                    'state' => $statusRow->for_sale
                ));
            }
            $this->response($result);
        } catch (Exception $e) {
            $this->responseError(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }
}
