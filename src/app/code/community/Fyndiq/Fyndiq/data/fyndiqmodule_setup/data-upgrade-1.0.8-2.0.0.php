<?php

$installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
$resource = Mage::getSingleton('core/resource');

$productIds = Mage::getResourceModel('catalog/product_collection')
    ->getAllIds();

//Now create an array of attribute_code => values
$attributeData = array('fyndiq_exported' => 0);

//Set the store to affect. I used admin to change all default values
$storeId = Mage_Core_Model_App::ADMIN_STORE_ID;

//Now Update the attribute for the given products.
Mage::getSingleton('catalog/product_action')
    ->updateAttributes($productIds, $attributeData, $storeId);

// Migrate products
$productTableName = Mage::getConfig()->getTablePrefix()."fyndiq_products";
if ($installer->tableExists($productTableName)) {
    $readConnection = $resource->getConnection('core_read');
    $query = 'SELECT * FROM ' . $productTableName;
    $products = $readConnection->fetchAll($query);
    $productModel = Mage::getModel('catalog/product');
    foreach ($products as $productRow) {
        $storeId = isset($productRow['store_id']) ? intval($productRow['store_id']) : Mage_Core_Model_App::ADMIN_STORE_ID;
        if (isset($productRow['store_id']) && $productRow['store_id'] != 0) {
            $product = $productModel
                ->setCurrentStore($storeId)
                ->load($productRow['product_id']);
        } else {
            $product = $productModel
                ->load($productRow['product_id']);
        }
        if ($product) {
            $product->setStoreId($storeId)
                ->setData('fyndiq_exported', 1)
                ->getResource()
                ->saveAttribute($product, 'fyndiq_exported');
        }
    }
}

// Add fyndiq_order_id
Mage::app()->setCurrentStore(Mage::getModel('core/store')->load(Mage_Core_Model_App::ADMIN_STORE_ID));

// Migrate orders
$orderTableName = Mage::getConfig()->getTablePrefix() . 'fyndiq_orders';
if ($installer->tableExists($orderTableName)) {
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
}
