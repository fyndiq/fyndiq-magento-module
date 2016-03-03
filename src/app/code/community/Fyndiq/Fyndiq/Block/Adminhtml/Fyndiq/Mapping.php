<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'fyndiq_fyndiq';
        $this->_controller = 'adminhtml_fyndiq_mapping';
        $this->_headerText = Mage::helper('fyndiq_fyndiq')->__('Category Mapping');

        parent::__construct();
        $this->_removeButton('add');
        $this->_addButton('fyndiq_import_orders', array(
            'label' => Mage::helper('fyndiq_fyndiq')->__('Update Fyndiq Cateogories'),
            'onclick' => 'this.disabled = true; setLocation(\' '  . $this->getUpdateFyndiqCategoriesURL() . '\');',
            'class' => 'add',
        ));
    }

    public function getUpdateFyndiqCategoriesURL()
    {
        return $this->getUrl('adminhtml/fyndiqcategorygrid/updateCategories');
    }
}
