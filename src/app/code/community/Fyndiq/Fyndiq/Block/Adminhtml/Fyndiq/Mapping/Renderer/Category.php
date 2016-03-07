<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Renderer_Category
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{

    public function render(Varien_Object $row)
    {
        $categoryId =  $row->getData($this->getColumn()->getIndex());
        return Mage::getModel('fyndiq/export')->getCategoryName($categoryId);
    }
}
