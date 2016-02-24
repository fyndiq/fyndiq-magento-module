<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Grid extends Mage_Adminhtml_Block_Catalog_Product_Grid
{

    public function setCollection($collection)
    {
        $storeId = $this->getStoreId();
        /* @var $collection Mage_Catalog_Model_Resource_Product_Collection */
        $collection->addStoreFilter($storeId);
        if (!isset($this->_joinAttributes['fyndiq_exported'])) {
            $collection->joinAttribute(
                'fyndiq_exported',
                'catalog_product/fyndiq_exported',
                'entity_id',
                null,
                'left',
                $storeId
            );
        } else {
            $collection->addAttributeToSelect('fyndiq_exported');
        }
        parent::setCollection($collection);
    }

    protected function _prepareColumns()
    {
        $this->addColumnAfter(
            'fyndiq_status',
            array(
                'header'=> Mage::helper('fyndiq_fyndiq')->__('Fyndiq'),
                'type' => 'options',
                'index' => 'fyndiq_exported',
                'sortable' => true,
                'align'   => 'right',
                'width' => '80px',
                'options' => array(
                    Fyndiq_Fyndiq_Model_Export::VALUE_YES => Mage::helper('eav')->__('Yes'),
                    Fyndiq_Fyndiq_Model_Export::VALUE_NO => Mage::helper('eav')->__('No'),
                ),
            ),
            'status'
        );
        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $result = parent::_prepareMassaction();

        $this->getMassactionBlock()->addItem(
            'export',
            array(
                'label'=> Mage::helper('fyndiq_fyndiq')->__('Export to Fyndiq'),
                'url' => $this->getUrl('adminhtml/fyndiq/exportProducts', array('_current'=>true))
            )
        );

        $this->getMassactionBlock()->addItem(
            'remove',
            array(
                'label'=> Mage::helper('fyndiq_fyndiq')->__('Remove from Fyndiq'),
                'url' => $this->getUrl('adminhtml/fyndiq/removeProducts', array('_current'=>true))
            )
        );
        return $result;
    }
}
