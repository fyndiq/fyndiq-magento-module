<?php

$installer2 = Mage::getResourceModel('catalog/setup', 'catalog_setup');

$resource = Mage::getSingleton('core/resource');

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

$productIds = Mage::getResourceModel('catalog/product_collection')
    ->getAllIds();

//Now create an array of attribute_code => values
$attributeData = array($attrCode => 0);

//Set the store to affect. I used admin to change all default values
$storeId = Mage_Core_Model_App::ADMIN_STORE_ID;

//Now Update the attribute for the given products.
Mage::getSingleton('catalog/product_action')
    ->updateAttributes($productIds, $attributeData, $storeId);

// Migrate products
$productTableName = Mage::getConfig()->getTablePrefix()."fyndiq_products";
if($installer->tableExists($productTableName)) {
    $readConnection = $resource->getConnection('core_read');
    $query = 'SELECT * FROM ' . $productTableName;
    $products = $readConnection->fetchAll($query);
    $productModel = Mage::getModel('catalog/product');
    foreach ($products as $productRow) {
        if (isset($productRow['store_id']) && $productRow['store_id'] != 0) {
            $product = $productModel
                ->setCurrentStore($productRow['store_id'])
                ->load($productRow['product_id']);
        } else {
            $product = $productModel
                ->load($productRow['product_id']);
        }
        if ($product) {
            $product->setData('fyndiq_exported', Fyndiq_Fyndiq_Model_Attribute_Exported::PRODUCT_EXPORTED)
                ->getResource()
                ->saveAttribute($product, 'fyndiq_exported');
        }
    }
    $sql = 'DROP TABLE IF EXISTS ' . $productTableName;
    $installer2->run($sql);
}
$installer2->endSetup();

// Add fyndiq_order_id
require_once('app/Mage.php');
Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));

$installerOrder = new Mage_Sales_Model_Mysql4_Setup('sales_setup');
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


// Migrate orders
$orderTableName = Mage::getConfig()->getTablePrefix()."fyndiq_orders";
if($installer->tableExists($orderTableName)) {
    $readConnection = $resource->getConnection('core_read');
    $query = 'SELECT * FROM ' . $orderTableName;
    $orders = $readConnection->fetchAll($query);
    $orderModel = Mage::getModel('sales/order');
    foreach ($orders as $orderRow) {
        $order = $orderModel->load($orderRow['order_id']);
        if ($order) {
            $order->setData('fyndiq_order_id', $orderRow['fyndiq_orderid']);
            $order->save();
        }
    }
    $sql = 'DROP TABLE IF EXISTS ' . $orderTableName;
    $installerOrder->run($sql);
}
$installerOrder->endSetup();
