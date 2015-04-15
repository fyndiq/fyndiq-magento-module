<?php
require_once(dirname(dirname(__FILE__)) . '/Model/Observer.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');

class Fyndiq_Fyndiq_FileController extends Mage_Core_Controller_Front_Action
{

    function indexAction()
    {
        $result = '';
        //Setting content type to csv.
        $this->getResponse()->setHeader('Content-type', 'text/csv');
        $storeId = Mage::app()->getStore()->getStoreId();
        if ($this->getUsername($storeId) != '' && $this->getAPIToken($storeId) != '') {
            //Check if feed file exist and if it is too old
            $filePath = FmConfig::getFeedPath($storeId);
            $fileExists = file_exists($filePath);
            $fileNotExistOrOld = ($fileExists) && (filemtime(FmConfig::getFeedPath($storeId)) > strtotime('-1 hour', time()));
            if (!$fileNotExistOrOld) {
                $fyndiqCron = new Fyndiq_Fyndiq_Model_Observer();
                $fyndiqCron->exportProducts($storeId, false);
            }
            $result = file_get_contents($filePath);
        }
        //printing out the content from feed file to the visitor.
        $this->getResponse()->setBody($result);
    }


    /**
     * Get the username from config
     *
     * @param $storeId
     * @return mixed
     */
    private function getUsername($storeId)
    {
        return FmConfig::get('username', $storeId);
    }

    /**
     * Get APItoken from config
     *
     * @param $storeId
     * @return mixed
     */
    private function getAPIToken($storeId)
    {
        return FmConfig::get('username', $storeId);
    }
}