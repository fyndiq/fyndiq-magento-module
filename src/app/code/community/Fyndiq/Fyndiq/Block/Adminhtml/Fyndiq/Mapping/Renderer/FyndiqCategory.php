<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Renderer_FyndiqCategory extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{

    public function render(Varien_Object $row)
    {
        $fyndiqCategoryId = (int)$row->getData($this->getColumn()->getIndex());
        if ($fyndiqCategoryId) {
            $row = Mage::getModel('fyndiq/category')->getById($fyndiqCategoryId);
            if ($row) {
                $langCode = Mage::app()->getLocale()->getLocaleCode();
                $fieldName = substr($langCode, 0, 2) == 'de' ? 'name_de' : 'name_sv';
                return $row[$fieldName];
            }
        }
        return '';
    }
}
