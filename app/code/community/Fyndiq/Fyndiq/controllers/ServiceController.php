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

    private $_itemPerPage = 10;
    private $_pageFrame = 4;

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
            $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true);
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
        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true);
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
        $category = Mage::getModel('catalog/category');
        $treeModel = $category->getTreeModel();
        $treeModel->load();

        $ids = $treeModel->getCollection()->getAllIds();

        $data = array();

        if (!empty($ids)) {
            foreach ($ids as $id) {
                $cat = Mage::getModel('catalog/category');
                $cat->load($id);
                // If it is a category
                if ($cat->getEntityTypeId() == 3) {

                    $products = Mage::getResourceModel('catalog/product_collection')
                        ->addCategoryFilter($cat)
                        ->addAttributeToFilter('image', array('neq' => 'no_selection'));

                    $prodcount = $products->count();

                    /*if ($item->getProductCount() < 1) {
                        $collection->removeItemByKey($key);
                    }*/

                    if ($prodcount >= 1){
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
        $grouped_model = Mage::getModel('catalog/product_type_grouped');
        $configurable_model = Mage::getModel('catalog/product_type_configurable');
        $product_model = Mage::getModel('catalog/product');

        $category = Mage::getModel('catalog/category')->load($args['category']);
        $products = $product_model
            ->getCollection()
            ->addAttributeToFilter(
                array(
                    array('attribute'=> 'type_id','eq' => 'configurable'),
                    array('attribute'=> 'type_id','eq' => 'simple'),
                )
            )
            ->addCategoryFilter($category)
            ->addAttributeToSelect('*');

        if(isset($args["page"]) AND $args["page"] != -1) {
            $products->setCurPage($args["page"]);
            $products->setPageSize(10);
        }
        $products->load();

        $data = array();

        // get all the products
        foreach ($products as $prod) {
            // setting up price and quantity for fyndiq.
            $qtyStock = Mage::getModel('cataloginventory/stock_item')->loadByProduct($prod->getId())->getQty();
            $fyndiq = Mage::getModel('fyndiq/product')->productExist($prod->getId());
            $fyndiq_data = Mage::getModel('fyndiq/product')->getProductExportData($prod->getId());
            $fyndiq_price = FmConfig::get('price_percentage');

            if ($prod->getTypeId() == "simple") {
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
            $tags = "";
            if(isset($parent)) {
                $parentprod = $product_model->load($parent);
                $productAttributeOptions = $parentprod->getTypeInstance()->getConfigurableAttributes();

                $attrid = 1;
                foreach ($productAttributeOptions as $productAttribute) {
                    $attrValue = $parentprod->getResource()->getAttribute($productAttribute->getProductAttribute()->getAttributeCode())->getFrontend();
                    $attrCode = $productAttribute->getProductAttribute()->getAttributeCode();
                    $value = $attrValue->getValue($prod);

                    if($attrid == 1) {
                        $tags .= $attrCode.": ".$value[0];
                    }
                    else {
                        $tags .= ", ".$attrCode.": ".$value[0];
                    }
                    $attrid++;
                }
            }

            $prodData = array(
                'id' => $prod->getId(),
                'url' => $prod->getUrl(),
                'name' => $prod->getName(),
                'quantity' => intval($qtyStock),
                'fyndiq_quantity' => intval($qtyStock),
                'price' => number_format((float)$prod->getPrice(), 2, '.', ''),
                'fyndiq_price' => $fyndiq_price,
                'fyndiq_exported_stock' => intval($qtyStock),
                'fyndiq_exported' => $fyndiq,
                'description' => $prod->getDescription(),
                'reference' => $prod->getSKU(),
                'properties' => $tags,
                'isActive' => $prod->getIsActive()
            );

            //trying to get image, if not image will be false
            try {
                $prodData["image"] = $prod->getImageUrl();
            } catch (Exception $e) {
                $prodData["image"] = false;
            }

            if($fyndiq) {
                $prodData["fyndiq_price"] = $fyndiq_data["exported_price_percentage"];
            }

            //Count expected price to Fyndiq
            $prodData["expected_price"] = $prodData["price"]-(($prodData["fyndiq_price"]/100)*$prodData["price"]);

            array_push($data, $prodData);
        }
        $object = new stdClass();
        $object->products = $data;
        if(!isset($args["page"])) {
            $object->pagination = $this->getPagerProductsHtml($category, 1);
        } else {
            $object->pagination = $this->getPagerProductsHtml($category, $args["page"]);
        }
        $this->response($object);
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
    public function export_products($args)
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

        $this->response();

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


        if(isset($args["page"]) AND $args["page"] != -1) {
            $orders = Mage::getModel('fyndiq/order')->getImportedOrders($args["page"]);
        }
        else {
            $orders = Mage::getModel('fyndiq/order')->getImportedOrders(1);
        }

        $object = new stdClass();
        $object->orders = $orders;
        if(!isset($args["page"])) {
            $object->pagination = $this->getPagerOrdersHtml(1);
        } else {
            $object->pagination = $this->getPagerOrdersHtml($args["page"]);
        }
        $this->response($object);
    }

    /**
     * Getting the orders to be saved in Magento.
     *
     * @param $args
     */
    public function import_orders($args) {
        try {
            $ret = FmHelpers::call_api('GET', 'orders/');

            foreach ($ret["data"] as $order) {
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
            $orders = new stdClass();
            $orders->orders = array();
            foreach($args["orders"] as $order) {
                $object = new stdClass();
                $object->order = intval($order);
                $orders->orders[] = $object;
            }

            $ret = FmHelpers::call_api('POST', 'delivery_notes/', $orders, true);


            if($ret['status'] == 200) {

                if (file_exists("fyndiq/files/deliverynote.pdf")) {
                    unlink("fyndiq/files/deliverynote.pdf");
                }
                // Open the file to save the pdf
                $fp = fopen ("fyndiq/files/deliverynote.pdf", 'w+');
                // Saving data to file
                fputs($fp, $ret['data']);
                # closing the file
                fclose($fp);
                unset($ret['data']);
            }

            $this->response($ret);
        } catch (Exception $e) {
            self::response_error(
                FmMessages::get('unhandled-error-title'),
                FmMessages::get('unhandled-error-message') . ' (' . $e->getMessage() . ')'
            );
        }
    }

    public function disconnect_account($args) {
        $config = new Mage_Core_Model_Config();
        $config ->saveConfig('fyndiq/fyndiq_group/apikey', "", 'default', "");
        $config ->saveConfig('fyndiq/fyndiq_group/username', "", 'default', "");
        $this->response(true);
    }


    /**
     * Get pagination
     *
     * @param $category
     * @param $currentpage
     * @return bool|string
     */
    private function getPagerProductsHtml($category, $currentpage)
    {
        $html = false;
        $collection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addCategoryFilter($category)
            ->addAttributeToSelect('*');
        if($collection == 'null') return;
        if($collection->count() > 10)
        {
            $curPage = $currentpage;
            $pager = (int)($collection->count() / $this->_itemPerPage);
            $count = ($collection->count() % $this->_itemPerPage == 0) ? $pager : $pager + 1 ;
            $start = 1;
            $end = $this->_pageFrame;


            $html .= '<ol class="pageslist">';
            if(isset($curPage) && $curPage != 1){
                $start = $curPage - 1;
                $end = $start + $this->_pageFrame;
            }else{
                $end = $start + $this->_pageFrame;
            }
            if($end > $count){
                $start = $count - ($this->_pageFrame-1);
            }else{
                $count = $end-1;
            }

            if($curPage > $count-1) {
                $html .= '<li><a href="#" data-page="'.($curPage-1).'"><< Previous</a></li>';
            }

            for($i = $start; $i<=$count; $i++)
            {
                if($i >= 1){
                    if($curPage){
                        $html .= ($curPage == $i) ? '<li class="current">'. $i .'</li>' : '<li><a href="#" data-page="'.$i.'">'. $i .'</a></li>';
                    }else{
                        $html .= ($i == 1) ? '<li class="current">'. $i .'</li>' : '<li><a href="#" data-page="'.$i.'">'. $i .'</a></li>';
                    }
                }

            }

            if($curPage < $count) {
                $html .= '<li><a href="#" data-page="'.($curPage+1).'">Next >></a></li>';
            }

            $html .= '</ol>';
        }

        return $html;
    }

    /**
     * Get pagination for orders
     *
     * @param integer $currentpage
     * @return bool|string
     */
    private function getPagerOrdersHtml($currentpage)
    {
        $html = false;
        $collection = Mage::getModel('fyndiq/order')
            ->getCollection();
        if($collection == 'null') return;
        if($collection->count() > 10)
        {
            $curPage = $currentpage;
            $pager = (int)($collection->count() / $this->_itemPerPage);
            $count = ($collection->count() % $this->_itemPerPage == 0) ? $pager : $pager + 1 ;
            $start = 1;
            $end = $this->_pageFrame;

            $html .= '<ol class="pageslist">';
            if(isset($curPage) && $curPage != 1){
                $start = $curPage - 1;
                $end = $start + $this->_pageFrame;
            }else{
                $end = $start + $this->_pageFrame;
            }
            if($end > $count){
                $start = $count - ($this->_pageFrame-1);
            }else{
                $count = $end-1;
            }

            for($i = $start; $i<=$count; $i++)
            {
                if($i >= 1){
                    if($curPage){
                        $html .= ($curPage == $i) ? '<li class="current">'. $i .'</li>' : '<li><a href="#" data-page="'.$i.'">'. $i .'</a></li>';
                    }else{
                        $html .= ($i == 1) ? '<li class="current">'. $i .'</li>' : '<li><a href="#" data-page="'.$i.'">'. $i .'</a></li>';
                    }
                }

            }

            $html .= '</ol>';
        }

        return $html;
    }
}