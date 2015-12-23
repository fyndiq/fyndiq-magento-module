<?php

require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/lib/api/fyndiqAPI.php');
require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/lib/shared/src/FyndiqAPICall.php');

class Fyndiq_Fyndiq_Helper_Api_Data extends Mage_Core_Helper_Abstract
{

    public function callApi($configModel, $storeId, $method, $path, $data = array())
    {

        $username = $configModel->get('fyndiq/fyndiq_group/username', $storeId);
        $apiToken = $configModel->get('fyndiq/fyndiq_group/apikey', $storeId);
        $userAgent = $configModel->getUserAgent();

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
