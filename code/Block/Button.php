<?php

class Fyndiq_Fyndiq_Block_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $url = Mage::helper("adminhtml")->getUrl("fyndiq/admin/disconnect", null);

        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setClass('scalable')
            ->setLabel('Disconnect')
            ->setOnClick("setLocation('$url')")
            ->toHtml();

        return $html;
    }
}

?>