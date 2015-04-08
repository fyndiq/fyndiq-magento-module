<?php
require_once(dirname(dirname(__FILE__)) . '/Model/Order.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');

class Fyndiq_Fyndiq_NotificationController extends Mage_Core_Controller_Front_Action
{
    function indexAction() {
        $orderid = $_GET['order_id'];

        $storeId = Mage::app()->getStore()->getStoreId();
        $ret = FmHelpers::call_api($storeId, 'GET', 'orders/'.intval($orderid) . '/');

        $fyndiq_order = $ret['data'];

        $order_model = Mage::getModel('fyndiq/order');

        if (!$order_model->orderExists($fyndiq_order->id)) {
            $order_model->create($storeId, $fyndiq_order);
        }
    }
}
