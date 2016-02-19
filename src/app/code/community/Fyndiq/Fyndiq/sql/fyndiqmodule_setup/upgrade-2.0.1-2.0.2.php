<?php

$installer = $this;
$installer->startSetup();

$installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'fyndiq_category_id',  array(
    'group'             => 'Fyndiq',
    'type'              => 'int',
    'input'             => 'text',
    'label'             => 'Fyndiq Category Id',
    'input'             => 'text',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'           => false,
    'required'          => false,
    'user_defined'      => false,
    'visible_on_front'  => true,
    'default'           => 0
));

$installer->addAttribute(Mage_Catalog_Model_Category::ENTITY, 'fyndiq_category_name',  array(
    'group'             => 'Fyndiq',
    'type'              => 'text',
    'input'             => 'text',
    'label'             => 'Fyndiq Category Name',
    'input'             => 'text',
    'global'            => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'visible'           => true,
    'required'          => false,
    'user_defined'      => true,
    'visible_on_front'  => false,
    'default'           => ''
));

$installer->endSetup();
