<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Sales_Order_View extends Mage_Adminhtml_Block_Sales_Order_View
{
    public function  __construct()
    {
        parent::__construct();

        error_log($this->getRequest()->getParam('order_id'));

        if ($this->isFyndiqOrder($this->getRequest()->getParam('order_id'))) {
            $this->_addButton('fyndiq_delivery_note', array(
                'label' => Mage::helper('fyndiq_fyndiq')->__('Fyndiq Delivery Note'),
                'onclick' => 'setLocation(\' ' . $this->getDeliveryNoteURL() . '\')',
                'class' => 'download',
            ));
        }
    }

    protected function isFyndiqOrder($orderId)
    {
        $order = Mage::getModel('sales/order')
            ->load($orderId);
        return !empty($order->getData('fyndiq_order_id'));
    }

    protected function getDeliveryNoteURL()
    {
        return $this->getUrl('adminhtml/fyndiq/getDeliveryNote/order_id/' . $this->getRequest()->getParam('order_id'));
    }
}
