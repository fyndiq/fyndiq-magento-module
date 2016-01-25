<?php

class Fyndiq_Fyndiq_Block_Export extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        if (!$this->getRequest()->getParam('import')) {
            return Mage::helper('fyndiq_fyndiq')->__('Disabled');
        }
        $this->setElement($element);

        $url =  Mage::getUrl('adminhtml/fyndiq/importSKUs');
        $js = "new Ajax.Request('$url',{method:'post',parameters:{skus:document.getElementById('export-sku').value},onSuccess:function(e,t){alert(e.responseText)}});";
        $html = '<textarea id="export-sku"></textarea>';

        $html .= $this->getLayout()->createBlock('adminhtml/widget_button')
            ->setType('button')
            ->setClass('scalable')
            ->setLabel(Mage::helper('fyndiq_fyndiq')->__('Export to Fyndiq'))
            ->setOnClick($js)
            ->toHtml();
        return $html;
    }
}
