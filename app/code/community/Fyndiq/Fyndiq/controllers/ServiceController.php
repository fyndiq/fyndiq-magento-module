<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 28/08/14
 * Time: 17:12
 */
require_once(dirname(dirname(__FILE__)) . '/includes/messages.php');
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
     * Exporting the products from Magento
     *
     * @param $args
     */
    public static function export_products($args)
    {
        foreach ($args['products'] as $v) {
            $product = $v['product'];

            //structing up the product data in a array
            $product_result = array(
                'title'=> $product['name'],
                'description'=> 'asdf8u4389j34g98j34g98',
                'images'=> array($product['image']),
                'oldprice'=> '9999',
                'brand' => 31,
                'categories' => array("10", "11"),
                'price'=> $product['price'],
                'moms_percent'=> '25'
            );

            // Setting combinations, also known as articles of a product.
            // when posting empty array, it's removed completely from the request, so check for key
            if (array_key_exists('combinations', $v)) {
                $combinations = $v['combinations'];

                foreach ($combinations as $combination) {
                    $product_result['articles'][] = array(
                        'num_in_stock' => '7',
                        'merchant_item_no' => '2',
                        'description' => 'asdfjeroijergo'
                    );
                }
            } else {
                $product_result['articles'][] = array(
                    'num_in_stock' => '99',
                    'merchant_item_no' => '99',
                    'description' => 'qwer99qwer98referf'
                );
            }

            // Sending the data to Fyndiq
            try {
                $result = FmHelpers::call_api('POST', 'products/', $product_result);
                if ($result['status'] != 201) {
                    // error occurred
                    $error = true;
                    self::response_error(
                        FmMessages::get('unhandled-error-title'),
                        FmMessages::get('unhandled-error-message')
                    );
                }
            } catch (FyndiqAPIBadRequest $e) {
                // Got error response from the api library
                $error = true;
                $message = '';
                foreach (FyndiqAPI::$error_messages as $error_message) {
                    $message .= $error_message;
                }
                self::response_error(
                    FmMessages::get('products-bad-params-title'),
                    $message
                );
            } catch (Exception $e) {
                // Other error occurred - send error message to frontend
                $error = true;
                self::response_error(
                    FmMessages::get('unhandled-error-title'),
                    $e->getMessage()
                );
            }

            if ($error) {
                break;
            }
        }

        if (!$error) {
            self::response();
        }
    }

} 