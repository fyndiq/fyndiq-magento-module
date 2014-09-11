<?php

/**
 * Created by PhpStorm.
 * User: confact
 * Date: 18/08/14
 * Time: 09:50
 */
require_once(dirname(dirname(__FILE__)) . '/includes/helpers.php');
class Fyndiq_Fyndiq_AdminController extends Mage_Adminhtml_Controller_Action
{

    public function indexAction()
    {
        $this->loadLayout();

        //create a text block with the name of "example-block"
        $block = $this->getLayout()
            ->createBlock('core/text', 'example-block')
            ->setText('<h1>This is a text block</h1>');

        $this->_addContent($block);

        $this->renderLayout();
    }

    public function exportproductsAction()
    {
        $this->loadLayout(array('default'));

        //$this->Heads();
        try {
            FmHelpers::call_api('GET', 'account/');
            $api_available = true;
        }
        catch (Exception $e) {
            $api_available = false;
            $page_args['message'] = $e->getMessage();
        }
        if($this->getAPIToken() == "" OR $this->getUsername() == "") {
            $this->setupTemplate('fyndiq/needapiinfo.phtml');
        }
        else if(!$api_available) {

            $this->setupTemplate('fyndiq/apierror.phtml', $page_args);
        }
        else {
            $this->setupTemplate('fyndiq/exportproducts.phtml');
        }
    }

    private function setupTemplate($template, $data = null) {
        $block = $this->getLayout()
            ->createBlock('Fyndiq_Fyndiq_Block_Admin', 'fyndiq.admin')
            ->setTemplate($template);
        if($data != null) {
            $block->setData('data', $data);
        }
        $this->getLayout()->getBlock('content')->append($block);

        $this->renderLayout();
    }

    public function getUsername()
    {
        return Mage::getStoreConfig('fyndiq/fyndiq_group/username');
    }
    public function getAPIToken()
    {
        return Mage::getStoreConfig('fyndiq/fyndiq_group/apikey');
    }
    /*
        /**
         * @see Mage_Core_Controller_Front_Action::renderLayout($output)
         *
        public function renderLayout($output = '')
        {
            //Add main template to to content block
            //$block = $this->getLayout()
            //    ->createBlock('Mage_Core_Block_Template', 'fyndiq.exportproducts')
            //    ->setTemplate('fyndiq/exportproducts.phtml');
            //$block->assign(get_object_vars($this));

            $mainViewBlock = $this->getLayout()->addBlock(new Fyndiq_Fyndiq_Block_exportproducts(), 'Fyndiq/exportproducts')->setTemplate('fyndiq/exportproducts.phtml');
            $mainViewBlock->assign(get_object_vars($this));
            $this->getLayout()->getBlock('content')->append($mainViewBlock);

            return parent::renderLayout($output);
        }*/
}