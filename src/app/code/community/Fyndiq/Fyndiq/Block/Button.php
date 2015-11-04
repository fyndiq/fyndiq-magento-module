<?php

class Fyndiq_Fyndiq_Block_Button extends Mage_Adminhtml_Block_System_Config_Form_Field
{

    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $request = $this->getRequest();
        $segments = array(
            'fyndiq/admin/disconnect',
            'website',
            $request->getParam('website'),
            'store',
            $request->getParam('store'),
        );
        $url = Mage::helper("adminhtml")->getUrl(implode('/', $segments), null);

        $html = $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setClass('scalable')
            ->setLabel('Disconnect')
            ->setOnClick("setLocation('$url')")
            ->toHtml();

        return $html;
    }
}
