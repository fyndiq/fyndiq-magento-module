<?php

require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/lib/shared/src/init.php');
require_once(Mage::getModuleDir('controllers','Mage_Adminhtml').DS.'Sales'.DS.'Order'.DS.'ShipmentController.php');

class Fyndiq_Fyndiq_Adminhtml_Sales_Order_ShipmentController extends Mage_Adminhtml_Sales_Order_ShipmentController
{
    protected function _saveShipment($shipment)
    {
        $result = parent::_saveShipment($shipment);
        $tracks = $shipment->getAllTracks();
        $detailtrack = '';
        $configModel = Mage::getModel('fyndiq/config');
        foreach($tracks as $track) {
            $orderId = $this->getFyndiqOrderId($track->getOrderId());
            if (!empty($orderId)) {
                $storeId = $track->getStoreId();
                $trackNumber = $track->getTrackNumber();
                $carrierCode = $track->getCarrierCode();
                $url = 'packages/' . $orderId . '/';
                $data = array(
                    'packages' => array(
                        'service' => $this->getServiceByCarrierCode($carrierCode),
                        'tracking' => $trackNumber,
                        'sku' => $this->getSKUs(),
                    )
                );
                try {
                    Mage::helper('api')->callApi($configModel, $storeId, 'PUT', $url, $data);
                } catch (Excepton $e) {
                    Mage::log('Error sending package information to Fyndiq: ' . $e->getMessage() , Zend_Log::ERR);
                }
            }
        }
        return $result;
    }

    protected function getFyndiqOrderId($orderId)
    {
        $order = Mage::getModel('sales/order')
            ->load($orderId);
        return $order->getData('fyndiq_order_id');
    }

    protected function getServiceByCarrierCode($carrierCode)
    {
        // TODO: Implement me
        return $carrierCode;
    }

    protected function getSKUs()
    {
        // TODO: Implement me ??
        return array();
    }
}
