<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Updated extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $html = '<textarea readonly="readonly">'.''.'</textarea>';
        return $html;
    }
}
