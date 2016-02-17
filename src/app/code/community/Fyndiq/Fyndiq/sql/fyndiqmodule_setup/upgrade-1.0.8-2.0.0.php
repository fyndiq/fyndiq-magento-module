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
        'source'        => 'eav/entity_attribute_source_boolean',
        'filterable_in_search' => true,
        'sort_order'    => 3, // Place last in fyndiq tab
        'default'       => 0,
        'used_in_product_listing' => true,
    )
);

$attrCode = 'fyndiq_title';
$attrLabel = 'Fyndiq Product Title';

$installer2->addAttribute(
    'catalog_product',
    $attrCode,
    array(
        'type'          => 'text',
        'input'         => 'text',
        'label'         => $attrLabel,
        'group'         => $attrGroupName,
        'required'      => false,
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'searchable'    => false,
        'filterable_in_search' => false,
        'sort_order'    => 1, // Place first in Fyndiq tab
        'default'       => '',
    )
);

$attrCode = 'fyndiq_description';
$attrLabel = 'Fyndiq Product Description';

$installer2->addAttribute(
    'catalog_product',
    $attrCode,
    array(
        'type'          => 'text',
        'input'         => 'textarea',
        'label'         => $attrLabel,
        'group'         => $attrGroupName,
        'required'      => false,
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'searchable'    => false,
        'filterable_in_search' => false,
        'sort_order'    => 2, // Place after title in Fyndiq tab
        'default'       => ''
    )
);

$installer2->endSetup();

// Add fyndiq_order_id
Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));

$installerOrder = new Mage_Sales_Model_Resource_Setup('sales_setup');
$installerOrder->startSetup();
$installerOrder->addAttribute(
    Mage_Sales_Model_Order::ENTITY,
    'fyndiq_order_id',
    array(
        'type'          => 'int',
        'label'         => 'Fyndiq Order ID',
        'required'      => false,
        'global'        => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'searchable'    => true,
        'filterable'    => true,
        'comparable'    => true,
        'is_visible'    => 1,
        'visible'       => true,
        'default'       => null,
    )
);

$installerOrder->endSetup();
