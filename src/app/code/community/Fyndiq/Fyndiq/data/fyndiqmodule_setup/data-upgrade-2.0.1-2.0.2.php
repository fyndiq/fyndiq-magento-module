<?php

$tableName = $this->getTable('fyndiq/category');
$fileName = realpath(dirname(__FILE__)) . '/tree.csv';
$tableColumns = array('id', 'name_sv', 'name_de');

// Populate the Fyndiq categories
try {
    if (file_exists($fileName)) {
        $handle = fopen($fileName, 'r');
        $connection = Mage::getSingleton('core/resource')->getConnection('core_write');
        $connection->beginTransaction();
        //clear the table first
        $connection->delete($tableName);
        while (($data = fgetcsv($handle)) !== FALSE) {
            $connection->insert(
                $tableName,
                array_combine(
                    $tableColumns,
                    $data
                )
            );
        }
        $connection->commit();
        fclose($handle);
    }
} catch (Exception $e) {
    Mage::log($e->getMessage(), Zend_Log::ERR);
}
