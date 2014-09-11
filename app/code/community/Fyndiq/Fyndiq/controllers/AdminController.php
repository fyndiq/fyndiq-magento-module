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

    /**
     * The page where everything happens.
     * TODO: maybe move this to index instead?
     */
    public function exportproductsAction()
    {
        $this->loadLayout(array('default'));

        //$this->Heads();
        try {
            FmHelpers::call_api('GET', 'account/');
            $api_available = true;
        } catch (Exception $e) {
            $api_available = false;
            $page_args['message'] = $e->getMessage();
        }
        if ($this->getAPIToken() == "" OR $this->getUsername() == "") {
            $this->setupTemplate('fyndiq/needapiinfo.phtml');
        } else {
            if (!$api_available) {

                $this->setupTemplate('fyndiq/apierror.phtml', $page_args);
            } else {
                $this->setupTemplate('fyndiq/exportproducts.phtml');
            }
        }
    }

    /**
     * Setting up the template with correct block and everything.
     *
     * @param $template
     * @param null $data
     */
    private function setupTemplate($template, $data = null)
    {
        $block = $this->getLayout()
            ->createBlock('Fyndiq_Fyndiq_Block_Admin', 'fyndiq.admin')
            ->setTemplate($template);
        if ($data != null) {
            $block->setData('data', $data);
        }
        $this->getLayout()->getBlock('content')->append($block);

        $this->renderLayout();
    }

    /**
     * Get the username from config
     *
     * @return mixed
     */
    public function getUsername()
    {
        return Mage::getStoreConfig('fyndiq/fyndiq_group/username');
    }

    /**
     * Get APItoken from config
     *
     * @return mixed
     */
    public function getAPIToken()
    {
        return Mage::getStoreConfig('fyndiq/fyndiq_group/apikey');
    }
}