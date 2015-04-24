<?php
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/init.php');

class FmProductInfo extends FyndiqPaginatedFetch
{

    private $storeId = '';


    function __construct($storeId)
    {
        $this->storeId = $storeId;
    }

    /**
     * Get product single page products' info
     *
     * @param string $path
     * @return mixed
     */
    public function getPageData($path)
    {
        $ret = FmHelpers::callApi($this->storeId, 'GET', $path);

        return $ret['data'];
    }


    function getInitialPath()
    {
        return 'product_info/';
    }

    function getSleepIntervalSeconds()
    {
        return 1 / self::THROTTLE_PRODUCT_INFO_RPS;
    }


    /**
     * Update product status
     *
     * @param mixed $data
     * @return bool
     */
    public function processData($data)
    {
        $result = true;
        $productModel = Mage::getModel('fyndiq/product');
        foreach ($data as $statusRow) {
            $result &= $productModel->updateProductState(
                $statusRow->product_id,
                array(
                    'state' => $statusRow->for_sale
                )
            );
        }

        return $result;
    }
}
