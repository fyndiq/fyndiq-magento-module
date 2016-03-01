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
        $columnName = 'name_sv';
        $collection = Mage::getModel('catalog/category')->getCollection();
        $collection->addAttributeToSelect('*');
        // TODO: Fixme
        // $collection->getSelect()->joinLeft(
        //     array('fyndiq_category' => 'fyndiq_fyndiq_category'),
        //     'fyndiq_category_id = fyndiq_category.id',
        //     array($columnName)
        // );
        // error_log($collection->getSelect());
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
          'index'     => 'fyndiq_category_id',
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
                        'base'=>'*/*/edit',
                        'params'=>array('store'=>$this->getRequest()->getParam('store'))
                    ),
                    'field'   => 'id'
                )
            ),
            'filter'    => false,
            'sortable'  => false,
            'index'     => 'stores',
        ));
        return parent::_prepareColumns();
    }

    public function getMultipleRows() {
        return false;
    }
}
