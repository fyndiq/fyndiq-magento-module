<?php

class Fyndiq_Fyndiq_Block_Updated extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $observer = Mage::getModel('fyndiq/observer');
        $storeId = $observer->getStoreId();
        $generatedTime = Mage::getModel('fyndiq/config')->get('fyndiq/feed/generated_time', $storeId);
        $label = Mage::helper('fyndiq_fyndiq')->__('never');
        if ($generatedTime) {
            $label = date('r', $generatedTime);
        }
        $html = '<label style="font-weight: bold;">'.$label.'</label>';
        return $html;
    }
}
