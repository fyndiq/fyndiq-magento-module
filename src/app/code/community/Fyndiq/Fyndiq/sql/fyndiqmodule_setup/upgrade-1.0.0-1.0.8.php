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

$connection->addColumn(
    $installer->getTable('fyndiq/order'),
    'status',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
        'nullable' => false,
        'default' => 0,
        'comment' => 'Order status'
    )
);

$connection->addColumn($installer->getTable('fyndiq/order'),
    'data',
    array(
        'type' => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable' => false,
        'default' => '',
        'comment' => 'Order payload'
    )
);

$installer->endSetup();
