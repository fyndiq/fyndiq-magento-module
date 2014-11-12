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

    function addProduct($product_id, $export_qty, $export_price)
    {
        $data = array('product_id' => $product_id, 'exported_qty' => $export_qty, 'exported_price' => $export_price);
        $model = $this->setData($data);

        return $model->save()->getId();
    }

    function updateProduct($product_id, $export_qty, $export_price)
    {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $product_id)->getFirstItem();
        $data = array('exported_qty' => $export_qty, 'exported_price' => $export_price);
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
        foreach ($products as $product) {
            $product = $product->getData();
            $magorder = Mage::getModel('catalog/product')->load($product["product_id"]);
            $magarray = $magorder->getData();
            $return_array[] = $magarray;
        }

        $filehandler = new FmFileHandler("w+");
        foreach ($return_array as $product_array) {
            $filehandler->writeOverFile($product_array);
        }
    }

}