<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Product extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'fyndiq_fyndiq';
        $this->_controller = 'adminhtml_fyndiq_product';
        $this->_headerText = Mage::helper('fyndiq_fyndiq')->__('Products');

        parent::__construct();
        $this->_removeButton('add');
    }
}
