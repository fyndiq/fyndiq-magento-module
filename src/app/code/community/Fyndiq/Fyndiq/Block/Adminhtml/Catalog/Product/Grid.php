<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Catalog_Product_Grid extends Mage_Adminhtml_Block_Catalog_Product_Grid
{
    /* Overwritten to be able to add custom columns to the product grid. Normally
     * one would overwrite the function _prepareCollection, but it won't work because
     * you have to call parent::_prepareCollection() first to get the collection.
     *
     * But since parent::_prepareCollection() also finishes the collection, the
     * joins and attributes to select added in the overwritten _prepareCollection()
     * are 'forgotten'.
     *
     * By overwriting setCollection (which is called in parent::_prepareCollection()),
     * we are able to add the join and/or attribute select in a proper way.
     *
     */
    private $isEnabled = null;

    protected function isEnabled($storeId)
    {
        if (is_null($this->isEnabled)) {
            $this->isEnabled = Mage::getModel('fyndiq/config')
                ->get('fyndiq/troubleshooting/fyndiq_grid', $storeId) == 1;
        }
        return $this->isEnabled;
    }

    protected function getStoreId()
    {
        $store = $this->_getStore();
        return $store->getId();
    }

    public function setCollection($collection)
    {
        $storeId = $this->getStoreId();
        if (!$this->isEnabled($storeId)) {
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
        }
        parent::setCollection($collection);
    }

    protected function _prepareColumns()
    {
        if (!$this->isEnabled($this->getStoreId())) {
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
        }
        return parent::_prepareColumns();
    }

    protected function _prepareMassaction()
    {
        $result = parent::_prepareMassaction();

        if (!$this->isEnabled($this->getStoreId())) {
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
        }
        return $result;
    }
}
