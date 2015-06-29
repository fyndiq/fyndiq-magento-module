<?php

class Fyndiq_Fyndiq_Model_Product extends Mage_Core_Model_Abstract
{

    public function _construct()
    {
        parent::_construct();
        $this->_init('fyndiq/product');
    }

    /**
     * Get product data
     * @param int $productId
     * @return bool|array
     */
    function getProductExportData($productId)
    {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $productId)->getFirstItem();
        if ($collection->getId()) {
            return $collection->getData();
        }

        return false;
    }

    /**
     * Add new product
     *
     * @param array $insertData
     * @return mixed
     */
    function addProduct($insertData)
    {
        $model = $this->setData($insertData);

        return $model->save()->getId();
    }

    /**
     * Update product
     * @param int $productId
     * @param array $updateData
     * @return bool
     */
    function updateProduct($productId, $updateData)
    {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $productId)->getFirstItem();
        $model = $this->load($collection->getId())->addData($updateData);
        try {
            $model->setId($collection->getId())->save();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Update product state
     * @param int $id
     * @param array $updateData
     * @return bool
     */
    function updateProductState($id, $updateData)
    {
        $model = $this->load($id)->addData($updateData);
        try {
            $model->setId($id)->save();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Delete Product
     *
     * @param int $productId
     * @return bool
     */
    function deleteProduct($productId)
    {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $productId)->getFirstItem();
        try {
            $this->setId($collection->getId())->delete();

            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
