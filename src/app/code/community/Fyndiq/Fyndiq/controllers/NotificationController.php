<?php

require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/lib/shared/src/init.php');

class Fyndiq_Fyndiq_NotificationController extends Mage_Core_Controller_Front_Action
{

    private $fyndiqOutput = null;
    private $configModel = null;

    protected function _construct()
    {
        $this->configModel = Mage::getModel('fyndiq/config');
    }

    public function indexAction()
    {
        FyndiqTranslation::init(Mage::app()->getLocale()->getLocaleCode());
        $event = $this->getRequest()->getParam('event');
        $eventName = $event ? $event : false;
        if ($eventName) {
            if ($eventName[0] != '_' && method_exists($this, $eventName)) {
                return $this->$eventName();
            }
        }
        return $this->getFyndiqOutput()->showError(400, 'Bad Request', 'The request did not work.');
    }

    /**
     * Handle new order
     *
     * @return bool
     */
    function order_created()
    {
        $storeId = $this->getRequest()->getParam('store');
        if ($this->configModel->get('import_orders_disabled', $storeId) == Fyndiq_Fyndiq_Model_Order::ORDERS_DISABLED) {
            return $this->getFyndiqOutput()->showError(403, 'Forbidden', 'Forbidden');
        }
        $orderId = $this->getRequest()->getParam('order_id');
        $orderId = is_numeric($orderId) ? intval($orderId) : 0;
        if ($orderId > 0) {
            try {
                // Set the area as backend so shipping method is not skipped
                $response = Mage::helper('api')->callApi($this->configModel, $storeId, 'GET', 'orders/' . $orderId . '/');
                $fyndiqOrder = $response['data'];

                Mage::getDesign()->setArea(Mage_Core_Model_App_Area::AREA_ADMIN);
                $orderModel = Mage::getModel('fyndiq/order');
                if (!$orderModel->orderExists($fyndiqOrder->id)) {
                    $reservationId = $orderModel->reserve(intval($fyndiqOrder->id));
                    $orderModel->create($storeId, $fyndiqOrder, $reservationId);
                }
            } catch (Exception $e) {
                $orderModel->unreserve($reservationId);
                // $inbox = Mage::getModel('Mage_AdminNotification_Model_Inbox');
                // $inbox->addMinor(
                //     sprintf('Fyndiq Order %s could not be imported', $orderId),
                //     $e->getMessage()
                // );
                return $this->getFyndiqOutput()->showError(500, 'Internal Server Error', $e->getMessage());
            }
            return $this->getFyndiqOutput()->output('OK');
        }
        return $this->getFyndiqOutput()->showError(400, 'Bad Request', 'The request did not work.');
    }

    protected function isPingLocked($storeId)
    {
        $lastPing = $this->configModel->get('ping_time', $storeId);
        return $lastPing && $lastPing > strtotime('15 minutes ago');
    }

    protected function isCorrectToken($token, $storeId)
    {
        $pingToken = $this->configModel->get('ping_token', $storeId);
        return !(is_null($token) || $token != $pingToken);
    }

    /**
     * Generate feed
     *
     */
    private function ping()
    {
        $storeId = $this->getRequest()->getParam('store');
        if (!$this->isCorrectToken($this->getRequest()->getParam('token'), $storeId)) {
            return $this->getFyndiqOutput()->showError(400, 'Bad Request', 'The request did not work.');
        }

        $this->getFyndiqOutput()->flushHeader('OK');
        if (!$this->isPingLocked($storeId)) {
            $this->configModel->set('ping_time', time(), $storeId);
            $this->configModel->reInit();
            $exportModel = Mage::getModel('fyndiq/export');
            try {
                $exportModel->generateFeed($storeId);
            } catch (Exception $e) {
            }
        }
    }

    private function debug()
    {
        $storeId = Mage::app()->getRequest()->getParam('store');

        if (!$this->isCorrectToken($this->getRequest()->getParam('token'), $storeId)) {
            return $this->getFyndiqOutput()->showError(400, 'Bad Request', 'The request did not work.');
        }

        FyndiqUtils::debugStart();
        FyndiqUtils::debug('USER AGENT', $this->configModel->getUserAgent());
        FyndiqUtils::debug('$storeId', $storeId);
        $filePath = $this->configModel->getFeedPath($storeId);
        FyndiqUtils::debug('$filePath', $filePath);
        FyndiqUtils::debug('is_writable(' . $filePath . ')', is_writable($filePath));
        FyndiqUtils::debug('mustRegenerate', FyndiqUtils::mustRegenerateFile($filePath));

        $exportModel = Mage::getModel('fyndiq/export');
        try {
            $exportModel->generateFeed($storeId);
        } catch (Exception $e) {
            FyndiqUtils::debug('UNHANDLED ERROR', $e->getMessage());
        }

        $result = file_get_contents($filePath);
        FyndiqUtils::debug('$result', $result, true);
        FyndiqUtils::debugStop();
    }

    protected function pingObserver($storeId)
    {
        $fyndiqCron = new Fyndiq_Fyndiq_Model_Observer();
        $fyndiqCron->generateFeed($storeId);
    }

    protected function getFyndiqOutput()
    {
        if (!$this->fyndiqOutput) {
            $this->fyndiqOutput = new FyndiqOutput();
        }
        return $this->fyndiqOutput;
    }
}
