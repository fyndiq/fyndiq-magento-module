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
            $order = Mage::getModel('sales/order')->load($track->getOrderId());
            $orderId = $order->getData('fyndiq_order_id');
            if (!empty($orderId)) {
                $storeId = $track->getStoreId();
                $trackNumber = $track->getTrackNumber();
                $carrierCode = $track->getCarrierCode();
                $url = 'packages/' . $orderId . '/';
                if ($carrierCode) {
                    $serviceCode = Mage::helper('tracking')->getDeliveryMapping($carrierCode, $storeId);
                    if ($serviceCode) {
                        $data = array(
                            'packages' => array(
                                array(
                                    'service' => $serviceCode,
                                    'tracking' => $trackNumber,
                                    // FIXME: Don't send SKU's for now
                                    //'sku' => $this->getSKUs($order),
                                )
                            )
                        );
                        $api = Mage::helper('api');
                        try {
                            $api->callApi($configModel, $storeId, 'PUT', $url, $data);
                        } catch (Excepton $e) {
                            Mage::log('Error sending package information to Fyndiq: ' . $e->getMessage() , Zend_Log::ERR);
                        }
                    }
                }
            }
        }
        return $result;
    }

    protected function getSKUs($order)
    {
        $skus = array();
        $orderedItems = $order->getAllVisibleItems();
        $orderedProductIds = [];

        foreach ($orderedItems as $item) {
            $orderedProductIds[] = $item->getData('product_id');
        }

        $productCollection = Mage::getModel('catalog/product')
            ->getCollection()
            ->addAttributeToSelect('*')
            ->addIdFilter($orderedProductIds);
        foreach ($productCollection->load() as $product) {
            $skus[] = $product->getSKU();
        }
        return $skus;
    }
}
