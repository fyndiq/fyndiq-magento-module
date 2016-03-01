<?php

$installer = $this;
$installer->startSetup();

$installer->removeAttribute(Mage_Catalog_Model_Category::ENTITY, 'fyndiq_category_id');

$installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'fyndiq_category_id',  array(
    'group'             => 'Fyndiq',
    'type'              => 'int',
    'input'             => 'text',
    'label'             => 'Fyndiq Category',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'           => false,
    'required'          => false,
    'user_defined'      => false,
    'visible_on_front'  => true,
    'is_visible'        => false,
    'default'           => 0,
));

$tableName = $this->getTable('fyndiq/category');

if ($installer->getConnection()->isTableExists($tableName) != true) {

    // Add tree table
    $table = new Varien_Db_Ddl_Table();

    $table->setName($tableName);

    $table->addColumn(
        'id',
        Varien_Db_Ddl_Table::TYPE_INTEGER,
        10,
        array(
            'auto_increment' => false,
            'unsigned' => true,
            'nullable'=> false,
            'primary' => true
        )
    );

    $table->addColumn(
        'name_se',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        255,
        array(
            'nullable' => false,
        )
    );


    $table->addColumn(
        'name_de',
        Varien_Db_Ddl_Table::TYPE_VARCHAR,
        255,
        array(
            'nullable' => false,
        )
    );

    /**
     * These two important lines are often missed.
     */
    $table->setOption('type', 'InnoDB');
    $table->setOption('charset', 'utf8');

    /**
     * Create the table!
     */
    $this->getConnection()->createTable($table);
}

$this->endSetup();
