<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 28/08/14
 * Time: 17:12
 */
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
                $productModel->updateProduct($product["id"], $product['fyndiq_quantity'], $product['fyndiq_price']);
            }
            else {
                $productModel->addProduct($product["id"],$product['fyndiq_quantity'], $product['fyndiq_price']);
            }
        }
        Mage::getModel('fyndiq/product')->saveFile();

        self::response();

    }

} 