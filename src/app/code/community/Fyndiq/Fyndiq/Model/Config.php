<?php

class Fyndiq_Fyndiq_Model_Config
{

    const COMMIT = 'XXXXXX';
    const REPOSITORY_PATH = 'fyndiq/fyndiq-magento-module/';
    const DEFAULT_CONFIG_NAME = 'fyndiq/fyndiq_group';

    private function key($name)
    {
        return self::DEFAULT_CONFIG_NAME . '/' . $name;
    }

    private function getScope($storeId)
    {
        if ($storeId == 0) {
            return 'default';
        }
        return 'stores';
    }

    public function delete($name)
    {
        return Mage::getConfig()->deleteConfig($this->key($name));
    }

    public function get($name, $storeId, $fullNamePath = false)
    {
        $key = $fullNamePath ? $name : $this->key($name);
        $result = Mage::getStoreConfig($key, $storeId);
        // FIXME: Since prior versions use serialized values, we first try to unserialize the data
        // if that fails return the naked value; At some point this should be removed when we're sure
        // all data is stored as plain unserialized strings
        $data = @unserialize($result);
        if ($data !== false) {
            return $data;
        }
        return $result;
    }

    public function getBool($name)
    {
        return (bool)Mage::getStoreConfigFlag($this->key($name));
    }

    public function set($name, $value, $storeId)
    {
        return Mage::getConfig()->saveConfig(
            $this->key($name),
            $value,
            $this->getScope($storeId),
            $storeId
        );
    }

    public function reInit()
    {
        Mage::getConfig()->reinit();
        Mage::app()->reinitStores();
    }

    public function getModuleVersion()
    {
        return (string)Mage::getConfig()->getNode()->modules->Fyndiq_Fyndiq->version;
    }

    public function getUserAgent()
    {
        return FyndiqUtils::getUserAgentString("Magento", Mage::getVersion(), "module", $this->getModuleVersion(), self::COMMIT);
    }

    public function getFeedPath($storeId)
    {
        return Mage::getBaseDir('cache') . '/feed-' . $storeId . '.csv';
    }
}
