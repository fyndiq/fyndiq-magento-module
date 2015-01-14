<?php
require_once(dirname(dirname(__FILE__)) . '/Model/Cron.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
class Fyndiq_Fyndiq_FileController extends Mage_Core_Controller_Front_Action {


    function indexAction() {
        $this->getResponse()->setHeader('Content-type', 'text/csv');
        try {
            $fileexists = file_get_contents(FmConfig::getFeedPath());
        }
        catch(Exception $e) {
            $fileexists = false;
        }

        if($fileexists) {
            if(filemtime(FmConfig::getFeedPath()) < strtotime('-1 hour',time())) {
                $FyndiqCron = new Fyndiq_Fyndiq_Model_Cron();
                $FyndiqCron->exportProducts();
                $this->getResponse()->setBody(file_get_contents(FmConfig::getFeedPath()));
            }
            else {
                $this->getResponse()->setBody(file_get_contents(FmConfig::getFeedPath()));
            }
        }
        else {
            $FyndiqCron = new Fyndiq_Fyndiq_Model_Cron();
            $FyndiqCron->exportProducts();
            $this->getResponse()->setBody(file_get_contents(FmConfig::getFeedPath()));
        }

    }
}