<?php
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

    function addProduct($product_id, $exported_price_percentage)
    {
        $data = array('product_id' => $product_id, 'exported_price_percentage' => $exported_price_percentage);
        $model = $this->setData($data);

        return $model->save()->getId();
    }

    function updateProduct($product_id, $exported_price_percentage)
    {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $product_id)->getFirstItem();
        $data = array('exported_price_percentage' => $exported_price_percentage);
        $model = $this->load($collection->getId())->addData($data);
        try {
            $model->setId($collection->getId())->save();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    function updateProductState($product_id, $state)
    {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $product_id)->getFirstItem();
        $data = array('state' => $state);
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

}