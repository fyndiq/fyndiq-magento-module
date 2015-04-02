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

    const itemPerPage = 10;
    const pageFrame = 4;

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
            self::response_error(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message')
            );
        } else {
            $this->getResponse()->clearHeaders()->setHeader('Content-type', 'application/json', true);
            $this->getResponse()->setBody($json);
        }
    }


    /**
     * create a error to be send back to client.
     *
     * @param $title
     * @param $message
     */
    public function response_error($title, $message)
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
        $action = false;
        $args = array();
        if (array_key_exists('action', $this->getRequest()->getPost())) {
            $action = $this->getRequest()->getPost('action');
        }
        if (array_key_exists('args', $this->getRequest()->getPost())) {
            $args = $this->getRequest()->getPost('args');
        }

        # call static function on self with name of the value provided in $action
        if (method_exists('Fyndiq_Fyndiq_ServiceController', $action)) {
            $this->$action($args);
        }
    }


    /**
     * Get the categories.
     *
     * @param $args
     */
    public function get_categories($args)
    {
        $storeId = $this->getRequest()->getParam('store');
        $categories = FmCategory::get_subcategories(intval($args['category_id']), $storeId);
        $this->response($categories);
    }

    /**
     * Get products in category for page
     *
     * @param int $storeId
     * @param Category $category
     * @param int $page
     * @return array
     */
    private function getAllProducts($storeId, $category, $page) {
        $data = array();

        $grouped_model = Mage::getModel('catalog/product_type_grouped');
        $configurable_model = Mage::getModel('catalog/product_type_configurable');
        $product_model = Mage::getModel('catalog/product');

        $products = $product_model
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

        $products->setCurPage($page);
        $products->setPageSize(10);
        $products->load();
        $products = $products->getItems();

        // get all the products
        foreach ($products as $prod) {
            if ($prod->getTypeId() == 'simple') {
                $children = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($prod->getId());
                if (isset($children) && count($children) > 0) {
                    continue;
                }
            }
            // setting up price and quantity for fyndiq.
            $qtyStock = 0;
            if ($prod->getTypeId() != 'simple') {
                foreach ($prod->getTypeInstance(true)->getUsedProducts(null, $prod) as $simple) {
                    $stock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($simple)->getQty();
                    $qtyStock += $stock;
                }
                if (!isset($qtyStock)) {
                    $qtyStock = 0;
                }
            } else {
                $qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod)->getQty();
            }
            $fyndiq = Mage::getModel('fyndiq/product')->productExist($prod->getId());
            $fyndiq_data = Mage::getModel('fyndiq/product')->getProductExportData($prod->getId());
            $fyndiq_percentage = FmConfig::get('price_percentage', $this->getRequest()->getParam('store'));
            $fyndiq_state = null;

            if ($prod->getTypeId() == 'simple') {
                //Get parent
                $parentIds = $grouped_model->getParentIdsByChild($prod->getId());
                if (!$parentIds) //Couldn't get parent, try configurable model instead
                {
                    $parentIds = $configurable_model->getParentIdsByChild($prod->getId());
                }
                // set parent id if exist
                if (isset($parentIds[0])) {
                    $parent = $parentIds[0];
                }
            }
            $tags = array();
            if (isset($parent)) {
                $parentProd = $product_model->load($parent);
                if ($parentProd) {
                    $productAttributeOptions = array();
                    $parentType = $parentProd->getTypeInstance();
                    if (method_exists($parentType, 'getConfigurableAttributes')) {
                        $productAttributeOptions = $parentType->getConfigurableAttributes();
                    }

                    foreach ($productAttributeOptions as $productAttribute) {
                        $attrValue = $parentProd->getResource()->getAttribute(
                            $productAttribute->getProductAttribute()->getAttributeCode()
                        )->getFrontend();
                        $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
                        $value = $attrValue->getValue($prod);

                        $tags[] = $attrCode . ': ' . $value[0];
                    }
                }
            }

            $prodData = array(
                'id' => $prod->getId(),
                'url' => $prod->getUrl(),
                'name' => $prod->getName(),
                'quantity' => intval($qtyStock),
                'price' => number_format((float)$prod->getPrice(), 2, '.', ''),
                'fyndiq_percentage' => $fyndiq_percentage,
                'fyndiq_exported' => $fyndiq,
                'fyndiq_state' => $fyndiq_state,
                'description' => $prod->getDescription(),
                'reference' => $prod->getSKU(),
                'properties' => implode(', ', $tags),
                'isActive' => $prod->getIsActive()
            );

            //trying to get image, if not image will be false
            try {
                $prodData['image'] = $prod->getImageUrl();
            } catch (Exception $e) {
                $prodData['image'] = false;
            }

            // If added to fyndiq export table, get the settings from that table
            if ($fyndiq) {
                $prodData['fyndiq_percentage'] = $fyndiq_data['exported_price_percentage'];
                if(isset($fyndiq_data['state'])) {
                    $prodData['fyndiq_state'] = $fyndiq_data['state'];
                }
            }

            //Count expected price to Fyndiq
            $prodData['expected_price'] = number_format(
                FyndiqUtils::getFyndiqPrice($prodData['price'], $prodData['fyndiq_percentage']),
                2,
                '.',
                ''
            );

            array_push($data, $prodData);
        }
        return $data;
    }

    /**
     * Get total products in category
     *
     * @param Category $category
     * @return int
     */
    private function getTotalProducts($category) {
        $collection = Mage::getModel('catalog/product')
            ->getCollection()
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
                $children = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($prod->getId());
                if (isset($children) && count($children) > 0) {
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
        $page = 1;
        if (isset($args['page']) && is_numeric($args['page']) && $args['page'] != -1) {
            $page = intval($args['page']);
        }
        $response = array(
            'products' => array(),
            'pagination' => ''
        );
        if (!empty($args['category'])) {
            $category = Mage::getModel('catalog/category')->load($args['category']);
            $total = $this->getTotalProducts($category);
            $storeId = $this->getRequest()->getParam('store');

            $response['products'] = $this->getAllProducts($storeId, $category, $page);
            $response['pagination'] = FyndiqUtils::getPaginationHTML($total, $page);
        }
        $this->response($response);
    }


    public function update_product($args)
    {
        $productModel = Mage::getModel('fyndiq/product');
        $status = $productModel->updateProduct($args['product'], $args['percentage']);
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
        $result = false;
        foreach ($args['products'] as $v) {
            $product = $v['product'];
            $fyndiqPercentage = $product['fyndiq_percentage'];
            $fyndiqPercentage = $fyndiqPercentage > 100 ? 100 : $fyndiqPercentage;
            $fyndiqPercentage = $fyndiqPercentage < 0 ? 0 : $fyndiqPercentage;

            if ($productModel->productExist($product['id'])) {
                $result = $productModel->updateProduct($product['id'], $fyndiqPercentage);
            } else {
                $result = $productModel->addProduct($product['id'], $fyndiqPercentage);
            }
        }
        $this->response($result);
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
        $page = 1;
        if (isset($args['page']) && is_numeric($args['page']) && $args['page'] != -1) {
            $page = intval($args['page']);
        }
        $object = new stdClass();
        $object->orders = Mage::getModel('fyndiq/order')->getImportedOrders($page);
        $object->pagination = FyndiqUtils::getPaginationHTML($total, $page);
        $this->response($object);
    }

    /**
     * Getting the orders to be saved in Magento.
     *
     * @param $args
     */
    public function import_orders($args)
    {
        $observer = Mage::getModel('fyndiq/observer');
        $storeId = $this->getRequest()->getParam('store');
        try {
            $newTime = time();
            $observer->importOrdersForStore($storeId, $newTime);
            $time = date('G:i:s', $newTime);
            self::response($time);
        } catch (Exception $e) {
            self::response_error(
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
            if(!isset($args['orders'])) {
                throw new Exception('Pick at least one order');
            }
            foreach ($args['orders'] as $order) {
                $orders['orders'][] = array('order' => intval($order));
            }
            $storeId = $this->getRequest()->getParam('store');
            $ret = FmHelpers::call_api($storeId, 'POST', 'delivery_notes/', $orders, true);

            if ($ret['status'] == 200) {
                $fileName = 'delivery_notes-' . implode('-', $args['orders']) . '.pdf';

                header('Content-Type: application/pdf');
                header('Content-Disposition: attachment; filename="' . $fileName . '"');
                header('Content-Transfer-Encoding: binary');
                header('Content-Length: ' . strlen($ret['data']));
                header('Expires: 0');
                $fp = fopen('php://temp', 'wb+');
                // Saving data to file
                fputs($fp, $ret['data']);
                rewind($fp);
                fpassthru($fp);
                fclose($fp);
                die();
            }

            $this->response(true);
        } catch (Exception $e) {
            $this->response_error(
                FyndiqTranslation::get('unhandled-error-title'),
                FyndiqTranslation::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    public function disconnect_account($args)
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
            foreach($args['orders'] as $orderId) {
                if (is_numeric($orderId)) {
                    $success &= $orderModel->updateOrderStatuses($orderId, $newStatusId);
                }
            }
            if ($success) {
                $status = $orderModel->getStatusName($newStatusId);
                $this->response($status);
            }
        }
        self::response_error(
            FyndiqTranslation::get('unhandled-error-title'),
            FyndiqTranslation::get('unhandled-error-message')
        );
    }
}
