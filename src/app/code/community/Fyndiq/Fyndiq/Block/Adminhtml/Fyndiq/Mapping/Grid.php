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
        $collection->addAttributeToSelect(array('name'));
        $this->setCollection($collection);
        return parent::_prepareCollection();
    }

    protected function _prepareColumns()
    {
        $this->addColumn('entity_id', array(
          'header'    => Mage::helper('fyndiq_fyndiq')->__('ID'),
          'align'     =>'right',
          'width'     => '10px',
          'index'     => 'entity_id',
        ));
        $this->addColumn('parent_id', array(
          'header'    => Mage::helper('fyndiq_fyndiq')->__('Parent ID'),
          'align'     =>'right',
          'width'     => '10px',
          'index'     => 'parent_id',
        ));

        $this->addColumn('name', array(
          'header'    => Mage::helper('fyndiq_fyndiq')->__('Name'),
          'align'     =>'left',
          'index'     => 'name',
        ));
        $this->addColumn('fyndiq_category_id', array(
          'header'    => Mage::helper('fyndiq_fyndiq')->__('Fyndiq Category'),
          'align'     =>'left',
          'width'     => '350px',
        ));
        return parent::_prepareColumns();
    }

    public function getMultipleRows() {
        return false;
    }
}
