<?php

class Fyndiq_Fyndiq_Model_Category
{

    public function __construct()
    {
        $this->configModel = Mage::getModel('fyndiq/config');
    }

    public function updateTree()
    {
        // FIXME: Get real working storeId
        $storeId = 0;
        $categories = $this->getCategories($storeId);
        if (is_array($categories)) {
            $this->updateCateories($categories);
        }
    }

    protected function getTree($storeId)
    {
        $json = Mage::helper('fyndiq_fyndiq/connect')->callApi($this->configModel, $storeId, 'GET', 'categories/');
        return json_decode($json, true);
    }

    protected function updateCateories($categories)
    {
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $connection->beginTransaction();
        //clear the table first
        $connection->delete($tableName);
        foreach($categories['categories'] as $category) {
            $connection->insert(
                $tableName,
                array(
                    'id' => $category['id'],
                    'name_sv' => $category['path']['sv'],
                    'name_de' => $category['path']['de'],
                )
            );
        }
        $connection->commit();
    }
}
