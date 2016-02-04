<?php
class Fyndiq_Fyndiq_Adminhtml_FyndiqordergridController extends Mage_Adminhtml_Controller_Action
{

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('sales/fyndiqpordergrid');
    }

    public function indexAction()
    {
        $this->_title($this->__('Sales'))->_title($this->__('Orders'));
        $this->loadLayout();
        $this->_setActiveMenu('sales/fyndiqordergrid');
        $this->_addContent($this->getLayout()->createBlock('fyndiq_fyndiq/adminhtml_fyndiq_order'));
        $this->renderLayout();
    }

    public function gridAction()
    {
        $this->loadLayout();
        $this->getResponse()->setBody(
            $this->getLayout()->createBlock('fyndiq_fyndiq/adminhtml_fyndiq_order_grid')->toHtml()
        );
    }

    public function editAction()
    {
        $productId  = (int) $this->getRequest()->getParam('id');
        $this->_redirect('adminhtml/sales_order/edit/id/' . $productId);
    }
}
