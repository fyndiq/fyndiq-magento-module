<?php

require_once(dirname(dirname(__FILE__)) . '/Model/Order.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
require_once(dirname(dirname(__FILE__)) . '/Model/Product_info.php');
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/FyndiqUtils.php');

class Fyndiq_Fyndiq_NotificationController extends Mage_Core_Controller_Front_Action
{

    function __construct() {
        parent::__construct();
        $this->fyndiqOutput = new fyndiqOutput();
    }

    function indexAction()
    {
        $event = $this->getParam('event');
        $eventName = $event ? $event : false;
        if ($eventName) {
            if ($eventName[0] != '_' && method_exists($this, $eventName)) {
                return $this->$eventName();
            }
        }
        $this->error();
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
    private function ping()
    {
        $storeId = Mage::app()->getStore()->getStoreId();
        $pingToken = unserialize(FmConfig::get('ping_token', $storeId));

        $token = $this->getParam('token');
        if (is_null($token) || $token != $pingToken) {
            header('HTTP/1.0 400 Bad Request');

            return die('400 Bad Request');
        }

        $this->flushHeader();

        $locked = false;
        $lastPing = FmConfig::get('ping_time', $storeId);
        $lastPing = $lastPing ? unserialize($lastPing) : false;
        if ($lastPing && $lastPing > strtotime('15 minutes ago')) {
            $locked = true;
        }
        if (!$locked) {
            FmConfig::set('ping_time', time());
            $this->pingObserver();
            $this->_update_product_info($storeId);
        }
    }

    private function debug()
    {
        $storeId = Mage::app()->getRequest()->getParam('store');

        $pingToken = unserialize(FmConfig::get('ping_token', $storeId));
        $token = $this->getRequest()->getParam('token');

        if (is_null($token) || $token != $pingToken) {
            header('HTTP/1.0 400 Bad Request');
            return die('400 Bad Request');
        }

        FyndiqUtils::debugStart();
        FyndiqUtils::debug('USER AGENT', FmConfig::getUserAgent());
        FyndiqUtils::debug('$storeId', $storeId);
        //Check if feed file exist and if it is too old
        $filePath = FmConfig::getFeedPath($storeId);
        FyndiqUtils::debug('$filePath', $filePath);
        FyndiqUtils::debug('is_writable(' . $filePath . ')', is_writable($filePath));

        $fileExistsAndFresh = file_exists($filePath) && filemtime($filePath) > strtotime('-1 hour');
        FyndiqUtils::debug('$fileExistsAndFresh', $fileExistsAndFresh);
        $fyndiqCron = new Fyndiq_Fyndiq_Model_Observer();
        $fyndiqCron->exportProducts($storeId, false);
        $result = file_get_contents($filePath);
        FyndiqUtils::debug('$result', $result, true);
        FyndiqUtils::debugStop();
    }

    private function _update_product_info($storeId)
    {
        $pi = new FmProductInfo($storeId);
        $pi->getAll();
    }

    function getParam($event)
    {
        return $this->getRequest()->getParam($event);
    }

    function flushHeader() {
        return $this->fyndiqOutput->flushHeader();
    }

    function pingObserver()
    {
        $fyndiqCron = new Fyndiq_Fyndiq_Model_Observer();
        $fyndiqCron->exportProducts($storeId, false);
    }

    function error()
    {
        return $this->fyndiqOutput->showError(400, 'Bad Request', 'The request did not work.');
    }
}
