<?php

class Fyndiq_Fyndiq_Block_Adminhtml_Fyndiq_Mapping_Grid extends Mage_Adminhtml_Block_Widget_Grid
{

    public function __construct()
    {
        error_log('WERKS');
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
          'align'     =>'right',
          'width'     => '10px',
          'index'     => 'entity_id',
        ));
        $this->addColumn('parent_id', array(
          'header'    => Mage::helper('fyndiq_fyndiq')->__('ID'),
          'align'     =>'right',
          'width'     => '10px',
          'index'     => 'parent_id',
        ));

        $this->addColumn('name', array(
          'header'    => Mage::helper('fyndiq_fyndiq')->__('Name'),
          'align'     =>'left',
          'index'     => 'name',
          'width'     => '50px',
        ));
        $this->addColumn('association', array(
          'header'    => Mage::helper('fyndiq_fyndiq')->__('Fyndiq Category'),
          'align'     =>'left',
          'width'     => '50px',
        ));
        return parent::_prepareColumns();
    }

    public function getMultipleRows() {
        return false;
    }
}
