<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Sales_Order_Grid extends Mage_Adminhtml_Block_Sales_Order_Grid
{
    protected function _prepareMassaction()
    {
        $result =  parent::_prepareMassaction();

        $this->getMassactionBlock()->addItem(
            'export',
            array(
                'label'=> Mage::helper('fyndiq_fyndiq')->__('Download Fyndiq Delivery Notes'),
                'url'  => $this->getUrl('adminhtml/fyndiq/getDeliveryNotes')
            )
        );
        $this->getMassactionBlock()->addItem(
            'handle_orders',
            array(
                'label'=> Mage::helper('fyndiq_fyndiq')->__('Mark as handled on Fyndiq'),
                'url'  => $this->getUrl('adminhtml/fyndiq/handledFyndiqOrders')
            )
        );
        $this->getMassactionBlock()->addItem(
            'unhandle_orders',
            array(
                'label'=> Mage::helper('fyndiq_fyndiq')->__('Mark as not handled on Fyndiq'),
                'url'  => $this->getUrl('adminhtml/fyndiq/unhandledFyndiqOrders')
            )
        );


        return $result;
    }

    public function setCollection($collection)
    {
        $collection->getSelect()->columns(
            array(
                'fyndiq_order_id' => '(SELECT fyndiq_order_id FROM sales_flat_order sfo WHERE sfo.entity_id = main_table.entity_id)'
            )
        );
        parent::setCollection($collection);
    }

    protected function _prepareColumns()
    {
        $this->addColumnAfter(
            'fyndiq_order_id',
            array(
                'header'=> Mage::helper('fyndiq_fyndiq')->__('Fyndiq'),
                'type' => 'text',
                'index' => 'fyndiq_order_id',
                'sortable' => true,
                'align'   => 'right',
                'width' => '50px'
            ),
            'status'
        );

        return parent::_prepareColumns();
    }
}
