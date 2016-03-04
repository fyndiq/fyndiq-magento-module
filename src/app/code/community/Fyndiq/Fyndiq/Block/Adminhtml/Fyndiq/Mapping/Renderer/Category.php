<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Renderer_Category
    extends Mage_Adminhtml_Block_Widget_Grid_Column_Renderer_Abstract
{

    public function render(Varien_Object $row)
    {
        $value =  $row->getData($this->getColumn()->getIndex());
        return '<span style="color:red;">'.$value.'</span>';
    }
}
