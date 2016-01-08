<?php

class Fyndiq_Fyndiq_Helper_Tracking_Data extends Mage_Core_Helper_Abstract
{

    public function getFyndiqDeliveryServices()
    {
        return array(
            'postnord' => 'PostNord',
            'schenker' => 'Schenker',
            'dhl' => 'DHL',
            'bring' => 'Bring',
            'deutsche-post' => 'Deutsche Post',
            'dpd' => 'DPD',
            'gls' => 'GLS',
            'ups' => 'UPS',
            'hermes' => 'Hermes',
        );
    }

    public function getDeliveryMapping($shippingProviderCode, $storeId)
    {
        $configModel =  Mage::getModel('fyndiq/config');
        foreach ($this->getFyndiqDeliveryServices() as $serviceCode => $serviceName) {
            $list = $configModel->get('fyndiq/tracking/' . $serviceCode, $storeId);
            $codes = explode(',', $list);
            if (in_array($shippingProviderCode, $codes)) {
                return $serviceName;
            }
        }
        return '';
    }

    public function getDuplicates($storeId)
    {
        $duplicates = array();
        $used = array();
        $configModel =  Mage::getModel('fyndiq/config');
        foreach (array_keys($this->getFyndiqDeliveryServices()) as $serviceCode) {
            $list = $configModel->get('fyndiq/tracking/' . $serviceCode, $storeId);
            $codes = explode(',', $list);
            foreach ($codes as $code) {
                if ($code) {
                    if (in_array($code, $used)) {
                        $duplicates[] = $code;
                        continue;
                    }
                    $used[] = $code;
                }
            }
        }
        return array_unique($duplicates);
    }
}
