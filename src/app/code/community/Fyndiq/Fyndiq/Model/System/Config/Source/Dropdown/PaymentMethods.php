<?php

class Fyndiq_Fyndiq_Model_System_Config_Source_Dropdown_PaymentMethods
{
    public function toOptionArray()
    {
        $methods = array();
        $payments = Mage::getSingleton('payment/config')->getActiveMethods();
        foreach ($payments as $paymentCode => $paymentModel) {
            $paymentTitle = Mage::getStoreConfig('payment/' . $paymentCode . '/title');
            $methods[$paymentCode] = array(
                'label'   => $paymentTitle,
                'value' => $paymentCode,
            );
        }
        return $methods;
    }
}
