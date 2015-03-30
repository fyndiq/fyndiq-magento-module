<?php

class FmCategory {

    public static function get_subcategories($category_id, $storeId) {
        $category = Mage::getModel('catalog/category');
        $rootCategoryId = Mage::app()->getStore($storeId)->getRootCategoryId();
        $data = array();
        if($category_id === 0) {
            $categories = $category->getCollection()
                ->addAttributeToSelect('*')
                ->setStoreId($storeId)
                ->addAttributeToFilter('path', array('like' => "1/{$rootCategoryId}/%"))
                ->addAttributeToFilter('is_active', '1')
                ->addAttributeToFilter('include_in_menu', '1')
                ->addAttributeToSort('position', 'asc')->getItems();

            $parentlvl = false;
            foreach($categories as $cat) {
                if($parentlvl == false) {
                    $parentlvl = $cat->getLevel();
                }
                if($parentlvl != false && $cat->getLevel() != $parentlvl) {
                    continue;
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
        }
        else {
            $categories = $category->getCollection()
                ->addAttributeToSelect('*')
                ->setStoreId($storeId)
                ->addAttributeToFilter('path', array('like' => "1/{$rootCategoryId}/%"))
                ->addAttributeToFilter('is_active', '1')
                ->addAttributeToFilter('parent_id', array('eq' => $category_id))
                ->addAttributeToSort('position', 'asc')->getItems();

            foreach ($categories as $cat) {
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
        return $data;
    }
}