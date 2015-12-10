<?php

require_once(Mage::getModuleDir('lib', 'Fyndiq_Fyndiq') . '/fyndiq/api/fyndiqAPI.php');
require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/fyndiq/shared/src/FyndiqAPICall.php');
//var/www/html/magento/app/code/community/Fyndiq/Fyndiq/fyndiq/api/fyndiqAPI.php

class Fyndiq_Fyndiq_Helper_Api_Data extends Mage_Core_Helper_Abstract
{

    public function callApi($storeId, $method, $path, $data = array())
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
