<?php

class Fyndiq_Fyndiq_Model_Carrier extends Mage_Shipping_Model_Carrier_Abstract implements Mage_Shipping_Model_Carrier_Interface
{
    const METHOD_TITLE = 'Standard';

    protected $_code = 'fyndiq_fyndiq';

    /**
     * Get rates of the shipping method
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return Mage_Shipping_Model_Rate_Result
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {
        if (!$this->getConfigFlag('active')) {
            return false;
        }

        // Disable Fyndiq shipping method for non admin area
        if (!in_array(
            Mage::getDesign()->getArea(),
            array(
                Mage_Core_Model_App_Area::AREA_ADMIN,
                Mage_Core_Model_App_Area::AREA_ADMINHTML,
            )
        )) {
            return false;
        }

        /* @var $result Mage_Shipping_Model_Rate_Result */
        $result = Mage::getModel('shipping/rate_result');
        $result->append($this->_getStandardShippingRate());
        return $result;
    }

    /**
     * Get standard rate for this method
     *
     * @return Mage_Shipping_Model_Rate_Result_Method
     */
    protected function _getStandardShippingRate()
    {
        /* @var $rate Mage_Shipping_Model_Rate_Result_Method */
        $rate = Mage::getModel('shipping/rate_result_method');

        $rate->setCarrier($this->_code);
        /**
         * getConfigData(config_key) returns the configuration value for the
         * carriers/[carrier_code]/[config_key]
         */
        $rate->setCarrierTitle($this->getConfigData('title'));
        $rate->setMethod('standard');
        $rate->setMethodTitle(self::METHOD_TITLE);
        $rate->setPrice(0);
        $rate->setCost(0);
        return $rate;
    }

    /**
     * Get allowed type of methods.
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array(
            'standard' => self::METHOD_TITLE,
        );
    }
}
