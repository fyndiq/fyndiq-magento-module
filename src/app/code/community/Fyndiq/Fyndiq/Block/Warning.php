<?php

class Fyndiq_Fyndiq_Block_Warning extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);

        $currentScope = Mage::helper('adminhtml')->__('Default Config');
        $websiteCode = Mage::app()->getRequest()->getParam('website');
        if ($websiteCode) {
            $currentScope = Mage::getModel('core/website')->load($websiteCode)->getName();
        }
        $storeCode = Mage::app()->getRequest()->getParam('store');
        if ($storeCode) {
            $currentScope = Mage::getModel('core/store')->load($storeCode)->getName();
        }

        $label = sprintf(
            Mage::helper('fyndiq_fyndiq')->__('Please note that you are working on settings for the "%s" configuration scope. Please check the selection in the top left corner of this screen and make sure you are setting up the Fyndiq Magento Extension for the correct scope depending on your requirements. Read the User Guide for more information.'),
            $currentScope
        );
        $html = '<div>'.$label.'</div>';
        return $html;
    }
}
