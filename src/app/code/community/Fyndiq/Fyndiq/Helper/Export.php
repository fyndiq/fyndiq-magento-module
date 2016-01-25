<?php

class Fyndiq_Fyndiq_Helper_Export extends Mage_Core_Helper_Abstract
{

    public function isExportable($product)
    {
        $productTypeId = $product->getTypeId();
        $productConfigurableModel = Mage::getModel('catalog/product_type_configurable');
        $parentId = $productConfigurableModel->getParentIdsByChild($product->getId());
        return (
            (
                $productTypeId == Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE ||
                (
                    $productTypeId == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE && empty($parentId)
                )
            ) && $product->getData('has_options') == 0
        );
    }
}
