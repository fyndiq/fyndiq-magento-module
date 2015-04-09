<?php

class FmCategory
{

    private static function getCategoriesList($categoryId, $storeId, $getRootOnly)
    {
        $category = Mage::getModel('catalog/category');
        if ($getRootOnly) {
            return $category->getCollection()
                ->addAttributeToSelect('*')
                ->setStoreId($storeId)
                ->addAttributeToFilter('is_active', '1')
                ->addAttributeToFilter('level', 1)
                ->addAttributeToFilter('include_in_menu', '1')
                ->addAttributeToSort('position', 'asc')->getItems();
        }
        return $category->getCollection()
            ->addAttributeToSelect('*')
            ->setStoreId($storeId)
            ->addAttributeToFilter('is_active', '1')
            ->addAttributeToFilter('parent_id', array('eq' => $categoryId))
            ->addAttributeToSort('position', 'asc')->getItems();
    }

    public static function getSubCategories($categoryId, $storeId)
    {
        $data = array();
        $parentLvl = false;
        $getRootOnly = $categoryId === 0;
        $categories = self::getCategoriesList($categoryId, $storeId, $getRootOnly);
        foreach ($categories as $cat) {
            if ($getRootOnly) {
                if ($parentLvl == false) {
                    $parentLvl = $cat->getLevel();
                }
                if ($parentLvl != false && $cat->getLevel() != $parentLvl) {
                    continue;
                }
            }
            $data[] = array(
                'id' => $cat->getId(),
                'url' => $cat->getUrl(),
                'name' => $cat->getName(),
                'image' => $cat->getImageUrl(),
                'isActive' => $cat->getIsActive()
            );
        }
        return $data;
    }
}