<?php

class Fyndiq_Fyndiq_Model_Sales_Quote_Address extends Mage_Sales_Model_Quote_Address
{
    public function getShippingRatesCollection()
    {
        parent::getShippingRatesCollection();

        $removeRates = array();

        foreach ($this->_rates as $key => $rate) {
            if ($rate->getCarrier() == 'fyndiq_fyndiq') {
                $removeRates[] = $key;
            }
        }

        foreach ($removeRates as $key) {
            $this->_rates->removeItemByKey($key);
        }

        return $this->_rates;
    }
}
