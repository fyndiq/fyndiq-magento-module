<?php
require_once(dirname(dirname(__FILE__)) . '/Model/Observer.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');

class Fyndiq_Fyndiq_FileController extends Mage_Core_Controller_Front_Action
{

    function indexAction()
    {
        $result = '';
        $lastModified = 0;
        //Setting content type to csv.
        $this->getResponse()->setHeader('Content-type', 'text/csv');
        $storeId = Mage::app()->getRequest()->getParam('store');
        if ($this->getUsername($storeId) != '' && $this->getAPIToken($storeId) != '') {
            //Check if feed file exist and if it is too old
            $filePath = FmConfig::getFeedPath($storeId);
            if (FyndiqUtils::mustRegenerateFile($filePath)) {
                $fyndiqCron = new Fyndiq_Fyndiq_Model_Observer();
                $fyndiqCron->exportProducts($storeId, false);
            }
            if (file_exists($filePath)) {
                $lastModified = filemtime($filePath);
            }
            $result = file_get_contents($filePath);
        }
        if ($lastModified) {
            $this->getResponse()->setHeader('Last-Modified', gmdate('D, d M Y H:i:s T', $lastModified));
        }
        $this->getResponse()->setHeader('Content-Disposition', 'attachment; filename="feed.csv"');
        $this->getResponse()->setHeader('Content-Transfer-Encoding', 'binary');
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
