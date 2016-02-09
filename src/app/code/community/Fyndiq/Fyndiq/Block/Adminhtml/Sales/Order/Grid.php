<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Sales_Order_Grid extends Mage_Adminhtml_Block_Sales_Order_Grid
{

    private $isEnabled = null;

    protected function isEnabled($storeId)
    {
        if (is_null($this->isEnabled)) {
            $this->isEnabled = Mage::getModel('fyndiq/config')
                ->get('fyndiq/troubleshooting/fyndiq_grid', $storeId) == 1;
        }
        return $this->isEnabled;
    }

    protected function _getStore()
    {
        $storeId = (int) $this->getRequest()->getParam('store', 0);
        return Mage::app()->getStore($storeId);
    }

    protected function getStoreId()
    {
        $store = $this->_getStore();
        return $store->getId();
    }

    protected function _prepareMassaction()
    {
        $result =  parent::_prepareMassaction();

        if (!$this->isEnabled($this->getStoreId())) {
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
                    'label'=> Mage::helper('fyndiq_fyndiq')->__('Mark as "handled" on Fyndiq'),
                    'url'  => $this->getUrl('adminhtml/fyndiq/handledFyndiqOrders')
                )
            );
            $this->getMassactionBlock()->addItem(
                'unhandle_orders',
                array(
                    'label'=> Mage::helper('fyndiq_fyndiq')->__('Mark as "not handled" on Fyndiq'),
                    'url'  => $this->getUrl('adminhtml/fyndiq/unhandledFyndiqOrders')
                )
            );
        }

        return $result;
    }

    public function setCollection($collection)
    {
        if (!$this->isEnabled($this->getStoreId())) {
            $collection->getSelect()->columns(
                array(
                    'fyndiq_order_id' => '(SELECT fyndiq_order_id FROM sales_flat_order sfo WHERE sfo.entity_id = main_table.entity_id)'
                )
            );
        }
        parent::setCollection($collection);
    }

    protected function _prepareColumns()
    {
        if (!$this->isEnabled($this->getStoreId())) {
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
        }
        return parent::_prepareColumns();
    }
}
