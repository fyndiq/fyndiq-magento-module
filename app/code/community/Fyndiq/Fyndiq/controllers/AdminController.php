<?php
/**
 * Created by PhpStorm.
 * User: confact
 * Date: 18/08/14
 * Time: 09:50
 */

class Fyndiq_Fyndiq_AdminController extends Mage_Adminhtml_Controller_Action
{
    public function indexAction() {
        $this->loadLayout();

        //create a text block with the name of "example-block"
        $block = $this->getLayout()
            ->createBlock('core/text', 'example-block')
            ->setText('<h1>This is a text block</h1>');

        $this->_addContent($block);

        $this->renderLayout();
    }

    public function showAction() {

    }

    public function helloAction() {
        echo "HEJ!";
    }

    public function exportproductsAction() {
        $this->loadLayout();

        //create a text block with the name of "example-block"
        //$block = $this->getLayout()
        //    ->createBlock('Mage_Core_Block_Template', 'fyndiq.exportproducts')
        //    ->setTemplate('fyndiq/exportproducts.phtml');

        $logo = Mage::getBaseUrl(Mage_Core_Model_Store::URL_TYPE_WEB) . "app/code/community/Fyndiq/Fyndiq/frontend/images/logo.png";

        $block2 = $this->getLayout()->createBlock('core/text', 'mupp')->setText('<img src="'.$logo.'" />');
        //$block22 = $this->getLayout()->createBlock('core/text', 'mupp')->setText('<p>HAJ</p>');

        $this->_addContent($block2);

        $this->renderLayout();
    }
}