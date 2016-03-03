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

    public function editAction()
    {
        $categoryId  = (int) $this->getRequest()->getParam('id');

        $category = Mage::getModel('catalog/category')
          ->setStoreId($this->getRequest()->getParam('store', 0))
          ->load($categoryId);

        $this->_title($category->getName());

        // Instantiate the form container.
        $mappingEditBlock = $this->getLayout()->createBlock(
            'fyndiq_fyndiq/adminhtml_fyndiq_mapping_edit'
        );

        // Add the form container as the only item on this page.
        $this->loadLayout()
            ->_addContent($mappingEditBlock)
            ->renderLayout();
    }

    public function saveAction()
    {

        $categoryId  = (int)$this->getRequest()->getParam('id');
        $storeId = (int)$this->getRequest()->getParam('store', 0);
        $fyndiqCategoryId = (int)$this->getRequest()->getPost('fyndiq_category_id');

        $categorySingleton = Mage::getSingleton('catalog/category');
        $categorySingleton->setId($categoryId);
        $categorySingleton->setFyndiqCategoryId($fyndiqCategoryId);
        $categorySingleton->setStoreId($storeId);

        Mage::getModel('catalog/category')->getResource()
            ->saveAttribute($categorySingleton, 'fyndiq_category_id');

        $this->_redirect('adminhtml/fyndiqcategorygrid/');
    }
}
