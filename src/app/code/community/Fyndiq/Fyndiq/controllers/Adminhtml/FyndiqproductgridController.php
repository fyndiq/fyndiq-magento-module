<?php
class Fyndiq_Fyndiq_Adminhtml_FyndiqproductgridController extends Mage_Adminhtml_Controller_Action
{

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/fyndiqproductgrid');
    }

    public function indexAction()
    {
        $this->_title($this->__('Products'))->_title($this->__('Export to Fyndiq'));
        $this->loadLayout();
        $this->_setActiveMenu('catalog/fyndiqproductgrid');
        $this->_addContent($this->getLayout()->createBlock('fyndiq_fyndiq/adminhtml_fyndiq_product'));
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('fyndiq_fyndiq/adminhtml_fyndiq_product_grid')->toHtml()
        );
    }

    public function editAction()
    {
        $productId  = (int) $this->getRequest()->getParam('id');
        $this->_redirect('adminhtml/catalog_product/edit/id/' . $productId);
    }
}
