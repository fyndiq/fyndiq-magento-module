<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 28/08/14
 * Time: 17:12
 */
require_once(dirname(dirname(__FILE__)) . '/includes/messages.php');
require_once(dirname(dirname(__FILE__)) . '/Model/Order.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
class Fyndiq_Fyndiq_ServiceController extends Mage_Adminhtml_Controller_Action {

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
            $this->response_error(
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
    public function response_error($title, $message)
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

        if (!empty($ids))
        {
            foreach ($ids as $id)
            {
                $cat = Mage::getModel('catalog/category');
                $cat->load($id);
                $categoryData = array('id'=>$cat->getId(),
                    'url'=>$cat->getUrl(),
                    'name'=>$cat->getName(),
                    'image'=>$cat->getImageUrl(),
                    'isActive'=>$cat->getIsActive()
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

            foreach ($products as $prod)
            {
                //var_dump($prod);
                $qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod->getId())->getQty();
                try {
                    $prodData = array('id'=>$prod->getId(),
                        'url'=>$prod->getUrl(),
                        'name'=>$prod->getName(),
                        'image'=> $prod->getImageUrl(),
                        'quantity' => $qtyStock,
                        'price' =>  $prod->getPrice(),
                        'reference' => $prod->getSKU(),
                        'isActive'=>$prod->getIsActive()
                    );
                }
                catch(Exception $e) {
                    $prodData = array('id'=>$prod->getId(),
                        'url'=>$prod->getUrl(),
                        'name'=>$prod->getName(),
                        'price' =>  $prod->getPrice(),
                        'reference' => $prod->getSKU(),
                        'quantity' => $qtyStock,
                        'image'=> false,
                        'isActive'=>$prod->getIsActive()
                    );
                }
                array_push($data, $prodData);
            }

        $this->response($data);
    }

    /**
     * Loading imported orders
     *
     * @param $args
     */
    public function load_orders($args) {

        $orders = Mage::getModel('fyndiq/order')->getImportedOrders();

        if(empty($orders)) {
            self::response_error(
                FmMessages::get('unhandled-error-title'),
                FmMessages::get('unhandled-error-message') . ' (Not working yet)'
            );
        }
        else {
            self::response($orders);
        }
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
                    $fyndiq_order_infos = FmHelpers::call_api('GET', 'order_row/?order__exact=' . $order->id);
                    Mage::getModel('fyndiq/order')->create($order,$fyndiq_order_infos);
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

} 