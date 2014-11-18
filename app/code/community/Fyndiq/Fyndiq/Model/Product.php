<?php
require_once(dirname(dirname(__FILE__)) . '/includes/file/fileHandler.php');

class Fyndiq_Fyndiq_Model_Product extends Mage_Core_Model_Abstract
{

    public function _construct()
    {
        parent::_construct();
        $this->_init('fyndiq/product');
    }

    function productExist($product_id)
    {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $product_id)->getFirstItem();
        if ($collection->getId()) {
            return true;
        } else {
            return false;
        }
    }

    function getProductExportData($product_id) {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $product_id)->getFirstItem();
        if ($collection->getId()) {
            return $collection->getData();
        } else {
            return false;
        }
    }

    function addProduct($product_id, $export_qty, $exported_price_percentage)
    {
        $data = array('product_id' => $product_id, 'exported_qty' => $export_qty, 'exported_price_percentage' => $exported_price_percentage);
        $model = $this->setData($data);

        return $model->save()->getId();
    }

    function updateProduct($product_id, $export_qty, $exported_price_percentage)
    {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $product_id)->getFirstItem();
        $data = array('exported_qty' => $export_qty, 'exported_price_percentage' => $exported_price_percentage);
        $model = $this->load($collection->getId())->addData($data);
        try {
            $model->setId($collection->getId())->save();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    function deleteProduct($product_id)
    {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $product_id)->getFirstItem();
        try {
            $this->setId($collection->getId())->delete();

            return true;

        } catch (Exception $e) {
            return false;
        }
    }

    function saveFile()
    {
        $products = $this->getCollection()->setOrder('id', 'DESC');;
        $products = $products->getItems();
        $return_array = array();
        foreach ($products as $producted) {

            $product = $producted->getData();
            var_dump($product);
            $magorder = Mage::getModel('catalog/product')->load($product["product_id"]);

            $magarray = $magorder->getData();
            var_dump($magarray);
            $real_array = array();
            $real_array["product-id"] = $product["product_id"];
            $real_array["article-num-in-stock"] = $product["exported_qty"];
            $real_array["product-price"] = $magarray["price"]-($magarray["price"]*($product["exported_price_percentage"] / 100));
            $real_array["product-img-1"] = $producted->getImageUrl();
            $real_array["product-title"] = $magarray["name"];
            $return_array[] = $real_array;
        }
           $first_array = array_values($return_array)[0];
        $key_values = array_keys($first_array);
        array_unshift($return_array, $key_values);
        $filehandler = new FmFileHandler("w+");
        foreach ($return_array as $product_array) {
            $filehandler->appendToFile($product_array);
        }
    }

}