<?php

require_once(dirname(dirname(__FILE__)) . '/includes/config.php');
class Fyndiq_Fyndiq_FileController extends Mage_Core_Controller_Front_Action {


    function indexAction() {
        $this->getResponse()->setHeader('Content-type', 'text/csv');
        if(file_get_contents(FmConfig::getFeedPath()) != false) {
            $this->getResponse()->setBody(file_get_contents(FmConfig::getFeedPath()));
        }
        else {
            $this->getResponse()->setBody("");
        }

    }
}