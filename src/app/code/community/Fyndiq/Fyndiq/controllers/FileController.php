<?php

require_once(Mage::getModuleDir('', 'Fyndiq_Fyndiq') . '/lib/shared/src/init.php');

class Fyndiq_Fyndiq_FileController extends Mage_Core_Controller_Front_Action
{

    public function indexAction()
    {
        $lastModified = 0;
        $configModel = Mage::getModel('fyndiq/config');
        $storeId = Mage::app()->getRequest()->getParam('store');

        if ($configModel->get('fyndiq/fyndiq_group/username', $storeId) == '' ||
            $configModel->get('fyndiq/fyndiq_group/apikey', $storeId) == ''
        ) {
            $this->getResponse()->setBody('Module is not set up');
            $this->getResponse()->setHttpResponseCode(500);
            return;
        }

        $filePath = $configModel->getFeedPath($storeId);

        //Check if feed file exist and if it is too old
        if (FyndiqUtils::mustRegenerateFile($filePath)) {
            $exportModel = Mage::getModel('fyndiq/export');
            try {
                $exportModel->generateFeed($storeId);
            } catch (Exception $e) {
            }
        }
        if (!file_exists($filePath)) {
            $this->getResponse()->setBody('Feed could not be generated');
            $this->getResponse()->setHttpResponseCode(500);
            return;
        }
        $lastModified = filemtime($filePath);

        $response = $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Content-type', 'text/csv', true)
            ->setHeader('Content-Disposition', 'attachment; filename="feed.csv"', true)
            ->setHeader('Content-Transfer-Encoding', 'binary', true)
            ->setHeader('Last-Modified', gmdate('r', $lastModified), true);

        $response->clearBody();
        $response->sendHeaders();

        $ioAdapter = new Varien_Io_File();
        $ioAdapter->open(array('path' => $ioAdapter->dirname($filePath)));
        $ioAdapter->streamOpen($filePath, 'r');
        while ($buffer = $ioAdapter->streamRead()) {
            print $buffer;
        }
        $ioAdapter->streamClose();
        exit(0);
    }
}
