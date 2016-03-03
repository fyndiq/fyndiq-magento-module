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
        try {
            $connection->delete($tableName);
            while (($data = fgetcsv($handle)) !== false) {
                $connection->insert(
                    $tableName,
                    array_combine(
                        $tableColumns,
                        $data
                    )
                );
            }
            $connection->commit();
        } catch (Exception $e) {
            $connection->rollback();
            Mage::log($e->getMessage(), Zend_Log::ERR);
        }
        fclose($handle);
    }
} catch (Exception $e) {
    Mage::log($e->getMessage(), Zend_Log::ERR);
}
