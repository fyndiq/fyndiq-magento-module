<?php

class Fyndiq_Fyndiq_Helper_Export extends Mage_Core_Helper_Abstract
{

    const IS_EXPORTABLE = 0;
    const ERR_HAS_OPTIONS = 1;
    const ERR_SIMPLE_HAS_PARENT = 2;
    const ERR_NOT_SIMPLE_OR_CONFIGURABLE = 3;

    public function isExportable($product)
    {
        return $this->isExportableStatus($product) == self::IS_EXPORTABLE;
    }

    public function hasCustomOptions($product)
    {
        $opts = Mage::getSingleton('catalog/product_option')->getProductOptionCollection($product);
        return $opts->getSize() > 0;
    }

    public function isExportableStatus($product)
    {
        $productTypeId = $product->getTypeId();
        $productConfigurableModel = Mage::getModel('catalog/product_type_configurable');
        $parentId = $productConfigurableModel->getParentIdsByChild($product->getId());
        if (!in_array(
            $productTypeId,
            array(
                    Mage_Catalog_Model_Product_Type::TYPE_SIMPLE,
                    Mage_Catalog_Model_Product_Type::TYPE_CONFIGURABLE
                )
        )
        ) {
            return self::ERR_NOT_SIMPLE_OR_CONFIGURABLE;
        }

        if ($productTypeId == Mage_Catalog_Model_Product_Type::TYPE_SIMPLE && !empty($parentId)) {
            return self::ERR_SIMPLE_HAS_PARENT;
        }
        if ($this->hasCustomOptions($product)) {
            return self::ERR_HAS_OPTIONS;
        }
        return self::IS_EXPORTABLE;
    }
}
