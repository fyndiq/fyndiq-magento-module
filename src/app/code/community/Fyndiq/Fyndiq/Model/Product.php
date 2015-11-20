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
    public function getProductExportData($productId)
    {
        $collection = $this->getCollection()->addFieldToFilter('product_id', $productId)->getFirstItem();
        if ($collection->getId()) {
            return $collection->getData();
        }

        return false;
    }

    public function getMagentoProducts($storeId, $group = false, $category = null, $page = 0)
    {
        $collection = Mage::getResourceModel('catalog/product_collection')
            ->joinTable(
                array('product_super_link' => 'catalog/product_super_link'),
                'product_id=entity_id',
                array('parent_id' => 'parent_id'),
                null,
                'left'
            )
            ->addAttributeToSelect('*')
            ->addStoreFilter($storeId);
        if (!is_null($category)) {
            $collection->addCategoryFilter($category);
        }

        $collection->getSelect()->where(
            '((`e`.`type_id` = "configurable") OR ((`e`.`type_id` = "simple") AND (product_super_link.parent_id is null)))'
        );

        $collection->addAttributeToFilter('status', array('eq' => Mage_Catalog_Model_Product_Status::STATUS_ENABLED));

        if ($group) {
            $collection->getSelect()->group('e.entity_id');
        }

        if ($page > 0) {
            $collection->getSelect()->limit(
                FyndiqUtils::PAGINATION_ITEMS_PER_PAGE,
                (FyndiqUtils::PAGINATION_ITEMS_PER_PAGE * ($page - 1))
            );
        }
        return $collection;
    }

    /**
     * Add new product
     *
     * @param array $insertData
     * @return mixed
     */
    public function addProduct($insertData)
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
    public function updateProduct($productId, $updateData)
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
    public function updateProductState($id, $updateData)
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
    public function deleteProduct($productId)
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
