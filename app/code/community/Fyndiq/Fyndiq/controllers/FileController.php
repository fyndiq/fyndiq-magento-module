<?php
require_once(dirname(dirname(__FILE__)) . '/Model/Fyndiqcron.php');
require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
class Fyndiq_Fyndiq_FileController extends Mage_Core_Controller_Front_Action {


    function indexAction() {
        //Setting content type to csv.
        $this->getResponse()->setHeader('Content-type', 'text/csv');

        //Check if feed file exist and if it is too old
        try {
            $fileexists = file_get_contents(FmConfig::getFeedPath());
        }
        catch(Exception $e) {
            $fileexists = false;
        }

        if($fileexists) {
            // If feed last modified date is older than 1 hour, create a new one
            // just if the cronjob didn't run.
            if(filemtime(FmConfig::getFeedPath()) < strtotime('-1 hour',time())) {
                $FyndiqCron = new Fyndiq_Fyndiq_Model_FyndiqCron();
                $FyndiqCron->exportProducts();
            }
        }
        else {
            //The file hasn't been created yet, create it.
            $FyndiqCron = new Fyndiq_Fyndiq_Model_FyndiqCron();
            $FyndiqCron->exportProducts();
        }

        //printing out the content from feed file to the visitor.
        $this->getResponse()->setBody(file_get_contents(FmConfig::getFeedPath()));
    }
}