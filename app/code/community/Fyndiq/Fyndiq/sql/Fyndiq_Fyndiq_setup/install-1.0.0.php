<?php
$installer = $this;

$installer->startSetup();

$productstable = $installer->getConnection()
    ->newTable($installer->getTable('fyndiq/product'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ), 'Id')
    ->addColumn('product_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
        ), 'Magento Product')
    ->addColumn('exported_qty', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
        ), 'Exported qty')
    ->addColumn('exported_price', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
        ), 'Exported price');

$installer->getConnection()->createTable($productstable);

$installer->endSetup();