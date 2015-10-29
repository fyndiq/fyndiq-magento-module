<?php

class Fyndiq_Fyndiq_Model_Sales_Quote_Address extends Mage_Sales_Model_Quote_Address
{
    const CARRIER_NAME = 'fyndiq_fyndiq';

    public function getShippingRatesCollection()
    {
        parent::getShippingRatesCollection();

        $removeRates = array();

        foreach ($this->_rates as $key => $rate) {
            if ($rate->getCarrier() == self::CARRIER_NAME) {
                $removeRates[] = $key;
            }
        }

        foreach ($removeRates as $key) {
            $this->_rates->removeItemByKey($key);
        }

        return $this->_rates;
    }
}
