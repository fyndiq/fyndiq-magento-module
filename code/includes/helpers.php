<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 03/09/14
 * Time: 10:33
 */

require_once(dirname(dirname(__FILE__)) . '/includes/messages.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
require_once(dirname(dirname(__FILE__)) . '/includes/api/fyndiqAPI.php');
require_once(MAGENTO_ROOT . '/fyndiq/shared/src/FyndiqAPICall.php');

class FmHelpers
{

    public static function api_connection_exists()
    {
        if (FmConfig::getBool('username') && FmConfig::getBool('apikey')) {
            return true;
        }
        return false;
    }

    public static function all_settings_exist()
    {
        if (FmConfig::getBool('language') && FmConfig::getBool('currency')) {
            return true;
        }
        return false;
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
    public static function call_api($storeId, $method, $path, $data = array())
    {
        $username = FmConfig::get('username', $storeId);
        $apiToken = FmConfig::get('apikey', $storeId);
        $userAgent = "FyndiqMerchantMagento" . FmConfig::getVersion() . "-" . Mage::getVersion();

        return FyndiqAPICall::callApiRaw($userAgent, $username, $apiToken, $method, $path, $data,
            array('FyndiqAPI', 'call'));
    }
}
