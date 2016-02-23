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

$installer->endSetup();
