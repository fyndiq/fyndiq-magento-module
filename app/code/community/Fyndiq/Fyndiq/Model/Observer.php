<?php
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
/**
 * Class Fyndiq_Fyndiq_Model_Observer
 */
class Fyndiq_Fyndiq_Model_Observer
{
    /**
     * exporting a quantity of a product when selling to Fyndiq
     *
     * @param Varien_Event_Observer $observer
     */
    public function exportQuantity(Varien_Event_Observer $observer)
    {
        //Check if settings for automatic product export is set
        if(FmConfig::getBool('automaticquantityexport')) {
            // Retrieve the product being updated from the event observer
            $product = $observer->getEvent()->getProduct();

            // Write a new line to var/log/product-updates.log
            $name = $product->getName();
            $sku = $product->getSku();
            Mage::log(
                "{$name} ({$sku}) updated",
                null,
                'Fyndiq_module.log'
            );
        }
    }
}