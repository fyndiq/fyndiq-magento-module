<?php

class Fyndiq_Fyndiq_Model_System_Config_Source_Dropdown_Carrier
{

    public function toOptionArray()
    {
        $carriers = array(
            array(
                'label'=> '',
                'value'=> '',
            )
        );
        $carrierInstances = Mage::getSingleton('shipping/config')->getAllCarriers(
            Mage::getModel('fyndiq/observer')->getStoreId()
        );

        foreach ($carrierInstances as $code => $carrier) {
            if ($carrier->isTrackingAvailable()) {
                $carriers[] = array(
                    'label' => $carrier->getConfigData('title'),
                    'value' => $code,
                );
            }
        }
        return $carriers;
    }
}
