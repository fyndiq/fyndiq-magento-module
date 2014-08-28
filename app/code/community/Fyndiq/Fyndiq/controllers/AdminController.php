<?php

/**
 * Created by PhpStorm.
 * User: confact
 * Date: 18/08/14
 * Time: 09:50
 */
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

        //create a text block with the name of "example-block"
        $block = $this->getLayout()
            ->createBlock('Fyndiq_Fyndiq_Block_Exportproducts', 'fyndiq.exportproducts')
            ->setTemplate('fyndiq/exportproducts.phtml');

        $logo = Mage::getBaseUrl(
                Mage_Core_Model_Store::URL_TYPE_WEB
            ) . "app/code/community/Fyndiq/Fyndiq/frontend/images/logo.png";

        $this->getLayout()->getBlock('content')->append($block);

        $this->renderLayout();
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