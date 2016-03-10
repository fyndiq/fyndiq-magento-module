<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        parent::__construct();
        $this->setId('mappingGrid');
        $this->setDefaultSort('id');
        $this->setDefaultDir('ASC');
        $this->setSaveParametersInSession(true);
    }

    protected function _prepareCollection()
    {
        $collection = Mage::getModel('catalog/category')->getCollection();
        $collection->addAttributeToSelect('*');
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('entity_id', array(
            'header'    => Mage::helper('fyndiq_fyndiq')->__('ID'),
            'align'     => 'right',
            'width'     => '10px',
            'index'     => 'entity_id',
        ));
        $this->addColumn('name', array(
            'header'    => Mage::helper('fyndiq_fyndiq')->__('Name'),
            'align'     => 'left',
            'width'     => '50%',
            'index'     => 'entity_id',
            'renderer'  => 'Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Renderer_Category',
            'filter'    => false,
        ));
        $this->addColumn('fyndiq_category_id', array(
            'header'    => Mage::helper('fyndiq_fyndiq')->__('Fyndiq Category'),
            'align'     => 'left',
            'width'     => '50%',
            'index'     => 'fyndiq_category_id',
            'renderer'  => 'Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Renderer_FyndiqCategory',
            'filter'    => false,
        ));
        $this->addColumn('action', array(
            'header'    => Mage::helper('catalog')->__('Action'),
            'width'     => '50px',
            'type'      => 'action',
            'getter'    => 'getId',
            'actions'   => array(
                array(
                    'caption' => Mage::helper('catalog')->__('Edit'),
                    'url'     => array(
                        'base' =>'*/*/edit',
                        'params' =>array('store'=>$this->getRequest()->getParam('store'))
                    ),
                    'field' => 'id'
                )
            ),
            'filter'    => false,
            'sortable'  => false,
            'index'     => 'stores',
        ));
        return parent::_prepareColumns();
    }

    public function getMultipleRows()
    {
        return false;
    }
}
