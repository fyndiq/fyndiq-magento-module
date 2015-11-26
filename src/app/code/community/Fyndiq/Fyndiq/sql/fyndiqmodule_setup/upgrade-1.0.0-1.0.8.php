<?php
$installer = $this;
$installer->startSetup();
$connection = $installer->getConnection();

$connection->addColumn(
    $installer->getTable('fyndiq/product'),
    'store_id',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'nullable' => false,
        'default' => 0,
        'comment' => 'Store id'
    )
);

$installer->endSetup();
