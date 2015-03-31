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
require_once(dirname(dirname(__FILE__)) . '/includes/messages.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/init.php');

class Fyndiq_Fyndiq_ServiceController extends Mage_Adminhtml_Controller_Action
{

    const itemPerPage = 10;
    const pageFrame = 4;

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
                FmMessages::get('unhandled-error-title'),
                FmMessages::get('unhandled-error-message')
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
     * @param Category $category
     * @param int $page
     * @return array
     */
    private function getAllProducts($category, $page) {
        $data = array();

        $grouped_model = Mage::getModel('catalog/product_type_grouped');
        $configurable_model = Mage::getModel('catalog/product_type_configurable');
        $product_model = Mage::getModel('catalog/product');

        $products = $product_model
            ->getCollection()
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
                'fyndiq_quantity' => intval($qtyStock),
                'price' => number_format((float)$prod->getPrice(), 2, '.', ''),
                'fyndiq_percentage' => $fyndiq_percentage,
                'fyndiq_exported_stock' => intval($qtyStock),
                'fyndiq_exported' => $fyndiq,
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

            if ($fyndiq) {
                $prodData['fyndiq_price'] = $fyndiq_data['exported_price_percentage'];
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
        $category = Mage::getModel('catalog/category')->load($args['category']);
        $total = $this->getTotalProducts($category);

        $object = new stdClass();
        $object->products = $this->getAllProducts($category, $page);
        $object->pagination = FyndiqUtils::getPaginationHTML($total, $page);
        $this->response($object);
    }


    public function update_product($args)
    {
        $productModel = Mage::getModel('fyndiq/product');
        $status = $productModel->updateProduct($args['product'], $args['percentage']);
        $this->response($status);
    }

    /**
     * Get exported products
     *
     * @param $args
     */
    public function get_exported_products($args)
    {
        $productModel = Mage::getModel('fyndiq/product');

        $return_array = array();
        foreach ($productModel->getCollection() as $product) {
            $prod = Mage::getModel('catalog/product')->load($product->getData()['product_id']);

            $qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod->getId())->getQty();
            $fyndiq_exported_data = Mage::getModel('fyndiq/product')->getProductExportData($prod->getId());
            if ($fyndiq_exported_data != false) {
                $fyndiq_exported_stock = $fyndiq_exported_data['exported_qty'];
                $fyndiq_exported_precentage = $fyndiq_exported_data['exported_price_percentage'];
            } else {
                $fyndiq_exported_stock = false;
                $fyndiq_exported_precentage = false;
            }
            $fyndiq_stock = $fyndiq_exported_stock;
            $fyndiq_exported_price = (int)round(
                $prod->getPrice() - ($prod->getPrice() * ($fyndiq_exported_precentage / 100)),
                0,
                PHP_ROUND_HALF_UP
            );
            $fyndiq_precentage = FmConfig::get('price_percentage', $this->getRequest()->getParam('store'));

            $prodData = array(
                'id' => $prod->getId(),
                'url' => $prod->getUrl(),
                'name' => $prod->getName(),
                'fyndiq_price' => $fyndiq_exported_price,
                'price' => $prod->getPrice(),
                'fyndiq_precentage' => $fyndiq_precentage,
                'description' => $prod->getDescription(),
                'reference' => $prod->getSKU(),
                'quantity' => $qtyStock,
                'fyndiq_quantity' => $fyndiq_stock,
                'image' => false,
                'isActive' => $prod->getIsActive()
            );
            $return_array[] = $prodData;
        }
        $this->response($return_array);
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
        foreach ($args['products'] as $v) {
            $product = $v['product'];
            $fyndiq_percentage = $product['fyndiq_percentage'];
            if ($fyndiq_percentage > 100) {
                $fyndiq_percentage = 100;
            }
            if ($productModel->productExist($product['id'])) {
                $productModel->updateProduct($product['id'], $fyndiq_percentage);
            } else {
                $productModel->addProduct($product['id'], $fyndiq_percentage);
            }
        }

        $this->response();

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
                FmMessages::get('unhandled-error-title'),
                FmMessages::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
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
            $orders = new stdClass();
            $orders->orders = array();
            if(!isset($args['orders'])) {
                throw new Exception('Pick at least one order');
            }
            foreach ($args['orders'] as $order) {
                $object = new stdClass();
                $object->order = intval($order);
                $orders->orders[] = $object;
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
                FmMessages::get('unhandled-error-title'),
                FmMessages::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
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
                return $this->response($status);
            }
        }
        self::response_error(
            FmMessages::get('unhandled-error-title'),
            FmMessages::get('unhandled-error-message')
        );
    }
}
