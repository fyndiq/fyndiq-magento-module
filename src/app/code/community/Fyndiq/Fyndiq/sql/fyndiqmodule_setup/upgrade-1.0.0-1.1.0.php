<?php

$installer2 = Mage::getResourceModel('catalog/setup', 'catalog_setup');

$installer2->startSetup();

$attrCode = 'fyndiq_exported';
$attrGroupName = 'Fyndiq';
$attrLabel = 'Fyndiq Exported';
$attrNote = 'Show if product is exported or not';

$installer2->addAttribute(
    'catalog_product',
    $attrCode,
    array(
    'type'          => 'int',
    'input'         => 'select',
    'label'         => $attrLabel,
    'required'      => false,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'searchable'    => true,
    'source'        => 'fyndiq/attribute_exported',
    'filterable_in_search' => true,
    'sort_order'    => 5, // Place just below SKU (4)
    'default'       => '0'
    )
);

$attrData = array(
    $attrCode => '0',
);
$storeId = 0;
$productIds = Mage::getModel('catalog/product')->getCollection()->getAllIds();
Mage::getModel("catalog/product_action")->updateAttributes(
    $productIds,
    $attrData,
    $storeId
);

$installer2->endSetup();
