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
}
