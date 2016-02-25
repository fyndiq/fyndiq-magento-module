<?php

class Fyndiq_Fyndiq_Adminhtml_FyndiqcategorygridController extends Mage_Adminhtml_Controller_Action
{

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/fyndiqcategorygrid');
    }

    public function indexAction()
    {
        $this->_title($this->__('Category mapping'));
        $this->loadLayout();
        $this->_setActiveMenu('catalog/fyndiqcategorygrid');
        $this->_addContent($this->getLayout()->createBlock('fyndiq_fyndiq/adminhtml_fyndiq_mapping'));
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('fyndiq_fyndiq/adminhtml_fyndiq_mapping_grid')->toHtml()
        );
    }
}
