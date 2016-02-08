<?php

class Fyndiq_Fyndiq_Block_Reinstall extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $url =  Mage::getUrl('adminhtml/fyndiq/reinstall');
        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setClass('scalable')
            ->setLabel(Mage::helper('fyndiq_fyndiq')->__('Reinstall'))
            ->setOnClick('setLocation(\''.$url.'\')')
            ->toHtml();
        return $html;
    }
}
