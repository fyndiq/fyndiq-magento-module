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
    public function setCollection($collection)
    {
        $store = $this->_getStore();
        /* @var $collection Mage_Catalog_Model_Resource_Product_Collection */
        $collection->addStoreFilter($store->getId());
        if ($store->getId() && !isset($this->_joinAttributes['fyndiq_exported'])) {
            $collection->joinAttribute(
                'fyndiq_exported',
                'catalog_product/fyndiq_exported',
                'entity_id',
                null,
                'left',
                $store->getId()
            );
        } else {
            $collection->addAttributeToSelect('fyndiq_exported');
        }
        parent::setCollection($collection);
    }

    protected function _prepareColumns()
    {
        $store = $this->_getStore();
        $this->addColumnAfter(
            'fyndiq_status',
            array(
                'header'=> Mage::helper('fyndiq_fyndiq')->__('Fyndiq'),
                'type' => 'options',
                'index' => 'fyndiq_exported',
                'sortable' => true,
                'align'   => 'right',
                'width' => '80px',
                'options' => $this->_getAttributeOptions('fyndiq_exported'),
            ),
            'status'
        );

        return parent::_prepareColumns();
    }

    protected function _getAttributeOptions($attribute_code)
    {
        $attribute = Mage::getModel('eav/config')->getAttribute('catalog_product', $attribute_code);
        $options = array();
        foreach ($attribute->getSource()->getAllOptions(true, true) as $option) {
            $options[$option['value']] = $option['label'];
        }
        return $options;
    }

    protected function _prepareMassaction()
    {
        $result = parent::_prepareMassaction();

        $this->getMassactionBlock()->addItem(
            'export',
            array(
                'label'=> Mage::helper('fyndiq_fyndiq')->__('Export to Fyndiq'),
                'url' => $this->getUrl('adminhtml/fyndiq/exportProducts')
            )
        );

        $this->getMassactionBlock()->addItem(
            'remove',
            array(
                'label'=> Mage::helper('fyndiq_fyndiq')->__('Remove from Fyndiq'),
                'url' => $this->getUrl('adminhtml/fyndiq/removeProducts')
            )
        );

        return $result;
    }
}
