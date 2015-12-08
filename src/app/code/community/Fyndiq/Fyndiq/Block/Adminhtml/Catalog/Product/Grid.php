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
        /* @var $collection Mage_Catalog_Model_Resource_Product_Collection */

        $store = $this->_getStore();
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
        $this->addColumn(
            'fyndiq_status',
            array(
                'header'=> Mage::helper('catalog')->__('Fyndiq Exported'),
                'type' => 'options',
                'index' => 'fyndiq_exported',
                'sortable' => true,
                'align'   => 'right',
                'width' => '80px',
                'options' => $this->_getAttributeOptions('fyndiq_exported'),
            )
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

        $this->getMassactionBlock()->addItem(
            'export',
            array(
            'label'=> __('Export to Fyndiq'),
            'url'  => $this->getUrl('fyndiq/admin/exportProducts')
            )
        );

        $this->getMassactionBlock()->addItem(
            'remove',
            array(
            'label'=> __('Remove from Fyndiq'),
            'url'  => $this->getUrl('fyndiq/admin/removeProducts')
            )
        );

        return parent::_prepareMassaction();
    }
}
