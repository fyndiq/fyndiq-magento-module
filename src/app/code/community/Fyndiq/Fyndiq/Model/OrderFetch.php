<?php

class Fyndiq_Fyndiq_Model_OrderFetch extends FyndiqPaginatedFetch
{
    private $storeId = 0;
    private $lastUpdate = null;

    public function init($storeId, $lastUpdate)
    {
        $this->storeId = $storeId;
        $this->lastUpdate = $lastUpdate;
    }

    public function getInitialPath()
    {
        return 'orders/' . (empty($this->lastUpdate) ? '' : '?min_date=' . urlencode(date('Y-m-d H:i:s', $this->lastUpdate)));
    }

    public function getPageData($path)
    {
        $ret = Mage::helper('fyndiq_fyndiq/connect')->callApi(
            Mage::getModel('fyndiq/config'),
            $this->storeId,
            'GET',
            $path
        );

        return $ret['data'];
    }

    public function processData($data)
    {
        $errors = array();
        $orderModel = Mage::getModel('fyndiq/order');
        foreach ($data as $order) {
            if (!$orderModel->orderExists(intval($order->id))) {
                try {
                    $orderModel->create($this->storeId, $order);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        if ($errors) {
            throw new Exception(implode(PHP_EOL, $errors));
        }
        return true;
    }

    public function getSleepIntervalSeconds()
    {
        return 1 / self::THROTTLE_ORDER_RPS;
    }
}
