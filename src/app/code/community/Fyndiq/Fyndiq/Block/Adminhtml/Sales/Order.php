<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Sales_Order extends Mage_Adminhtml_Block_Sales_Order
{
    public function __construct()
    {
        parent::__construct();

        $this->_addButton('fyndiq_import_orders', array(
            'label' => Mage::helper('fyndiq_fyndiq')->__('Import Fyndiq Orders'),
            'onclick' => 'setLocation(\' '  . $this->getImportFyndiqOrdersURL() . '\')',
            'class' => 'add',
        ));
    }

    public function getImportFyndiqOrdersURL()
    {
        return $this->getUrl('adminhtml/fyndiq/importFyndiqOrders');
    }
}
