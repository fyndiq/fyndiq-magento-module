<?php
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/init.php');

class FmOrderFetch extends FyndiqPaginatedFetch
{
    function __construct($storeId, $settingExists)
    {
        $this->storeId = $storeId;
        $this->settingExists = $settingExists;
    }

    function getInitialPath()
    {
        $date = false;
        if ($this->settingExists) {
            $date = Mage::getModel('fyndiq/setting')->getSetting($this->storeId, 'order_lastdate');
        }
        $url = 'orders/' . (empty($date) ? '' : '?min_date=' . urlencode($date['value']));

        return $url;
    }

    function getPageData($path)
    {
        $ret = FmHelpers::callApi($this->storeId, 'GET', $path);

        return $ret['data'];
    }

    function processData($data)
    {
        $errors = array();
        foreach ($data as $order) {
            if (!Mage::getModel('fyndiq/order')->orderExists($order->id)) {
                try {
                    Mage::getModel('fyndiq/order')->create($this->storeId, $order);
                } catch (Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
        }
        if ($errors) {
            throw new Exception(implode("\n", $errors));
        }
        return true;
    }

    function getSleepIntervalSeconds()
    {
        return 1 / self::THROTTLE_ORDER_RPS;
    }
}
