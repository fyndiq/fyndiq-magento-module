<?php

$installer = $this;

$installer->startSetup();

$table = $installer->getConnection()
    ->newTable($installer->getTable('fyndiq/order'))
    ->addColumn('id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'identity'  => true,
            'unsigned'  => true,
            'nullable'  => false,
            'primary'   => true,
        ), 'Id')
    ->addColumn('order_id', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
        ), 'Magento Order')
    ->addColumn('fyndiq_orderid', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
        ), 'Fyndiq Order')
    ->addColumn('fyndiq_stock', Varien_Db_Ddl_Table::TYPE_INTEGER, null, array(
            'nullable'  => false,
        ), 'amount of stock been sent to Fyndiq last time.');
$installer->getConnection()->createTable($table);

$installer->endSetup();