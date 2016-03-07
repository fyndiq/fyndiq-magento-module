<?php

class Fyndiq_Fyndiq_Block_CategoryUpdated extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        $this->setElement($element);
        $updateTime = Mage::getModel('fyndiq/config')
            ->get('fyndiq/troubleshooting/categories_check_time', Mage_Core_Model_App::ADMIN_STORE_ID);
        $label = Mage::helper('fyndiq_fyndiq')->__('never');
        if ($updateTime) {
            $label = date('r', $updateTime);
        }
        $html = '<label style="font-weight: bold;">'.$label.'</label>';
        return $html;
    }
}
