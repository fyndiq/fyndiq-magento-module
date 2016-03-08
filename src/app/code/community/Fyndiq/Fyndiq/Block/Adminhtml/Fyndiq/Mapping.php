<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    /**
     * Grid container constructor
     */
    public function __construct()
    {
        $this->_blockGroup = 'fyndiq_fyndiq';
        $this->_controller = 'adminhtml_fyndiq_mapping';
        $this->_headerText = Mage::helper('fyndiq_fyndiq')->__('Category Mapping');

        parent::__construct();
        $this->_removeButton('add');
        $this->_addButton('fyndiq_import_orders', array(
            'label' => Mage::helper('fyndiq_fyndiq')->__('Update Fyndiq Categories'),
            'onclick' => 'this.disabled = true; setLocation(\' '  . $this->getUpdateFyndiqCategoriesURL() . '\');',
            'class' => 'add',
        ));
    }

    /**
     * getUpdateFyndiqCategoriesURL returns the the URL to the update categories action
     * @return string
     */
    public function getUpdateFyndiqCategoriesURL()
    {
        return $this->getUrl('adminhtml/fyndiqcategorygrid/updateCategories');
    }
}
