<?php

class Fyndiq_Fyndiq_Model_OrderFetch extends FyndiqPaginatedFetch
{
    private $storeId = 0;
    private $lastUpdate = null;
    private $lastTimestamp = null;

    /**
     * init initializes the fetching class
     *
     * @param  int $storeId
     * @param  int $lastUpdate timestamp for the last time orders were updated
     */
    public function init($storeId, $lastUpdate)
    {
        $this->storeId = $storeId;
        $this->lastUpdate = $lastUpdate;
    }

    /**
     * getInitialPath returns the initial API path for fetching the orders
     * @return [type] [description]
     */
    public function getInitialPath()
    {
        return 'orders/' . (empty($this->lastUpdate) ? '' : '?min_date=' . urlencode(date('Y-m-d H:i:s', $this->lastUpdate)));
    }

    /**
     * getPageData fetches order data given URL path
     * @param  string $path URL path to the resource
     * @return string result pauload
     */
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

    /**
     * processData processes an API payload
     *
     * @param  object $data API response object
     * @return bool
     */
    public function processData($data)
    {
        $errors = array();
        $orderModel = Mage::getModel('fyndiq/order');
        foreach ($data as $order) {
            $timestamp = strtotime($order->created);
            if ($timestamp > $this->lastTimestamp) {
                $this->lastTimestamp = $timestamp;
            }
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

    /**
     * getSleepIntervalSeconds returns the interval between two requests in seconds
     *
     * @return float
     */
    public function getSleepIntervalSeconds()
    {
        return 1 / self::THROTTLE_ORDER_RPS;
    }

    /**
     * getLastTimestamp returns the last order timestamp from the process
     *
     * @return int
     */
    public function getLastTimestamp()
    {
        return $this->lastTimestamp();
    }
}
