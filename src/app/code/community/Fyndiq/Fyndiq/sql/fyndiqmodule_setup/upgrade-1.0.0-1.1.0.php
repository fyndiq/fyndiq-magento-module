<?php

$installer2 = Mage::getResourceModel('catalog/setup', 'catalog_setup');

$installer2->startSetup();


// Add new Attribute group
$attrGroupName = 'Fyndiq';
$entityTypeId = $installer2->getEntityTypeId('catalog_product');
$attributeSetId = $installer2->getDefaultAttributeSetId($entityTypeId);
$installer2->addAttributeGroup($entityTypeId, $attributeSetId, $attrGroupName, 100);
$attributeGroupId = $installer2->getAttributeGroupId($entityTypeId, $attributeSetId, $attrGroupName);


$attrCode = 'fyndiq_exported';

$attrLabel = 'Fyndiq Exported';
$attrNote = 'Show if product is exported or not';

$installer2->addAttribute(
    'catalog_product',
    $attrCode,
    array(
    'type'          => 'int',
    'input'         => 'select',
    'label'         => $attrLabel,
    'group'         => $attrGroupName,
    'required'      => false,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'searchable'    => true,
    'source'        => 'fyndiq/attribute_exported',
    'filterable_in_search' => true,
    'sort_order'    => 1, // Place just below SKU (4)
    'default'       => '0'
    )
);

$attrCode2 = 'fyndiq_price';
$attrLabel2 = 'Fyndiq Price';

$installer2->addAttribute(
    'catalog_product',
    $attrCode2,
    array(
    'type'          => 'int',
    'input'         => 'price',
    'label'         => $attrLabel2,
    'group'         => $attrGroupName,
    'required'      => false,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'searchable'    => true,
    'filterable_in_search' => true,
    'sort_order'    => 2, // Place just below SKU (4)
    'default'       => '0'
    )
);

$attrCode3 = 'fyndiq_state';

$attrLabel3 = 'Fyndiq State';
$attrNote = 'What is the status of the product';

$installer2->addAttribute(
    'catalog_product',
    $attrCode3,
    array(
    'type'          => 'int',
    'input'         => 'select',
    'label'         => $attrLabel3,
    'group'         => $attrGroupName,
    'required'      => false,
    'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
    'searchable'    => true,
    'source'        => 'fyndiq/attribute_state',
    'filterable_in_search' => false,
    'visible_on_front' => false,
    'visible_in_advanced_search'  => false,
    'is_html_allowed_on_front' => false,
    'is_visible'    => 0,
    'visible'       => false,
    'sort_order'    => 1, // Place just below SKU (4)
    'default'       => '0'
    )
);

$productCollection = Mage::getModel('catalog/product')->getCollection();

foreach($productCollection as $product)
{
    $product = Mage::getModel('catalog/product')
                   ->load($product->getEntityId());
    $product->setData($attrCode, 0)
            ->getResource()
            ->saveAttribute($product, $attrCode);
}

$installer2->endSetup();