<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 03/09/14
 * Time: 10:33
 */

require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(MAGENTO_ROOT . '/fyndiq/api/fyndiqAPI.php');
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/FyndiqAPICall.php');

class FmHelpers
{
    const ORDERS_ENABLED = 0;
    const ORDERS_DISABLED = 1;

    public static function apiConnectionExists()
    {
        return FmConfig::getBool('username') && FmConfig::getBool('apikey');
    }

    public static function allSettingsExist()
    {
        return FmConfig::getBool('language') && FmConfig::getBool('currency');
    }

    /**
     * wrappers around FyndiqAPI
     * uses stored connection credentials for authentication
     *
     * @param $method
     * @param $path
     * @param array $data
     * @return mixed
     */
    public static function callApi($storeId, $method, $path, $data = array())
    {
        $username = FmConfig::get('username', $storeId);
        $apiToken = FmConfig::get('apikey', $storeId);
        $userAgent = FmConfig::getUserAgent();

        return FyndiqAPICall::callApiRaw(
            $userAgent,
            $username,
            $apiToken,
            $method,
            $path,
            $data,
            array('FyndiqAPI', 'call')
        );
    }

    public static function getProductPrice($objProduct, $storeid)
    {
        $price = '';
        $fyndiq_price = Mage::getResourceModel('catalog/product')->getAttributeRawValue($objProduct->getId(), 'fyndiq_price', $storeId);

        $catalogRulePrice = "";
        $catalogRulePrice = Mage::getModel('catalogrule/rule')->calcProductPriceRule($objProduct, $objProduct->getFinalPrice());
            // Added logc to consider speacial price in feed if it is available
        if ($objProduct->getSpecialPrice()) {
            $today = mktime(0, 0, 0, date('m'), date('d'), date('y'));
            $todaytimestamp = strtotime(date('Y-m-d 00:00:00', $today));
            $spcl_pri_time = is_null($objProduct->getSpecialToDate()) ? $todaytimestamp : strtotime($objProduct->getSpecialToDate());
            if ($spcl_pri_time <= $todaytimestamp) {
                $price = $objProduct->getSpecialPrice();
            } else {
                $price = $objProduct->getPrice();
            }
        } elseif ($catalogRulePrice) {
            $price = $catalogRulePrice;
        } else {
            $price = $objProduct->getPrice();
        }

        if (!Mage::helper('tax')->priceIncludesTax()) {
            $price = Mage::helper('tax')->getPrice($objProduct, $price);
        }

        if(!empty($fyndiq_price) && $price > $fyndiq_price) {
            $price = $fyndiq_price;
        }

        return number_format($price, 2, ".", "");
    }
}
