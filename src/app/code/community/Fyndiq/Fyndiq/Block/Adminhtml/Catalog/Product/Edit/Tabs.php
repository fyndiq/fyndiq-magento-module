<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Catalog_Product_Edit_Tabs extends Mage_Adminhtml_Block_Catalog_Product_Edit_Tabs
{
    protected function _prepareLayout()
    {
        $result = parent::_prepareLayout();
        $product = $this->getProduct();
        if (!Mage::helper('fyndiq_fyndiq/export')->isExportable($product)) {
            foreach ($this->_tabs as $key => $tab) {
                // FIXME: There is no better way to identify the tab at this place
                if (strpos($tab->getLabel(), 'Fyndiq') !== false) {
                    $this->removeTab($key);
                }
            }
        }
        return $result;
    }
}
