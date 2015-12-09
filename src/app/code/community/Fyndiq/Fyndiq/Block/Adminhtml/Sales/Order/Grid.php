<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Sales_Order_Grid extends Mage_Adminhtml_Block_Sales_Order_Grid
{
    protected function _prepareMassaction()
    {
        $result =  parent::_prepareMassaction();

        $this->getMassactionBlock()->addItem(
            'export',
            array(
                'label'=> __('Download Fyndiq Delivery Notes'),
                'url'  => $this->getUrl('adminhtml/fyndiq/getDeliveryNotes')
            )
        );
        return $result;
    }

    public function setCollection($collection)
    {
        $collection->getSelect()->join(
            array('order_item' => 'sales_flat_order'),
            'order_item.entity_id = main_table.entity_id',
            array('fyndiq_order_id' => 'fyndiq_order_id'),
            null,
            'left'
        );
        parent::setCollection($collection);
    }

    protected function _prepareColumns()
    {
        $this->addColumnAfter(
            'fyndiq_order_id',
            array(
                'header'=> Mage::helper('catalog')->__('Fyndiq'),
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
