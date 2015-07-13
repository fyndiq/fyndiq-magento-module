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
        $storeId = Mage::app()->getRequest()->getParam('store');
        if ($this->getUsername($storeId) != '' && $this->getAPIToken($storeId) != '') {
            //Check if feed file exist and if it is too old
            $filePath = FmConfig::getFeedPath($storeId);
            if (!$this->mustRegenerateFile($filePath)) {
                $fyndiqCron = new Fyndiq_Fyndiq_Model_Observer();
                $fyndiqCron->exportProducts($storeId, false);
            }
            $result = file_get_contents($filePath);
        }
        //printing out the content from feed file to the visitor.
        $this->getResponse()->setBody($result);
    }

    /**
     * Return true if file must be regenerated
     *
     * @param  string $filePath
     * @return bool
     */
    private function mustRegenerateFile($filePath)
    {
        if (getenv('FYNDIQ_DEBUG') == 1) {
            return true;
        }
        if (file_exists($filePath) && filemtime($filePath) > strtotime('-1 hour')) {
            return false;
        }
        return true;
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
