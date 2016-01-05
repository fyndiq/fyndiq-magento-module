<?php

require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/lib/shared/src/init.php');
require_once(Mage::getModuleDir('controllers','Mage_Adminhtml').DS.'Sales'.DS.'Order'.DS.'ShipmentController.php');

class Fyndiq_Fyndiq_Adminhtml_Sales_Order_ShipmentController extends Mage_Adminhtml_Sales_Order_ShipmentController
{
    protected function _saveShipment($shipment)
    {
        error_log(get_class($shipment));
        return parent::_saveShipment($shipment);
    }
}
