<?php
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
class FmProductInfo
{
    const SLEEP_INTERVAL_SEC = 1;
    const NS_IN_SEC = 1000000;
    /**
     * Gets all products' info
     *
     * @return bool
     */
    public function getAll($storeid = 0)
    {
        $nextPagePath = 'product_info/';
        do {
            $data = $this->getPageData($nextPagePath, $storeid);
            $start = microtime(true);
            $result = false;
            if ($data) {
                $result = $this->updateProducts($data->results);
                $nextPagePath= $this->getPath($data->next);
            }
            // Sleep the remaining microseconds
            usleep($this->getUSleepInterval($start, microtime(true), self::SLEEP_INTERVAL_SEC * self::NS_IN_SEC));
        } while ($result && $nextPagePath);
        return $result;
    }
    /**
     * Get product single page products' info
     *
     * @param string $path
     * @return mixed
     */
    public function getPageData($path, $storeId)
    {
        $ret = FmHelpers::callApi($storeId, 'GET', $path);
        return $ret['data'];
    }
    /**
     * Gets the usleep interval
     *
     * @param float $start starting time
     * @param float $stop stopping time
     * @param int $max $maximum sleeping time in nanoseconds
     * @return int mixed nanoseconds to sleep before next request
     */
    private function getUSleepInterval($start, $stop, $max)
    {
        return min($max, $max - intval(($stop - $start) * self::NS_IN_SEC));
    }
    /**
     * Gets page path from pagination URL
     *
     * @param string $url
     * @return string
     */
    private function getPath($url)
    {
        if (empty($url)) {
            return '';
        }
        return implode('/', array_slice(explode('/', $url), 5));
    }
    /**
     * Update product status
     *
     * @param mixed $data
     * @return bool
     */
    private function updateProducts($data) {
        $result = true;
        $productModel = Mage::getModel('fyndiq/product');
        foreach ($data as $statusRow) {
            $result &= $productModel->updateProductState($statusRow->product_id, array(
                'state' => $statusRow->for_sale
            ));
        }
        return $result;
    }
}