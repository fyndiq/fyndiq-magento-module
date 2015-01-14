<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 28/08/14
 * Time: 17:12
 */
require_once(dirname(dirname(__FILE__)) . '/Model/Order.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/messages.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');

class Fyndiq_Fyndiq_ServiceController extends Mage_Adminhtml_Controller_Action
{

    /**
     * Structure the response back to the client
     *
     * @param string $data
     */
    public static function response($data = '')
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
            echo $json;
        }
    }


    /**
     * create a error to be send back to client.
     *
     * @param $title
     * @param $message
     */
    public static function response_error($title, $message)
    {
        $response = array(
            'fm-service-status' => 'error',
            'title' => $title,
            'message' => $message,
        );
        $json = json_encode($response);
        echo $json;
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
        $category = Mage::getModel('catalog/category');
        $treeModel = $category->getTreeModel();
        $treeModel->load();

        $ids = $treeModel->getCollection()->getAllIds();

        $data = array();

        if (!empty($ids)) {
            foreach ($ids as $id) {
                $cat = Mage::getModel('catalog/category');
                $cat->load($id);
                $categoryData = array(
                    'id' => $cat->getId(),
                    'url' => $cat->getUrl(),
                    'name' => $cat->getName(),
                    'image' => $cat->getImageUrl(),
                    'isActive' => $cat->getIsActive()
                );
                array_push($data, $categoryData);
            }
        }

        $this->response($data);
    }

    /**
     * Get the products.
     *
     * @param $args
     */
    public function get_products($args)
    {
        $category = Mage::getModel('catalog/category')->load($args['category']);

        $products = Mage::getModel('catalog/product')
            ->getCollection()
            ->addCategoryFilter($category)
            ->addAttributeToSelect('*')
            ->load();

        $data = array();

        // get all the products
        foreach ($products as $prod) {
            // setting up price and quantity for fyndiq.
            $qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod->getId())->getQty();
            $fyndiq_exported_stock = Mage::getModel('fyndiq/product')->getProductExportData($prod->getId());
            if($fyndiq_exported_stock != false) {
                $fyndiq_exported_stock = $fyndiq_exported_stock["exported_qty"];
            }
            $fyndiq_stock = (int)round(
                ($qtyStock * (FmConfig::get('quantity_percentage') / 100)),
                0,
                PHP_ROUND_HALF_UP
            );
            $fyndiq_price = FmConfig::get('price_percentage');
            //trying to get image, if not image will be false
            try {
                $prodData = array(
                    'id' => $prod->getId(),
                    'url' => $prod->getUrl(),
                    'name' => $prod->getName(),
                    'image' => $prod->getImageUrl(),
                    'quantity' => $qtyStock,
                    'fyndiq_quantity' => $fyndiq_stock,
                    'price' => $prod->getPrice(),
                    'fyndiq_price' => $fyndiq_price,
                    'fyndiq_exported_stock' => $fyndiq_exported_stock,
                    'description' => $prod->getDescription(),
                    'reference' => $prod->getSKU(),
                    'isActive' => $prod->getIsActive()
                );
            } catch (Exception $e) {
                $prodData = array(
                    'id' => $prod->getId(),
                    'url' => $prod->getUrl(),
                    'name' => $prod->getName(),
                    'price' => $prod->getPrice(),
                    'fyndiq_price' => $fyndiq_price,
                    'fyndiq_exported_stock' => $fyndiq_exported_stock,
                    'description' => $prod->getDescription(),
                    'reference' => $prod->getSKU(),
                    'quantity' => $qtyStock,
                    'fyndiq_quantity' => $fyndiq_stock,
                    'image' => false,
                    'isActive' => $prod->getIsActive()
                );
            }
            array_push($data, $prodData);
        }

        $this->response($data);
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
            $prod = Mage::getModel('catalog/product')->load($product->getData()["product_id"]);

            $qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod->getId())->getQty();
            $fyndiq_exported_data = Mage::getModel('fyndiq/product')->getProductExportData($prod->getId());
            if($fyndiq_exported_data != false) {
                $fyndiq_exported_stock = $fyndiq_exported_data["exported_qty"];
                $fyndiq_exported_precentage = $fyndiq_exported_data["exported_price_percentage"];
            }
            else {
                $fyndiq_exported_stock = false;
                $fyndiq_exported_precentage = false;
            }
            $fyndiq_stock = $fyndiq_exported_stock;
            $fyndiq_exported_price = (int)round(
                $prod->getPrice()-($prod->getPrice() * ($fyndiq_exported_precentage / 100)),
                0,
                PHP_ROUND_HALF_UP
            );
            $fyndiq_precentage = FmConfig::get('price_percentage');

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
    public static function export_products($args)
    {
        // Getting all data
        $productModel = Mage::getModel('fyndiq/product');
        foreach ($args['products'] as $v) {
            $product = $v['product'];

            if($productModel->productExist($product["id"])) {
                $productModel->updateProduct($product["id"], $product['fyndiq_quantity'], $product['fyndiq_precentage']);
            }
            else {
                $productModel->addProduct($product["id"],$product['fyndiq_quantity'], $product['fyndiq_precentage']);
            }
        }

        self::response();

    }

    public function delete_exported_products($args) {
        foreach ($args['products'] as $v) {
            $product = $v["product"];
            $productModel = Mage::getModel('fyndiq/product')->getCollection()->addFieldToFilter('product_id', $product["id"])->getFirstItem();
            $productModel->delete();
        }
        $this->response();
    }

    /**
     * Loading imported orders
     *
     * @param $args
     */
    public function load_orders($args) {
        $orders = Mage::getModel('fyndiq/order')->getImportedOrders();
        self::response($orders);
    }

    /**
     * Getting the orders to be saved in Magento.
     *
     * @param $args
     */
    public function import_orders($args) {
        try {
            $ret = FmHelpers::call_api('GET', 'order/');

            foreach ($ret["data"]->objects as $order) {
                if(!Mage::getModel('fyndiq/order')->orderExists($order->id)) {
                    Mage::getModel('fyndiq/order')->create($order);
                }
            }
            self::response($ret);
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
    public function get_delivery_notes($args) {
        try {
            //TESTDATA!!!!
            // TODO: fix this testdata to real data
            $orders = new stdClass();
            $orders->orders = array();
            $orders->orders[] = 17;
            $orders->orders[] = 1;
            $orders->orders[] = 12;

            $ret = FmHelpers::call_api('POST', 'function/delivery_note/', $orders, "fyndiq/files/deliverynote.pdf");
            self::response($ret);
        } catch (Exception $e) {
            self::response_error(
                FmMessages::get('unhandled-error-title'),
                FmMessages::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

} 