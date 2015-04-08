<?php
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/init.php');
require_once(dirname(dirname(__FILE__)) . '/Model/Order.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');

class Fyndiq_Fyndiq_NotificationController extends Mage_Core_Controller_Front_Action
{
    function indexAction() {
        $orderid = $_GET['orderid'];

        $storeId = Mage::app()->getStore()->getStoreId();
        $ret = FmHelpers::call_api($storeId, 'GET', 'orders/'.intval($orderid));

        var_dump($ret);
        $this->getResponse()->setBody(true);
    }
}