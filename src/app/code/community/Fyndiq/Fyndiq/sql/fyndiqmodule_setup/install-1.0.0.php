<?php
$installer = $this;

$installer->startSetup();
$connection = $installer->getConnection();

// fyndiq_product
$tableName = $installer->getTable('fyndiq/product');
if (!$installer->tableExists($tableName)) {
    $productsTable = $connection->newTable($tableName)
        ->addColumn(
            'id',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true,
            ),
            'Id'
        )
        ->addColumn(
            'product_id',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'nullable' => false,
            ),
            'Magento Product'
        )
        ->addColumn(
            'exported_price_percentage',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'nullable' => false,
            ),
            'Exported price percentage'
        )
        ->addColumn(
            'state',
            Varien_Db_Ddl_Table::TYPE_VARCHAR,
            null,
            array(
                'nullable' => true,
            ),
            'Fyndiq State'
        );

    $connection->createTable($productsTable);
}

// fyndiq_order
$tableName = $installer->getTable('fyndiq/order');
if (!$installer->tableExists($tableName)) {
    $table = $connection->newTable($tableName)
        ->addColumn(
            'id',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true,
            ),
            'Id'
        )
        ->addColumn(
            'order_id',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'nullable' => false,
            ),
            'Magento Order'
        )
        ->addColumn(
            'fyndiq_orderid',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'nullable' => false,
            ),
            'Fyndiq Order'
        );

    $connection->createTable($table);
}

// fyndiq_setting
$tableName = $installer->getTable('fyndiq/setting');
if (!$installer->tableExists($tableName)) {
    $table = $connection->newTable($tableName)
        ->addColumn(
            'id',
            Varien_Db_Ddl_Table::TYPE_INTEGER,
            null,
            array(
                'identity' => true,
                'unsigned' => true,
                'nullable' => false,
                'primary' => true,
            ),
            'Id'
        )
        ->addColumn(
            'key',
            Varien_Db_Ddl_Table::TYPE_TEXT,
            null,
            array(
                'nullable' => false,
            ),
            'settings key'
        )
        ->addColumn(
            'value',
            Varien_Db_Ddl_Table::TYPE_TEXT,
            null,
            array(
                'nullable' => false,
            ),
            'setting value'
        );

    $connection->createTable($table);
}

$installer->endSetup();
