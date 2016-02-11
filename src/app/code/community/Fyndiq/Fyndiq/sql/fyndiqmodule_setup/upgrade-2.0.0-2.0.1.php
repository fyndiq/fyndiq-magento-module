<?php

$installer = $this;

$entityTypeId = $installer->getEntityTypeId('catalog_product');
$installer->run("
    UPDATE `{$installer->getTable('eav/attribute')}`
    SET `source_model` = 'eav/entity_attribute_source_boolean'
    WHERE attribute_code = 'fyndiq_exported'");

// Flush cache to apply changes
Mage::app()->getCacheInstance()->flush();
