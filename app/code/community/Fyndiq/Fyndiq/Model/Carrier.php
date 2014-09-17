<?php
class Fyndiq_Fyndiq_Model_Carrier
    extends Mage_Shipping_Model_Carrier_Abstract
    implements Mage_Shipping_Model_Carrier_Interface
{
    protected $_code = 'fyndiq';

    public function collectRates(
        Mage_Shipping_Model_Rate_Request $request
    )
    {
        $result = Mage::getModel('shipping/rate_result');
        /* @var $result Mage_Shipping_Model_Rate_Result */

        $result->append($this->_getStandardShippingRate());

        return $result;
    }

    protected function _getStandardShippingRate()
    {
        $rate = Mage::getModel('shipping/rate_result_method');
        /* @var $rate Mage_Shipping_Model_Rate_Result_Method */

        $rate->setCarrier($this->_code);
        /**
         * getConfigData(config_key) returns the configuration value for the
         * carriers/[carrier_code]/[config_key]
         */
        $rate->setCarrierTitle($this->getConfigData('title'));

        $rate->setMethod('fyndiq');
        $rate->setMethodTitle('fyndiq');

        $rate->setPrice(0);
        $rate->setCost(0);

        return $rate;
    }

    public function getAllowedMethods()
    {
        return array(
            'standard' => 'Standard',
        );
    }
}