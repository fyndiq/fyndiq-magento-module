<?php

class Fyndiq_Fyndiq_Model_Category
{

    const NO_STORE = -1;

    public function __construct()
    {
        $this->configModel = Mage::getModel('fyndiq/config');

    }

    public function update($storeId = self::NO_STORE)
    {
        if ($storeId == self::NO_STORE) {
            $storeId = $this->getStoreId();
        }
        $categories = $this->downloadCategories($storeId);
        if (is_object($categories)) {
            $this->updateCateories($categories);
        }
    }

    protected function getStoreId()
    {
        // First check the global scope
        if ($this->storeIsSetUp(0)) {
            return 0;
        }
        // Then loop through all stores to find the first set-up
        foreach (Mage::app()->getWebsites() as $website) {
            foreach ($website->getGroups() as $group) {
                $stores = $group->getStores();
                foreach ($stores as $store) {
                    $storeId = $store->getId();
                    if ($this->storeIsSetUp($storeId)) {
                        return $storeId;
                    }
                }
            }
        }
        throw new Exception(Mage::helper('fyndiq_fyndiq')->__('Extension is not set up'));
    }

    protected function storeIsSetUp($storeId)
    {
        return (bool)$this->configModel->get('fyndiq/fyndiq_group/apikey', $storeId);
    }

    protected function downloadCategories($storeId)
    {
        try {
            $res = Mage::helper('fyndiq_fyndiq/connect')->callApi($this->configModel, $storeId, 'GET', 'categories/');
            return $res['data'];
        } catch (Exception $e) {
            throw new Exception(Mage::helper('fyndiq_fyndiq')->__('Error getting the category tree') . '('. $e->getMessage() .')');
        }
    }

    protected function updateCateories($categories)
    {
        $coreResource = Mage::getSingleton('core/resource');
        $tableName = $coreResource->getTableName('fyndiq/category');
        $connection = $coreResource->getConnection('core_write');
        try {
            $connection->beginTransaction();
            //clear the table first
            $connection->delete($tableName);
            foreach ($categories->categories as $category) {
                $connection->insert(
                    $tableName,
                    array(
                        'id' => $category->id,
                        'name_sv' => $category->path->sv,
                        'name_de' => $category->path->de,
                    )
                );
            }
            $connection->commit();
        } catch (Exception $e) {
            $connection->rollback();
            throw new Exception(Mage::helper('fyndiq_fyndiq')->__('Error updating the category tree') . '('. $e->getMessage() .')');
        }
    }

    public function getCategories()
    {
        $resource = Mage::getSingleton('core/resource');
        $tableName = $resource->getTableName('fyndiq/category');
        $readConnection = $resource->getConnection('core_read');
        $query = 'SELECT * FROM ' . $tableName;
        return $readConnection->fetchAll($query);
    }
}
