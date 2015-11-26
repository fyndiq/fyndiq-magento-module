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
}
