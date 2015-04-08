<?php
require_once(dirname(dirname(__FILE__)) . '/Model/Order.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');

class Fyndiq_Fyndiq_NotificationController extends Mage_Core_Controller_Front_Action
{
    function indexAction()
    {
        (isset($_GET['order_id'])) ? $orderid = intval($_GET['order_id']): $orderid = 0;

        if ($orderid > 0) {

            $storeId = Mage::app()->getStore()->getStoreId();
            $ret = FmHelpers::call_api($storeId, 'GET', 'orders/' . $orderid . '/');

            $fyndiq_order = $ret['data'];

            $order_model = Mage::getModel('fyndiq/order');

            if (!$order_model->orderExists($fyndiq_order->id)) {
                $order_model->create($storeId, $fyndiq_order);
            }
        }
    }
}
