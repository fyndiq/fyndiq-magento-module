<?php
require_once(dirname(dirname(__FILE__)) . '/Model/Order.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');

class Fyndiq_Fyndiq_NotificationController extends Mage_Core_Controller_Front_Action
{
    function indexAction()
    {
        $event = $this->getRequest()->getParam('event');
        $eventName = $event ? $event : false;
        if ($eventName) {
            if (method_exists($this, $eventName)) {
                return $this->$eventName();
            }
        }
        header('HTTP/1.0 400 Bad Request');
        die('400 Bad Request');
    }

    /**
     * Handle new order
     *
     * @return bool
     */
    function order_created()
    {
        $orderId = $this->getRequest()->getParam('order_id');
        $orderId = is_numeric($orderId) ? intval($orderId) : 0;
        if ($orderId > 0) {
            try {
                $storeId = Mage::app()->getStore()->getStoreId();
                $ret = FmHelpers::callApi($storeId, 'GET', 'orders/' . $orderId . '/');

                $fyndiqOrder = $ret['data'];

                $orderModel = Mage::getModel('fyndiq/order');

                if (!$orderModel->orderExists($fyndiqOrder->id)) {
                    $orderModel->create($storeId, $fyndiqOrder);
                }
            } catch (Exception $e) {
                header('HTTP/1.0 500 Internal Server Error');
                die('500 Internal Server Error');
            }
            return true;
        }
        header('HTTP/1.0 400 Bad Request');
        die('400 Bad Request');
    }


    /**
     * Generate feed
     *
     */
    private function ping() {
        $token = $this->getRequest()->getParam('token');
        die($token);
    }

}
