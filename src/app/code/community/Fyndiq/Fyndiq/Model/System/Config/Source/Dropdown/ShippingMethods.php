<?php

class Fyndiq_Fyndiq_Model_System_Config_Source_Dropdown_ShippingMethods
{
    public function toOptionArray()
    {
        $methods = array();
        $shipping = Mage::getSingleton('shipping/config')->getActiveCarriers();
        foreach ($shipping as $shippingCode => $shippingModel) {
            $shippingTitle = Mage::getStoreConfig('carriers/'.$shippingCode.'/title');
            $methods[] = array(
                'label' => $shippingTitle,
                'value' => $shippingCode,
            );
        }
        return $methods;
    }
}
