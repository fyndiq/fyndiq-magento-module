<?php

class FmCategory {

    public static function get_subcategories($category_id, $storeId) {
        $category = Mage::getModel('catalog/category');
        $data = array();
        $getRootOnly = $category_id === 0;
        if($getRootOnly) {
            $categories = $category->getCollection()
                ->addAttributeToSelect('*')
                ->setStoreId($storeId)
                ->addAttributeToFilter('is_active', '1')
                ->addAttributeToFilter('include_in_menu', '1')
                ->addAttributeToSort('position', 'asc')->getItems();
        } else {
            $categories = $category->getCollection()
                ->addAttributeToSelect('*')
                ->setStoreId($storeId)
                ->addAttributeToFilter('is_active', '1')
                ->addAttributeToFilter('parent_id', array('eq' => $category_id))
                ->addAttributeToSort('position', 'asc')->getItems();
        }
        $parentLvl = false;
        foreach($categories as $cat) {
            if ($getRootOnly) {
                if($parentLvl == false) {
                    $parentLvl = $cat->getLevel();
                }
                if($parentLvl != false && $cat->getLevel() != $parentLvl) {
                    continue;
                }
            }
            $categoryData = array(
                'id' => $cat->getId(),
                'url' => $cat->getUrl(),
                'name' => $cat->getName(),
                'image' => $cat->getImageUrl(),
                'isActive' => $cat->getIsActive()
            );
            array_push($data, $categoryData);
        }
        return $data;
    }
}