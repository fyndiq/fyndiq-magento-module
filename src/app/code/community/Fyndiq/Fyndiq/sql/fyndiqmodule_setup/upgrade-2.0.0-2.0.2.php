<?php

$installer = $this;
$installer->startSetup();

// Add the FyndiqCategoryId attribute
$installer->removeAttribute(Mage_Catalog_Model_Category::ENTITY, 'fyndiq_category_id');
$installer->addAttribute(
    Mage_Catalog_Model_Category::ENTITY,
    'fyndiq_category_id',
    array(
        'group'             => 'General Information',
        'type'              => 'int',
        'label'             => 'Fyndiq Category',
        'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'visible'           => false,
        'required'          => false,
        'user_defined'      => false,
        'visible_on_front'  => true,
        'is_visible'        => 0,
        'default'           => 0,
    )
);
// Make the attribute non visible since setting that in addAttribute does not work
$installer->updateAttribute(Mage_Catalog_Model_Category::ENTITY, 'fyndiq_category_id', 'is_visible', '0');

// Add the categories table
$tableName = $this->getTable('fyndiq/category');
if ($installer->getConnection()->isTableExists($tableName) != true) {

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
        'name_sv',
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

    $table->setOption('type', 'InnoDB');
    $table->setOption('charset', 'utf8');

    $this->getConnection()->createTable($table);
}

$this->endSetup();
