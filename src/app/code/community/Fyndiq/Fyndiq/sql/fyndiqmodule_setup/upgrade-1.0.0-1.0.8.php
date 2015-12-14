<?php
$installer = $this;
$installer->startSetup();
$connection = $installer->getConnection();

$tableName = Mage::getConfig()->getTablePrefix()."fyndiq_products";
if ($installer->tableExists($tableName)) {
    $connection->addColumn(
        $tableName,
        'store_id',
        array(
            'type' => Varien_Db_Ddl_Table::TYPE_INTEGER,
            'nullable' => false,
            'default' => 0,
            'comment' => 'Store id'
        )
    );
}
$installer->endSetup();
