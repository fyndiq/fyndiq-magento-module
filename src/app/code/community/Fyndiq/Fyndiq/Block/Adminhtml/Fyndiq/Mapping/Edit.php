<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_blockGroup = 'fyndiq_fyndiq';
        $this->_controller = 'adminhtml_fyndiq_mapping';

        $this->_mode = 'edit';

        $this->_headerText =  $this->__('Category Mapping');
        $this->_removeButton('delete');
    }
}
