<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Order extends Mage_Adminhtml_Block_Widget_Grid_Container
{
    public function __construct()
    {
        $this->_blockGroup = 'fyndiq_fyndiq';
        $this->_controller = 'adminhtml_fyndiq_order';
        $this->_headerText = Mage::helper('fyndiq_fyndiq')->__('Orders');

        parent::__construct();
        $this->_removeButton('add');
        $this->_addButton('fyndiq_import_orders', array(
            'label' => Mage::helper('fyndiq_fyndiq')->__('Import Fyndiq Orders'),
            'onclick' => 'this.disabled = true; setLocation(\' '  . $this->getImportFyndiqOrdersURL() . '\');',
            'class' => 'add',
        ));
    }

    public function getImportFyndiqOrdersURL()
    {
        return $this->getUrl('adminhtml/fyndiq/importFyndiqOrders');
    }
}
