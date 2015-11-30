<?php
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/init.php');

class FmOrderFetch extends FyndiqPaginatedFetch
{
    public function __construct($storeId, $settingExists)
    {
        $this->storeId = $storeId;
        $this->settingExists = $settingExists;
    }

    public function getInitialPath()
    {
        $date = false;
        if ($this->settingExists) {
            $date = Mage::getModel('fyndiq/setting')->getSetting($this->storeId, 'order_lastdate');
        }
        $url = 'orders/' . (empty($date) ? '' : '?min_date=' . urlencode($date['value']));

        return $url;
    }

    public function getPageData($path)
    {
        $ret = FmHelpers::callApi($this->storeId, 'GET', $path);

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
            throw new Exception(implode("<br/>\n", $errors));
        }
        return true;
    }

    public function getSleepIntervalSeconds()
    {
        return 1 / self::THROTTLE_ORDER_RPS;
    }
}
